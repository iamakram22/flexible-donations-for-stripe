<?php
/**
 * Plugin Name: Stripe Custom Donations
 * Plugin URI: https://github.com/iamakram22/stripe-custom-donations
 * Description: Accept one-time and recurring donations via Stripe Checkout with custom amounts, sandbox testing, and webhook-based confirmation.
 * Version: 3.1.0
 * Author: Mohd Akram
 * Author URI: https://github.com/iamakram22
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: stripe-custom-donations
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Stripe_Custom_Donations
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
define('SCD_VERSION', '3.1.0');
define('SCD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SCD_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader (Stripe SDK)
require_once SCD_PLUGIN_DIR . 'vendor/autoload.php';

// Include required files
require_once SCD_PLUGIN_DIR . 'includes/class-settings.php';
require_once SCD_PLUGIN_DIR . 'includes/class-donation-handler.php';
require_once SCD_PLUGIN_DIR . 'includes/class-webhook-handler.php';

// Initialize plugin
function scd_init_plugin()
{
    // Initialize settings page
    new SCD_Settings();

    // Initialize donation handler
    new SCD_Donation_Handler();

    // Initialize webhook handler
    new SCD_Webhook_Handler();
}
add_action('plugins_loaded', 'scd_init_plugin');

// Resolve active Stripe keys based on mode (live/test)
function scd_get_active_keys()
{
    $options = get_option('scd_stripe_settings');
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

// Register activation hook
register_activation_hook(__FILE__, 'scd_activate_plugin');
function scd_activate_plugin()
{
    // Create donations table
    global $wpdb;
    $table_name = $wpdb->prefix . 'stripe_donations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        stripe_session_id varchar(255) NOT NULL,
        stripe_subscription_id varchar(255) DEFAULT NULL,
        customer_email varchar(255) NOT NULL,
        amount decimal(10,2) NOT NULL,
        currency varchar(10) DEFAULT 'inr',
        payment_type varchar(20) NOT NULL,
        billing_interval varchar(20) DEFAULT NULL,
        status varchar(50) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY stripe_session_id (stripe_session_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Enqueue frontend assets
add_action('wp_enqueue_scripts', 'scd_enqueue_assets');
function scd_enqueue_assets()
{
    wp_enqueue_style('scd-donation-form', SCD_PLUGIN_URL . 'assets/css/donation-form.css', array(), SCD_VERSION);

    wp_enqueue_script('scd-donation-form', SCD_PLUGIN_URL . 'assets/js/donation-form.js', array('jquery'), SCD_VERSION, true);

    // Localize script with AJAX URL and nonce
    $options = get_option('scd_stripe_settings');
    $method = isset($options['submission_method']) ? $options['submission_method'] : 'ajax';

    wp_localize_script('scd-donation-form', 'scdAjax', array(
        'ajax_url'   => admin_url('admin-ajax.php'),
        'rest_url'   => get_rest_url(null, 'scd/v1/create-checkout-session'),
        'nonce'      => wp_create_nonce('scd_donation_nonce'),
        'method'     => $method,
    ));
}