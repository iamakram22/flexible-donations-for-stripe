<?php
/**
 * Handles donation form and Stripe Checkout Session creation
 */

if (!defined('ABSPATH'))
    exit;

class FDFS_Donation_Handler
{

    public function __construct()
    {
        // Register shortcode
        add_shortcode('fdfs_donation_form', array($this, 'render_donation_form'));

        // Register AJAX handlers (for both logged-in and non-logged-in users)
        add_action('wp_ajax_fdfs_create_checkout_session', array($this, 'create_checkout_session'));
        add_action('wp_ajax_nopriv_fdfs_create_checkout_session', array($this, 'create_checkout_session'));

        // Register REST API route
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function register_rest_routes()
    {
        register_rest_route('fdfs/v1', '/create-checkout-session', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_checkout_session_rest'),
            'permission_callback' => array($this, 'check_rest_permission'),
        ));
    }

    public function check_rest_permission($request)
    {
        $nonce = $request->get_param('nonce');
        return (bool) wp_verify_nonce($nonce, 'fdfs_donation_nonce');
    }

    /**
     * Render donation form shortcode
     */
    public function render_donation_form($atts)
    {
        $options = get_option('fdfs_stripe_settings');
        $predefined_amounts = isset($options['predefined_amounts']) ? explode(',', $options['predefined_amounts']) : array('500', '1000', '2000', '5000');
        $currency = isset($options['currency']) ? $options['currency'] : 'inr';
        $intervals = isset($options['recurring_intervals']) ? $options['recurring_intervals'] : array('daily', 'weekly', 'monthly', 'quarterly', 'yearly');
        $button_label = isset($options['button_label']) ? $options['button_label'] : __('Proceed to Payment', 'flexible-donations-for-stripe');

        // Read query params
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only redirect params
        $session_id = isset($_GET['session_id']) ? sanitize_text_field(wp_unslash($_GET['session_id'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only redirect params
        $donation_status = isset($_GET['donation']) ? sanitize_text_field(wp_unslash($_GET['donation'])) : '';

        // Build message state
        $success_message = '';
        $error_message = '';

        if (!empty($session_id)) {
            $success_message = __('Thank you! Your donation was successfully processed.', 'flexible-donations-for-stripe');
        } elseif ($donation_status === 'cancelled') {
            $error_message = __('Your donation was cancelled. You can try again below.', 'flexible-donations-for-stripe');
        }

        ob_start();
        ?>
        <div class="fdfs-donation-form">
            <?php if (!empty($success_message)) : ?>
                <div class="fdfs-success-message">
                    <?php echo esc_html($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)) : ?>
                <div class="fdfs-error-message" style="<?php echo empty($error_message) ? 'display:none;' : ''; ?>">
                    <?php echo esc_html($error_message); ?>
                </div>
            <?php else : ?>
                <div class="fdfs-error-message" style="display:none;"></div>
            <?php endif; ?>

            <h3><?php esc_html_e('Make a Donation', 'flexible-donations-for-stripe'); ?></h3>

            <form id="fdfs-donation-form">
                <!-- Predefined Amounts -->
                <div class="fdfs-amount-buttons">
                    <?php foreach ($predefined_amounts as $amount) : ?>
                        <button type="button" class="fdfs-amount-btn" data-amount="<?php echo esc_attr(trim($amount)); ?>">
                            <?php echo esc_html('$' . number_format(trim($amount))); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Custom Amount -->
                <div class="fdfs-custom-amount">
                    <label for="fdfs-custom-amount"><?php esc_html_e('Or enter custom amount:', 'flexible-donations-for-stripe'); ?></label>
                    <input type="number" id="fdfs-custom-amount" name="custom_amount" min="1"
                        placeholder="<?php esc_attr_e('Enter amount', 'flexible-donations-for-stripe'); ?>" required>
                </div>

                <!-- Email -->
                <div class="fdfs-email">
                    <label for="fdfs-email"><?php esc_html_e('Email:', 'flexible-donations-for-stripe'); ?></label>
                    <input type="email" id="fdfs-email" name="email" required>
                </div>

                <!-- Payment Type -->
                <div class="fdfs-payment-type">
                    <label>
                        <input type="radio" name="payment_type" value="one_time" checked>
                        <?php esc_html_e('One-time donation', 'flexible-donations-for-stripe'); ?>
                    </label>
                    <label>
                        <input type="radio" name="payment_type" value="recurring">
                        <?php esc_html_e('Recurring donation', 'flexible-donations-for-stripe'); ?>
                    </label>
                </div>

                <!-- Recurring Interval (shown when recurring is selected) -->
                <div class="fdfs-interval" style="display: none;">
                    <label for="fdfs-interval"><?php esc_html_e('Frequency:', 'flexible-donations-for-stripe'); ?></label>
                    <select id="fdfs-interval" name="interval">
                        <?php if (in_array('daily', $intervals)) : ?>
                            <option value="daily"><?php esc_html_e('Daily', 'flexible-donations-for-stripe'); ?></option>
                        <?php endif; ?>
                        <?php if (in_array('weekly', $intervals)) : ?>
                            <option value="weekly"><?php esc_html_e('Weekly', 'flexible-donations-for-stripe'); ?></option>
                        <?php endif; ?>
                        <?php if (in_array('monthly', $intervals)) : ?>
                            <option value="monthly"><?php esc_html_e('Monthly', 'flexible-donations-for-stripe'); ?></option>
                        <?php endif; ?>
                        <?php if (in_array('quarterly', $intervals)) : ?>
                            <option value="quarterly"><?php esc_html_e('Quarterly', 'flexible-donations-for-stripe'); ?></option>
                        <?php endif; ?>
                        <?php if (in_array('yearly', $intervals)) : ?>
                            <option value="yearly"><?php esc_html_e('Yearly', 'flexible-donations-for-stripe'); ?></option>
                        <?php endif; ?>
                    </select>
                </div>

                <button type="submit" class="fdfs-submit-btn">
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
        check_ajax_referer('fdfs_donation_nonce', 'nonce');

        // Get and sanitize form data [web:42]
        $amount = isset($_POST['amount']) ? floatval(wp_unslash($_POST['amount'])) : 0;
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $payment_type = isset($_POST['payment_type']) ? sanitize_text_field(wp_unslash($_POST['payment_type'])) : 'one_time';
        $interval = isset($_POST['interval']) ? sanitize_text_field(wp_unslash($_POST['interval'])) : 'monnthly';

        // Validate
        if ($amount < 10) {
            wp_send_json_error(array('message' => __('Minimum donation amount is 10', 'flexible-donations-for-stripe')));
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address', 'flexible-donations-for-stripe')));
        }

        // Get Stripe settings
        $options = get_option('fdfs_stripe_settings');
        $active_keys = fdfs_get_active_keys();
        $secret_key = $active_keys['stripe_secret_key'];
        $currency = isset($options['currency']) ? $options['currency'] : 'inr';
        $donation_page = isset($options['donation_page_url']) && !empty($options['donation_page_url']) ? $options['donation_page_url'] : '/support-us';
        $org_name = get_bloginfo('name');
        $statement_descriptor = substr(preg_replace('/[^a-zA-Z0-9 ]/', '', !empty($options['statement_descriptor']) ? $options['statement_descriptor'] : $org_name), 0, 22);

        if (empty($secret_key)) {
            wp_send_json_error(array('message' => __('Stripe is not configured', 'flexible-donations-for-stripe')));
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
                'success_url' => home_url($donation_page . '?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => home_url($donation_page . '?donation=cancelled'),
                'metadata' => array(
                    'payment_type' => $payment_type,
                    'interval' => $interval,
                    'business_name' => $org_name,
                    'website' => home_url($donation_page),
                ),
            );

            // Add payment intent data for one-time payments
            if ($mode === 'payment') {
                $session_params['payment_intent_data'] = array(
                    'description' => 'Donation',
                    'statement_descriptor' => $statement_descriptor,
                );
            }

            // Add subscription data for recurring payments
            if ($mode === 'subscription') {
                $session_params['subscription_data'] = array(
                    'description' => 'Donation',
                    'metadata' => array(
                        'business_name' => $org_name,
                    ),
                );
            }

            // Create Checkout Session
            $session = \Stripe\Checkout\Session::create($session_params);

            // Save donation record
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table, no WP API exists
            $wpdb->insert(
                $wpdb->prefix . 'fdfs_donations',
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

    public function create_checkout_session_rest(WP_REST_Request $request)
    {
        $amount = floatval($request->get_param('amount'));
        $email = sanitize_email($request->get_param('email'));
        $payment_type = sanitize_text_field($request->get_param('payment_type'));
        $interval = sanitize_text_field($request->get_param('interval'));

        if ($amount < 10) {
            return new WP_REST_Response(array('success' => false, 'data' => array('message' => __('Minimum donation amount is 10', 'flexible-donations-for-stripe'))), 400);
        }

        if (!is_email($email)) {
            return new WP_REST_Response(array('success' => false, 'data' => array('message' => __('Invalid email address', 'flexible-donations-for-stripe'))), 400);
        }

        $options = get_option('fdfs_stripe_settings');
        $active_keys = fdfs_get_active_keys();
        $secret_key = $active_keys['stripe_secret_key'];
        $currency = isset($options['currency']) ? $options['currency'] : 'inr';
        $donation_page = isset($options['donation_page_url']) && !empty($options['donation_page_url']) ? $options['donation_page_url'] : '/support-us';
        $org_name = get_bloginfo('name');
        $statement_descriptor = substr(preg_replace('/[^a-zA-Z0-9 ]/', '', !empty($options['statement_descriptor']) ? $options['statement_descriptor'] : $org_name), 0, 22);

        if (empty($secret_key)) {
            return new WP_REST_Response(array('success' => false, 'data' => array('message' => __('Stripe is not configured', 'flexible-donations-for-stripe'))), 400);
        }

        try {
            \Stripe\Stripe::setApiKey($secret_key);

            $line_items = array();

            if ($payment_type === 'recurring') {
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
                    default:
                        $stripe_interval = 'month';
                        $interval_count = 1;
                        break;
                }

                $line_items[] = array(
                    'price_data' => array(
                        'currency' => $currency,
                        'product_data' => array('name' => 'Donation'),
                        'unit_amount' => $amount * 100,
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
                        'product_data' => array('name' => 'Donation'),
                        'unit_amount' => $amount * 100,
                    ),
                    'quantity' => 1,
                );

                $mode = 'payment';
            }

            $session_params = array(
                'payment_method_types' => array('card'),
                'line_items' => $line_items,
                'mode' => $mode,
                'customer_email' => $email,
                'success_url' => home_url($donation_page . '?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => home_url($donation_page . '?donation=cancelled'),
                'metadata' => array(
                    'payment_type' => $payment_type,
                    'interval' => $interval,
                    'business_name' => $org_name,
                    'website' => home_url($donation_page),
                ),
            );

            if ($mode === 'payment') {
                $session_params['payment_intent_data'] = array(
                    'description' => 'Donation',
                    'statement_descriptor' => $statement_descriptor,
                );
            }

            if ($mode === 'subscription') {
                $session_params['subscription_data'] = array(
                    'description' => 'Donation',
                    'metadata' => array(
                        'business_name' => $org_name,
                    ),
                );
            }

            $session = \Stripe\Checkout\Session::create($session_params);

            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table, no WP API exists
            $wpdb->insert(
                $wpdb->prefix . 'fdfs_donations',
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

            return new WP_REST_Response(array(
                'success' => true,
                'data' => array(
                    'session_id' => $session->id,
                    'url' => $session->url,
                ),
            ), 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(array('success' => false, 'data' => array('message' => $e->getMessage())), 500);
        }
    }
}