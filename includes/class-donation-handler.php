<?php
/**
 * Handles donation form and Stripe Checkout Session creation
 */

if (!defined('ABSPATH'))
    exit;

class SCD_Donation_Handler
{

    public function __construct()
    {
        // Register shortcode
        add_shortcode('stripe_donation_form', array($this, 'render_donation_form'));

        // Register AJAX handlers (for both logged-in and non-logged-in users)
        add_action('wp_ajax_scd_create_checkout_session', array($this, 'create_checkout_session'));
        add_action('wp_ajax_nopriv_scd_create_checkout_session', array($this, 'create_checkout_session'));
    }

    /**
     * Render donation form shortcode
     */
    public function render_donation_form($atts)
    {
        $options = get_option('scd_stripe_settings');
        $predefined_amounts = isset($options['predefined_amounts']) ? explode(',', $options['predefined_amounts']) : array('500', '1000', '2000', '5000');
        $currency = isset($options['currency']) ? $options['currency'] : 'inr';
        $intervals = isset($options['recurring_intervals']) ? $options['recurring_intervals'] : array('daily', 'weekly', 'monthly', 'quarterly', 'yearly');
        $button_label = isset($options['button_label']) ? $options['button_label'] : __('Proceed to Payment', 'stripe-donations');

        // Read query params
        $session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';
        $donation_status = isset($_GET['donation']) ? sanitize_text_field($_GET['donation']) : '';

        // Build message state
        $success_message = '';
        $error_message = '';

        if (!empty($session_id)) {
            $success_message = __('Thank you! Your donation was successfully processed.', 'stripe-donations');
        } elseif ($donation_status === 'cancelled') {
            $error_message = __('Your donation was cancelled. You can try again below.', 'stripe-donations');
        }

        ob_start();
        ?>
        <div class="scd-donation-form">
            <?php if (!empty($success_message)) : ?>
                <div class="scd-success-message">
                    <?php echo esc_html($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)) : ?>
                <div class="scd-error-message" style="<?php echo empty($error_message) ? 'display:none;' : ''; ?>">
                    <?php echo esc_html($error_message); ?>
                </div>
            <?php else : ?>
                <div class="scd-error-message" style="display:none;"></div>
            <?php endif; ?>

            <h3><?php _e('Make a Donation', 'stripe-donations'); ?></h3>

            <form id="scd-donation-form">
                <!-- Predefined Amounts -->
                <div class="scd-amount-buttons">
                    <?php foreach ($predefined_amounts as $amount) : ?>
                        <button type="button" class="scd-amount-btn" data-amount="<?php echo esc_attr(trim($amount)); ?>">
                            <?php echo esc_html('$' . number_format(trim($amount))); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Custom Amount -->
                <div class="scd-custom-amount">
                    <label for="scd-custom-amount"><?php _e('Or enter custom amount:', 'stripe-donations'); ?></label>
                    <input type="number" id="scd-custom-amount" name="custom_amount" min="1"
                        placeholder="<?php _e('Enter amount', 'stripe-donations'); ?>" required>
                </div>

                <!-- Email -->
                <div class="scd-email">
                    <label for="scd-email"><?php _e('Email:', 'stripe-donations'); ?></label>
                    <input type="email" id="scd-email" name="email" required>
                </div>

                <!-- Payment Type -->
                <div class="scd-payment-type">
                    <label>
                        <input type="radio" name="payment_type" value="one_time" checked>
                        <?php _e('One-time donation', 'stripe-donations'); ?>
                    </label>
                    <label>
                        <input type="radio" name="payment_type" value="recurring">
                        <?php _e('Recurring donation', 'stripe-donations'); ?>
                    </label>
                </div>

                <!-- Recurring Interval (shown when recurring is selected) -->
                <div class="scd-interval" style="display: none;">
                    <label for="scd-interval"><?php _e('Frequency:', 'stripe-donations'); ?></label>
                    <select id="scd-interval" name="interval">
                        <?php if (in_array('daily', $intervals)) : ?>
                            <option value="daily"><?php _e('Daily', 'stripe-donations'); ?></option>
                        <?php endif; ?>
                        <?php if (in_array('weekly', $intervals)) : ?>
                            <option value="weekly"><?php _e('Weekly', 'stripe-donations'); ?></option>
                        <?php endif; ?>
                        <?php if (in_array('monthly', $intervals)) : ?>
                            <option value="monthly"><?php _e('Monthly', 'stripe-donations'); ?></option>
                        <?php endif; ?>
                        <?php if (in_array('quarterly', $intervals)) : ?>
                            <option value="quarterly"><?php _e('Quarterly', 'stripe-donations'); ?></option>
                        <?php endif; ?>
                        <?php if (in_array('yearly', $intervals)) : ?>
                            <option value="yearly"><?php _e('Yearly', 'stripe-donations'); ?></option>
                        <?php endif; ?>
                    </select>
                </div>

                <button type="submit" class="scd-submit-btn">
                    <?php echo esc_html($button_label); ?>
                </button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Create Stripe Checkout Session via AJAX [web:44]
     */
    public function create_checkout_session()
    {
        // Verify nonce for security [web:39]
        check_ajax_referer('scd_donation_nonce', 'nonce');

        // Get and sanitize form data [web:42]
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $payment_type = isset($_POST['payment_type']) ? sanitize_text_field($_POST['payment_type']) : 'one_time';
        $interval = isset($_POST['interval']) ? sanitize_text_field($_POST['interval']) : 'monnthly';

        // Validate
        if ($amount < 10) {
            wp_send_json_error(array('message' => __('Minimum donation amount is 10', 'stripe-donations')));
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address', 'stripe-donations')));
        }

        // Get Stripe settings
        $options = get_option('scd_stripe_settings');
        $secret_key = isset($options['stripe_secret_key']) ? $options['stripe_secret_key'] : '';
        $currency = isset($options['currency']) ? $options['currency'] : 'inr';

        if (empty($secret_key)) {
            wp_send_json_error(array('message' => __('Stripe is not configured', 'stripe-donations')));
        }

        try {
            // Initialize Stripe
            \Stripe\Stripe::setApiKey($secret_key);

            // Prepare line items
            $line_items = array();

            if ($payment_type === 'recurring') {
                // Map interval to Stripe format                
                switch ($interval) {
                    case 'daily':
                        $stripe_interval = 'day';
                        $interval_count = 1;
                        break;

                    case 'weekly':
                        $stripe_interval = 'week';
                        $interval_count = 1;
                        break;

                    case 'quarterly':
                        $stripe_interval = 'month';
                        $interval_count = 3;
                        break;

                    case 'yearly':
                        $stripe_interval = 'year';
                        $interval_count = 1;
                        break;

                    default: // monthly
                        $stripe_interval = 'month';
                        $interval_count = 1;
                        break;
                }

                $line_items[] = array(
                    'price_data' => array(
                        'currency' => $currency,
                        'product_data' => array(
                            'name' => 'Donation',
                        ),
                        'unit_amount' => $amount * 100, // Convert to cents
                        'recurring' => array(
                            'interval' => $stripe_interval,
                            'interval_count' => $interval_count,
                        ),
                    ),
                    'quantity' => 1,
                );

                $mode = 'subscription';
            } else {
                $line_items[] = array(
                    'price_data' => array(
                        'currency' => $currency,
                        'product_data' => array(
                            'name' => 'Donation',
                        ),
                        'unit_amount' => $amount * 100,
                    ),
                    'quantity' => 1,
                );

                $mode = 'payment';
            }

            // Create Checkout Session
            // Prepare session parameters
            $session_params = array(
                'payment_method_types' => array('card'),
                'line_items' => $line_items,
                'mode' => $mode,
                'customer_email' => $email,
                'success_url' => home_url('/support-us?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => home_url('/support-us?donation=cancelled'),
                'metadata' => array(
                    'payment_type' => $payment_type,
                    'interval' => $interval,
                    'business_name' => 'Center for Resource Solutions',
                    'website' => 'https://resource-solutions.org/support-us/',
                ),
            );

            // Add payment intent data for one-time payments
            if ($mode === 'payment') {
                $session_params['payment_intent_data'] = array(
                    'description' => 'Donation',
                    'statement_descriptor' => 'CRS Donation',
                );
            }

            // Add subscription data for recurring payments
            if ($mode === 'subscription') {
                $session_params['subscription_data'] = array(
                    'description' => 'Donation',
                    'metadata' => array(
                        'business_name' => 'Center for Resource Solutions',
                    ),
                );
            }

            // Create Checkout Session
            $session = \Stripe\Checkout\Session::create($session_params);

            // Save donation record
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'stripe_donations',
                array(
                    'stripe_session_id' => $session->id,
                    'customer_email' => $email,
                    'amount' => $amount,
                    'currency' => $currency,
                    'payment_type' => $payment_type,
                    'billing_interval' => $interval,
                    'status' => 'pending',
                ),
                array('%s', '%s', '%f', '%s', '%s', '%s', '%s')
            );

            wp_send_json_success(array(
                'session_id' => $session->id,
                'url' => $session->url
            ));

        }
        catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}