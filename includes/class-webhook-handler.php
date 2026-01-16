<?php
/**
 * Handle Stripe webhooks for payment confirmations
 */

if (!defined('ABSPATH'))
    exit;

class SCD_Webhook_Handler
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
        register_rest_route('scd/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true', // Stripe signature verification handles security
        ));
    }

    /**
     * Handle incoming webhooks
     */
    public function handle_webhook($request)
    {
        $options = get_option('scd_stripe_settings');
        $webhook_secret = isset($options['stripe_webhook_secret']) ? $options['stripe_webhook_secret'] : '';
        $secret_key = isset($options['stripe_secret_key']) ? $options['stripe_secret_key'] : '';

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

        $wpdb->update(
            $wpdb->prefix . 'stripe_donations',
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

        $wpdb->insert(
            $wpdb->prefix . 'stripe_donations',
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

        $wpdb->update(
            $wpdb->prefix . 'stripe_donations',
            array('status' => 'cancelled'),
            array('stripe_subscription_id' => $subscription->id),
            array('%s'),
            array('%s')
        );
    }

    /**
     * Send confirmation email to customer
     */
    private function send_confirmation_email($session)
    {
        $to = $session->customer_email;
        $admin_email = get_option('admin_email');
        $subject = __('Thank you for your donation', 'stripe-donations');
        $headers = array(
            'Cc: ' . $admin_email
        );
        $message = sprintf(
            __('Your donation of %s %s has been received. Thank you for your support!', 'stripe-donations'),
            strtoupper($session->currency),
            number_format($session->amount_total / 100, 2)
        );

        wp_mail($to, $subject, $message);
    }
}