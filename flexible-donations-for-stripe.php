<?php
/**
 * Plugin Name: Flexible Donations for Stripe
 * Plugin URI: https://github.com/iamakram22/flexible-donations-for-stripe
 * Description: Accept one-time and recurring donations via Stripe Checkout with custom amounts, sandbox testing, and webhook-based confirmation.
 * Version: 3.3.1
 * Author: Mohd Akram
 * Author URI: https://github.com/iamakram22
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: flexible-donations-for-stripe
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Flexible_Donations_For_Stripe
 */

// Copyright (c) 2026 Mohd Akram.
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FDFS_VERSION', '3.3.1');
define('FDFS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FDFS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader (Stripe SDK)
require_once FDFS_PLUGIN_DIR . 'vendor/autoload.php';

// Include required files
require_once FDFS_PLUGIN_DIR . 'includes/class-settings.php';
require_once FDFS_PLUGIN_DIR . 'includes/class-donation-handler.php';
require_once FDFS_PLUGIN_DIR . 'includes/class-webhook-handler.php';
require_once FDFS_PLUGIN_DIR . 'includes/class-helpers.php';

// Initialize plugin
function fdfs_init_plugin()
{
    // Initialize settings page
    new FDFS_Settings();

    // Initialize donation handler
    new FDFS_Donation_Handler();

    // Initialize webhook handler
    new FDFS_Webhook_Handler();
}
add_action('plugins_loaded', 'fdfs_init_plugin');

// Migrate legacy (pre-3.3.0 "stripe-donations" / scd_ era) data. Idempotent: runs on every load
// so it also works when the plugin files are replaced manually (activation hook never fires).
function fdfs_migrate_legacy()
{
    // 0. Keep the donations table schema in sync (adds new columns on upgrade
    // without needing deactivate/reactivate).
    if (get_option('fdfs_db_version') !== FDFS_VERSION) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta(fdfs_donations_table_sql());
        update_option('fdfs_db_version', FDFS_VERSION);
    }

    // 1. Settings: copy old option key if the new one is missing.
    if (false === get_option('fdfs_stripe_settings')) {
        $legacy_settings = get_option('scd_stripe_settings');
        if (false !== $legacy_settings) {
            update_option('fdfs_stripe_settings', $legacy_settings);
        }
    }

    // 2. Donations table: rename (or copy) legacy rows into the new table.
    global $wpdb;
    // Table names are built from $wpdb->prefix, which is a config-supplied safe identifier.
    $old_table = $wpdb->prefix . 'stripe_donations';
    $new_table = $wpdb->prefix . 'fdfs_donations';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration
    $old_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $old_table));
    if (!$old_exists) {
        return;
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration
    $new_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $new_table));
    if (!$new_exists) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- one-time migration; DDL cannot use prepare()
        $wpdb->query("RENAME TABLE {$old_table} TO {$new_table}");
        return;
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- one-time migration; identifier from wpdb->prefix
    $new_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$new_table}");
    if (0 === $new_count) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- one-time migration; identifier from wpdb->prefix
        $wpdb->query("INSERT INTO {$new_table} (id, stripe_session_id, stripe_subscription_id, customer_email, amount, currency, payment_type, billing_interval, status, created_at) SELECT id, stripe_session_id, stripe_subscription_id, customer_email, amount, currency, payment_type, billing_interval, status, created_at FROM {$old_table}");
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.SchemaChange -- one-time migration; identifier from wpdb->prefix
    $wpdb->query("DROP TABLE IF EXISTS {$old_table}");
}
add_action('plugins_loaded', 'fdfs_migrate_legacy', 5);

// Resolve active Stripe keys based on mode (live/test)
function fdfs_get_active_keys()
{
    $options = get_option('fdfs_stripe_settings');
    $mode = isset($options['stripe_mode']) ? $options['stripe_mode'] : 'live';
    $test = ($mode === 'test');

    return array(
        'stripe_secret_key' => $test
            ? (isset($options['stripe_test_secret_key']) ? $options['stripe_test_secret_key'] : '')
            : (isset($options['stripe_secret_key']) ? $options['stripe_secret_key'] : ''),
        'stripe_webhook_secret' => $test
            ? (isset($options['stripe_test_webhook_secret']) ? $options['stripe_test_webhook_secret'] : '')
            : (isset($options['stripe_webhook_secret']) ? $options['stripe_webhook_secret'] : ''),
    );
}

// Donations table schema (shared by activation and the schema-sync check in fdfs_migrate_legacy)
function fdfs_donations_table_sql()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'fdfs_donations';
    $charset_collate = $wpdb->get_charset_collate();

    return "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        stripe_session_id varchar(255) NOT NULL,
        stripe_subscription_id varchar(255) DEFAULT NULL,
        customer_email varchar(255) NOT NULL,
        amount decimal(10,2) NOT NULL,
        currency varchar(10) DEFAULT 'inr',
        payment_type varchar(20) NOT NULL,
        billing_interval varchar(20) DEFAULT NULL,
        donor_note text DEFAULT NULL,
        status varchar(50) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY stripe_session_id (stripe_session_id)
    ) $charset_collate;";
}

// Register activation hook
register_activation_hook(__FILE__, 'fdfs_activate_plugin');
function fdfs_activate_plugin()
{
    // Create donations table
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta(fdfs_donations_table_sql());
}

// Enqueue frontend assets
add_action('wp_enqueue_scripts', 'fdfs_enqueue_assets');
function fdfs_enqueue_assets()
{
    wp_enqueue_style('fdfs-donation-form', FDFS_PLUGIN_URL . 'assets/css/donation-form.css', array(), FDFS_VERSION);

    wp_enqueue_script('fdfs-donation-form', FDFS_PLUGIN_URL . 'assets/js/donation-form.js', array('jquery'), FDFS_VERSION, true);

    // Localize script with AJAX URL and nonce
    $options = get_option('fdfs_stripe_settings');
    $method = isset($options['submission_method']) ? $options['submission_method'] : 'ajax';

    wp_localize_script('fdfs-donation-form', 'fdfsAjax', array(
        'ajax_url'   => admin_url('admin-ajax.php'),
        'rest_url'   => get_rest_url(null, 'fdfs/v1/create-checkout-session'),
        'nonce_url'  => get_rest_url(null, 'fdfs/v1/nonce'),
        'nonce'      => wp_create_nonce('fdfs_donation_nonce'),
        'method'     => $method,
    ));
}