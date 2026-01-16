<?php
/**
 * Settings page for Stripe configuration
 * Uses WordPress Settings API for secure storage
 */

if (!defined('ABSPATH'))
    exit;

class SCD_Settings
{

    private $option_group = 'scd_settings_group';
    private $option_name = 'scd_stripe_settings';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add settings page to WordPress admin
     */
    public function add_settings_page()
    {
        add_options_page(
            __('Stripe Donations Settings', 'stripe-donations'),
            __('Stripe Donations', 'stripe-donations'),
            'manage_options',
            'stripe-donations-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings using WordPress Settings API
     * This securely stores data in wp_options table [web:43]
     */
    public function register_settings()
    {
        register_setting(
            $this->option_group,
            $this->option_name,
            array($this, 'sanitize_settings')
        );

        // Section: Stripe API Keys
        add_settings_section(
            'scd_api_section',
            __('Stripe API Configuration', 'stripe-donations'),
            array($this, 'api_section_callback'),
            'stripe-donations-settings'
        );

        add_settings_field(
            'stripe_secret_key',
            __('Secret Key', 'stripe-donations'),
            array($this, 'secret_key_callback'),
            'stripe-donations-settings',
            'scd_api_section'
        );

        add_settings_field(
            'stripe_publishable_key',
            __('Publishable Key', 'stripe-donations'),
            array($this, 'publishable_key_callback'),
            'stripe-donations-settings',
            'scd_api_section'
        );

        add_settings_field(
            'stripe_webhook_secret',
            __('Webhook Secret', 'stripe-donations'),
            array($this, 'webhook_secret_callback'),
            'stripe-donations-settings',
            'scd_api_section'
        );

        // Section: Donation Configuration
        add_settings_section(
            'scd_donation_section',
            __('Donation Configuration', 'stripe-donations'),
            array($this, 'donation_section_callback'),
            'stripe-donations-settings'
        );

        add_settings_field(
            'predefined_amounts',
            __('Predefined Amounts', 'stripe-donations'),
            array($this, 'predefined_amounts_callback'),
            'stripe-donations-settings',
            'scd_donation_section'
        );

        add_settings_field(
            'currency',
            __('Currency', 'stripe-donations'),
            array($this, 'currency_callback'),
            'stripe-donations-settings',
            'scd_donation_section'
        );

        add_settings_field(
            'recurring_intervals',
            __('Recurring Intervals', 'stripe-donations'),
            array($this, 'recurring_intervals_callback'),
            'stripe-donations-settings',
            'scd_donation_section'
        );
    }

    /**
     * Sanitize all settings before saving [web:42]
     */
    public function sanitize_settings($input)
    {
        $sanitized = array();

        // Sanitize API keys
        if (isset($input['stripe_secret_key'])) {
            $sanitized['stripe_secret_key'] = sanitize_text_field($input['stripe_secret_key']);
        }

        if (isset($input['stripe_publishable_key'])) {
            $sanitized['stripe_publishable_key'] = sanitize_text_field($input['stripe_publishable_key']);
        }

        if (isset($input['stripe_webhook_secret'])) {
            $sanitized['stripe_webhook_secret'] = sanitize_text_field($input['stripe_webhook_secret']);
        }

        // Sanitize predefined amounts (comma-separated)
        if (isset($input['predefined_amounts'])) {
            $sanitized['predefined_amounts'] = sanitize_text_field($input['predefined_amounts']);
        }

        // Sanitize currency
        if (isset($input['currency'])) {
            $sanitized['currency'] = sanitize_text_field($input['currency']);
        }

        // Sanitize recurring intervals (array)
        if (isset($input['recurring_intervals'])) {
            $sanitized['recurring_intervals'] = array_map('sanitize_text_field', $input['recurring_intervals']);
        }

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Show success message
        if (isset($_GET['settings-updated'])) {
            add_settings_error('scd_messages', 'scd_message', __('Settings Saved', 'stripe-donations'), 'updated');
        }

        settings_errors('scd_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_group);
                do_settings_sections('stripe-donations-settings');
                submit_button(__('Save Settings', 'stripe-donations'));
                ?>
            </form>

            <hr>
            <h2><?php _e('Shortcode Usage', 'stripe-donations'); ?></h2>
            <p><code>[stripe_donation_form]</code></p>

            <h2><?php _e('Webhook URL', 'stripe-donations'); ?></h2>
            <p><code><?php echo home_url('/wp-json/scd/v1/webhook'); ?></code></p>
            <p><?php _e('Add this URL to your Stripe Dashboard > Webhooks', 'stripe-donations'); ?></p>
        </div>
        <?php
    }

    // Section callbacks
    public function api_section_callback()
    {
        echo '<p>' . __('Enter your Stripe API keys. Keys are stored securely in the database.', 'stripe-donations') . '</p>';
    }

    public function donation_section_callback()
    {
        echo '<p>' . __('Configure donation amounts and recurring intervals.', 'stripe-donations') . '</p>';
    }

    // Field callbacks
    public function secret_key_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['stripe_secret_key']) ? $options['stripe_secret_key'] : '';
        echo '<input type="password" name="' . $this->option_name . '[stripe_secret_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Starts with sk_test_ or sk_live_', 'stripe-donations') . '</p>';
    }

    public function publishable_key_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['stripe_publishable_key']) ? $options['stripe_publishable_key'] : '';
        echo '<input type="text" name="' . $this->option_name . '[stripe_publishable_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Starts with pk_test_ or pk_live_', 'stripe-donations') . '</p>';
    }

    public function webhook_secret_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['stripe_webhook_secret']) ? $options['stripe_webhook_secret'] : '';
        echo '<input type="password" name="' . $this->option_name . '[stripe_webhook_secret]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Starts with whsec_', 'stripe-donations') . '</p>';
    }

    public function predefined_amounts_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['predefined_amounts']) ? $options['predefined_amounts'] : '10,20,50,100';
        echo '<input type="text" name="' . $this->option_name . '[predefined_amounts]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Comma-separated values (e.g., 10,20,50,100)', 'stripe-donations') . '</p>';
    }

    public function currency_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['currency']) ? $options['currency'] : 'usd';
        echo '<input type="text" name="' . $this->option_name . '[currency]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('3-letter ISO code (e.g., usd)', 'stripe-donations') . '</p>';
    }

    public function recurring_intervals_callback()
    {
        $options = get_option($this->option_name);
        $selected = isset($options['recurring_intervals']) ? $options['recurring_intervals'] : array('monthly', 'quarterly', 'yearly');

        $intervals = array(
            'monthly' => __('Monthly', 'stripe-donations'),
            'quarterly' => __('Quarterly (Every 3 months)', 'stripe-donations'),
            'yearly' => __('Yearly', 'stripe-donations')
        );

        foreach ($intervals as $key => $label) {
            $checked = in_array($key, $selected) ? 'checked' : '';
            echo '<label><input type="checkbox" name="' . $this->option_name . '[recurring_intervals][]" value="' . esc_attr($key) . '" ' . $checked . '> ' . esc_html($label) . '</label><br>';
        }
    }
}