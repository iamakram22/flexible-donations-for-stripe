<?php
/**
 * Handle Stripe webhooks for payment confirmations
 */

if (!defined('ABSPATH'))
    exit;

class FDFS_Webhook_Handler
{

    public function __construct()
    {
        // Register REST API endpoint for webhooks
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
    }

    /**
     * Register webhook endpoint
     */
    public function register_webhook_endpoint()
    {
        register_rest_route('fdfs/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true', // Stripe signature verification handles security
        ));

        // Legacy route alias (pre-3.3.0): keeps confirmations flowing until the
        // webhook URL in the Stripe dashboard is updated to fdfs/v1/webhook.
        register_rest_route('scd/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handle incoming webhooks
     */
    public function handle_webhook($request)
    {
        $active_keys = fdfs_get_active_keys();
        $webhook_secret = $active_keys['stripe_webhook_secret'];
        $secret_key = $active_keys['stripe_secret_key'];

        if (empty($webhook_secret)) {
            return new WP_REST_Response(array('error' => 'Webhook not configured'), 400);
        }

        $payload = $request->get_body();
        $sig_header = $request->get_header('stripe_signature');

        try {
            // Verify webhook signature [web:47]
            \Stripe\Stripe::setApiKey($secret_key);
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);

            // Handle different event types
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handle_checkout_completed($event->data->object);
                    break;

                case 'invoice.payment_succeeded':
                    $this->handle_recurring_payment($event->data->object);
                    break;

                case 'customer.subscription.deleted':
                    $this->handle_subscription_cancelled($event->data->object);
                    break;
            }

            return new WP_REST_Response(array('status' => 'success'), 200);

        }
        catch (\Exception $e) {
            return new WP_REST_Response(array('error' => $e->getMessage()), 400);
        }
    }

    /**
     * Handle successful checkout
     */
    private function handle_checkout_completed($session)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, no WP API exists
        $wpdb->update(
            $wpdb->prefix . 'fdfs_donations',
            array(
                'status' => 'completed',
                'stripe_subscription_id' => isset($session->subscription) ? $session->subscription : null,
            ),
            array('stripe_session_id' => $session->id),
            array('%s', '%s'),
            array('%s')
        );

        // Send confirmation email
        $this->send_confirmation_email($session);
    }

    /**
     * Handle recurring payment
     */
    private function handle_recurring_payment($invoice)
    {
        // Log recurring payment
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table, no WP API exists
        $wpdb->insert(
            $wpdb->prefix . 'fdfs_donations',
            array(
                'stripe_subscription_id' => $invoice->subscription,
                'customer_email' => $invoice->customer_email,
                'amount' => $invoice->amount_paid / 100,
                'currency' => $invoice->currency,
                'payment_type' => 'recurring',
                'status' => 'completed',
            ),
            array('%s', '%s', '%f', '%s', '%s', '%s')
        );
    }

    /**
     * Handle subscription cancellation
     */
    private function handle_subscription_cancelled($subscription)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, no WP API exists
        $wpdb->update(
            $wpdb->prefix . 'fdfs_donations',
            array('status' => 'cancelled'),
            array('stripe_subscription_id' => $subscription->id),
            array('%s'),
            array('%s')
        );
    }

    /**
     * Send confirmation email with dynamic template
     *
     * @param object $session Stripe Checkout Session object.
     * @return bool True if wp_mail() accepted the message, false otherwise.
     */
    private function send_confirmation_email($session)
    {
        try {
            global $wpdb;

            // Get email settings
            $options = get_option('fdfs_stripe_settings');

            // Email configuration
            $from_name = isset($options['email_from_name']) ? $options['email_from_name'] : get_bloginfo('name');
            $from_email = isset($options['email_from_address']) ? $options['email_from_address'] : get_bloginfo('admin_email');
            $subject = isset($options['email_subject']) ? $options['email_subject'] : 'Thank you for your contribution';
            $template = isset($options['email_template']) ? $options['email_template'] : '';

            // Default template if empty
            if (empty($template)) {
                $template = "Thank you for your contribution of {amount}. We deeply appreciate your support. A receipt will be emailed to you soon. If you have any questions, please reply to this email.";
            }

            $session_id = isset($session->id) ? $session->id : '';

            // Resolve donor email: customer_email is only the prefill value and may be
            // null if the donor edited it on the Checkout page. The completed address
            // lives in customer_details->email; fall back to our stored record.
            $customer_email = isset($session->customer_details->email) ? $session->customer_details->email : null;
            if (empty($customer_email) && isset($session->customer_email)) {
                $customer_email = $session->customer_email;
            }
            if (empty($customer_email) && !empty($session_id)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, no WP API exists
                $customer_email = $wpdb->get_var($wpdb->prepare("SELECT customer_email FROM {$wpdb->prefix}fdfs_donations WHERE stripe_session_id = %s", $session_id));
            }
            if (empty($customer_email) || !is_email($customer_email)) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- failure needs to be diagnosable in PHP error log
                error_log('[FDFS] confirmation email skipped: no valid donor email for session ' . $session_id);
                return false;
            }

            // Resolve amount/currency with the same DB fallback.
            $currency = isset($session->currency) ? $session->currency : null;
            $amount_total = isset($session->amount_total) ? $session->amount_total : null;
            if ((empty($currency) || null === $amount_total) && !empty($session_id)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, no WP API exists
                $row = $wpdb->get_row($wpdb->prepare("SELECT amount, currency FROM {$wpdb->prefix}fdfs_donations WHERE stripe_session_id = %s", $session_id));
                if ($row) {
                    if (empty($currency)) {
                        $currency = $row->currency;
                    }
                    if (null === $amount_total) {
                        $amount_total = (float) $row->amount * 100;
                    }
                }
            }
            if (empty($currency)) {
                $currency = isset($options['currency']) ? $options['currency'] : 'inr';
            }
            if (null === $amount_total) {
                $amount_total = 0;
            }

            // Prepare dynamic data
            $amount_formatted = fdfs_get_currency_symbol($currency) . number_format($amount_total / 100, 2);
            $payment_type = isset($session->metadata->payment_type) ? ucfirst($session->metadata->payment_type) : 'One-time';
            $interval = isset($session->metadata->interval) ? ucfirst($session->metadata->interval) : 'N/A';
            $current_date = date_i18n(get_option('date_format'));

            // Replace placeholders
            $placeholders = array(
                '{amount}' => $amount_formatted,
                '{email}' => $customer_email,
                '{payment_type}' => str_replace('_', '-', $payment_type),
                '{interval}' => $interval,
                '{date}' => $current_date,
            );

            $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);
            $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);

            // Set custom headers
            $headers = array(
                'From: ' . $from_name . ' <' . $from_email . '>',
                'Content-Type: text/html; charset=UTF-8'
            );

            // Convert line breaks to <br> for HTML email
            $message = nl2br($message);

            // Send email
            $sent = wp_mail($customer_email, $subject, $message, $headers);
            if (!$sent) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- failure needs to be diagnosable in PHP error log
                error_log('[FDFS] wp_mail failed for session ' . $session_id . ' to ' . $customer_email);
            }
            return (bool) $sent;
        }
        catch (\Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- failure needs to be diagnosable in PHP error log
            error_log('[FDFS] confirmation email error: ' . $e->getMessage());
            return false;
        }
    }
}