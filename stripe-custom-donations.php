<?php
/**
 * Plugin Name: Stripe Custom Donations
 * Plugin URI: https://yoursite.com
 * Description: Custom Stripe donation integration with recurring payments
 * Version: 1.0.0
 * Author: Mohd Akram
 * Author URI: https://github.com/iamakram22
 * Text Domain: stripe-donations
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SCD_VERSION', '1.0.0');
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
    wp_localize_script('scd-donation-form', 'scdAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('scd_donation_nonce')
    ));
}