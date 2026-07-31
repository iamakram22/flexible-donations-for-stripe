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
            __('Stripe Donations Settings', 'stripe-custom-donations'),
            __('Stripe Donations', 'stripe-custom-donations'),
            'manage_options',
            'stripe-custom-donations-settings',
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
            __('Stripe API Configuration', 'stripe-custom-donations'),
            array($this, 'api_section_callback'),
            'stripe-custom-donations-settings'
        );

        add_settings_field(
            'stripe_secret_key',
            __('Secret Key', 'stripe-custom-donations'),
            array($this, 'secret_key_callback'),
            'stripe-custom-donations-settings',
            'scd_api_section'
        );

        add_settings_field(
            'stripe_publishable_key',
            __('Publishable Key', 'stripe-custom-donations'),
            array($this, 'publishable_key_callback'),
            'stripe-custom-donations-settings',
            'scd_api_section'
        );

        add_settings_field(
            'stripe_webhook_secret',
            __('Webhook Secret', 'stripe-custom-donations'),
            array($this, 'webhook_secret_callback'),
            'stripe-custom-donations-settings',
            'scd_api_section'
        );

        // Section: Sandbox / Test Mode
        add_settings_section(
            'scd_test_section',
            __('Sandbox / Test Mode', 'stripe-custom-donations'),
            array($this, 'test_section_callback'),
            'stripe-custom-donations-settings'
        );

        add_settings_field(
            'stripe_mode',
            __('Mode', 'stripe-custom-donations'),
            array($this, 'stripe_mode_callback'),
            'stripe-custom-donations-settings',
            'scd_test_section'
        );

        add_settings_field(
            'stripe_test_secret_key',
            __('Test Secret Key', 'stripe-custom-donations'),
            array($this, 'test_secret_key_callback'),
            'stripe-custom-donations-settings',
            'scd_test_section'
        );

        add_settings_field(
            'stripe_test_publishable_key',
            __('Test Publishable Key', 'stripe-custom-donations'),
            array($this, 'test_publishable_key_callback'),
            'stripe-custom-donations-settings',
            'scd_test_section'
        );

        add_settings_field(
            'stripe_test_webhook_secret',
            __('Test Webhook Secret', 'stripe-custom-donations'),
            array($this, 'test_webhook_secret_callback'),
            'stripe-custom-donations-settings',
            'scd_test_section'
        );

        // Section: Donation Configuration
        add_settings_section(
            'scd_donation_section',
            __('Donation Configuration', 'stripe-custom-donations'),
            array($this, 'donation_section_callback'),
            'stripe-custom-donations-settings'
        );

        add_settings_field(
            'predefined_amounts',
            __('Predefined Amounts', 'stripe-custom-donations'),
            array($this, 'predefined_amounts_callback'),
            'stripe-custom-donations-settings',
            'scd_donation_section'
        );

        add_settings_field(
            'currency',
            __('Currency', 'stripe-custom-donations'),
            array($this, 'currency_callback'),
            'stripe-custom-donations-settings',
            'scd_donation_section'
        );

        add_settings_field(
            'recurring_intervals',
            __('Recurring Intervals', 'stripe-custom-donations'),
            array($this, 'recurring_intervals_callback'),
            'stripe-custom-donations-settings',
            'scd_donation_section'
        );

        add_settings_field(
            'button_label',
            __('Donation Button Label', 'stripe-custom-donations'),
            array($this, 'button_label_callback'),
            'stripe-custom-donations-settings',
            'scd_donation_section'
        );

        add_settings_field(
            'submission_method',
            __('Submission Method', 'stripe-custom-donations'),
            array($this, 'submission_method_callback'),
            'stripe-custom-donations-settings',
            'scd_donation_section'
        );

        add_settings_field(
            'donation_page_url',
            __('Donation Page Path', 'stripe-custom-donations'),
            array($this, 'donation_page_url_callback'),
            'stripe-custom-donations-settings',
            'scd_donation_section'
        );

        add_settings_field(
            'statement_descriptor',
            __('Statement Descriptor', 'stripe-custom-donations'),
            array($this, 'statement_descriptor_callback'),
            'stripe-custom-donations-settings',
            'scd_donation_section'
        );

        // Section: Email Configuration
        add_settings_section(
            'scd_email_section',
            __('Email Configuration', 'stripe-custom-donations'),
            array($this, 'email_section_callback'),
            'stripe-custom-donations-settings'
        );

        add_settings_field(
            'email_from_name',
            __('Email From Name', 'stripe-custom-donations'),
            array($this, 'email_from_name_callback'),
            'stripe-custom-donations-settings',
            'scd_email_section'
        );

        add_settings_field(
            'email_from_address',
            __('Email From Address', 'stripe-custom-donations'),
            array($this, 'email_from_address_callback'),
            'stripe-custom-donations-settings',
            'scd_email_section'
        );

        add_settings_field(
            'email_subject',
            __('Email Subject', 'stripe-custom-donations'),
            array($this, 'email_subject_callback'),
            'stripe-custom-donations-settings',
            'scd_email_section'
        );

        add_settings_field(
            'email_template',
            __('Email Template', 'stripe-custom-donations'),
            array($this, 'email_template_callback'),
            'stripe-custom-donations-settings',
            'scd_email_section'
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

        // Sanitize sandbox/test mode settings
        if (isset($input['stripe_mode'])) {
            $sanitized['stripe_mode'] = in_array($input['stripe_mode'], array('live', 'test')) ? $input['stripe_mode'] : 'live';
        }

        if (isset($input['stripe_test_secret_key'])) {
            $sanitized['stripe_test_secret_key'] = sanitize_text_field($input['stripe_test_secret_key']);
        }

        if (isset($input['stripe_test_publishable_key'])) {
            $sanitized['stripe_test_publishable_key'] = sanitize_text_field($input['stripe_test_publishable_key']);
        }

        if (isset($input['stripe_test_webhook_secret'])) {
            $sanitized['stripe_test_webhook_secret'] = sanitize_text_field($input['stripe_test_webhook_secret']);
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

        // Sanitize button label
        if (isset($input['button_label'])) {
            $sanitized['button_label'] = sanitize_text_field($input['button_label']);
        }

        if (isset($input['submission_method'])) {
            $sanitized['submission_method'] = in_array($input['submission_method'], array('ajax', 'rest')) ? $input['submission_method'] : 'ajax';
        }

        if (isset($input['donation_page_url'])) {
            $sanitized['donation_page_url'] = sanitize_text_field($input['donation_page_url']);
        }

        if (isset($input['statement_descriptor'])) {
            $sanitized['statement_descriptor'] = sanitize_text_field($input['statement_descriptor']);
        }

        // Sanitize email settings
        if (isset($input['email_from_name'])) {
            $sanitized['email_from_name'] = sanitize_text_field($input['email_from_name']);
        }

        if (isset($input['email_from_address'])) {
            $sanitized['email_from_address'] = sanitize_email($input['email_from_address']);
        }

        if (isset($input['email_subject'])) {
            $sanitized['email_subject'] = sanitize_text_field($input['email_subject']);
        }

        if (isset($input['email_template'])) {
            $sanitized['email_template'] = wp_kses_post($input['email_template']);
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
            add_settings_error('scd_messages', 'scd_message', __('Settings Saved', 'stripe-custom-donations'), 'updated');
        }

        settings_errors('scd_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php
            $scd_options = get_option($this->option_name);
            if (isset($scd_options['stripe_mode']) && $scd_options['stripe_mode'] === 'test') :
                ?>
                <div class="notice notice-warning">
                    <p><strong><?php _e('WARNING: Test mode is ACTIVE.', 'stripe-custom-donations'); ?></strong>
                    <?php _e('Donations will NOT charge real cards. Switch back to Live mode when ready to accept real payments.', 'stripe-custom-donations'); ?></p>
                </div>
            <?php endif; ?>
            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_group);
                do_settings_sections('stripe-custom-donations-settings');
                submit_button(__('Save Settings', 'stripe-custom-donations'));
                ?>
            </form>

            <hr>
            <h2><?php _e('Shortcode Usage', 'stripe-custom-donations'); ?></h2>
            <p><code>[stripe_donation_form]</code></p>

            <h2><?php _e('Webhook URL', 'stripe-custom-donations'); ?></h2>
            <p><code><?php echo home_url('/wp-json/scd/v1/webhook'); ?></code></p>
            <p><?php _e('Add this URL to your Stripe Dashboard > Webhooks', 'stripe-custom-donations'); ?></p>
        </div>
        <?php
    }

    // Section callbacks
    public function api_section_callback()
    {
        echo '<p>' . __('Enter your Stripe API keys. Keys are stored securely in the database.', 'stripe-custom-donations') . '</p>';
    }

    public function donation_section_callback()
    {
        echo '<p>' . __('Configure donation amounts and recurring intervals.', 'stripe-custom-donations') . '</p>';
    }

    public function test_section_callback()
    {
        echo '<p>' . __('Store separate Stripe test keys and switch between Live and Test mode without touching your live keys.', 'stripe-custom-donations') . '</p>';
    }

    // Field callbacks
    public function secret_key_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['stripe_secret_key']) ? $options['stripe_secret_key'] : '';
        echo '<input type="password" name="' . $this->option_name . '[stripe_secret_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Starts with sk_test_ or sk_live_', 'stripe-custom-donations') . '</p>';
    }

    public function publishable_key_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['stripe_publishable_key']) ? $options['stripe_publishable_key'] : '';
        echo '<input type="text" name="' . $this->option_name . '[stripe_publishable_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Starts with pk_test_ or pk_live_', 'stripe-custom-donations') . '</p>';
    }

    public function webhook_secret_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['stripe_webhook_secret']) ? $options['stripe_webhook_secret'] : '';
        echo '<input type="password" name="' . $this->option_name . '[stripe_webhook_secret]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Starts with whsec_', 'stripe-custom-donations') . '</p>';
    }

    public function stripe_mode_callback()
    {
        $options = get_option($this->option_name);
        $mode = isset($options['stripe_mode']) ? $options['stripe_mode'] : 'live';
        ?>
        <label>
            <input type="radio" name="<?php echo $this->option_name; ?>[stripe_mode]" value="live" <?php checked($mode, 'live'); ?>>
            <?php _e('Live mode (real payments)', 'stripe-custom-donations'); ?>
        </label>
        <br>
        <label>
            <input type="radio" name="<?php echo $this->option_name; ?>[stripe_mode]" value="test" <?php checked($mode, 'test'); ?>>
            <?php _e('Test mode (sandbox testing)', 'stripe-custom-donations'); ?>
        </label>
        <?php
    }

    public function test_secret_key_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['stripe_test_secret_key']) ? $options['stripe_test_secret_key'] : '';
        echo '<input type="password" name="' . $this->option_name . '[stripe_test_secret_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Test key starts with sk_test_', 'stripe-custom-donations') . '</p>';
    }

    public function test_publishable_key_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['stripe_test_publishable_key']) ? $options['stripe_test_publishable_key'] : '';
        echo '<input type="text" name="' . $this->option_name . '[stripe_test_publishable_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Test key starts with pk_test_', 'stripe-custom-donations') . '</p>';
    }

    public function test_webhook_secret_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['stripe_test_webhook_secret']) ? $options['stripe_test_webhook_secret'] : '';
        echo '<input type="password" name="' . $this->option_name . '[stripe_test_webhook_secret]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Test webhook secret starts with whsec_ (from Stripe test webhook endpoint)', 'stripe-custom-donations') . '</p>';
    }

    public function predefined_amounts_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['predefined_amounts']) ? $options['predefined_amounts'] : '10,20,50,100';
        echo '<input type="text" name="' . $this->option_name . '[predefined_amounts]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Comma-separated values (e.g., 10,20,50,100)', 'stripe-custom-donations') . '</p>';
    }

    public function currency_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['currency']) ? $options['currency'] : 'usd';
        echo '<input type="text" name="' . $this->option_name . '[currency]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('3-letter ISO code (e.g., usd)', 'stripe-custom-donations') . '</p>';
    }

    public function recurring_intervals_callback()
    {
        $options = get_option($this->option_name);
        $selected = isset($options['recurring_intervals']) ? $options['recurring_intervals'] : array('monthly', 'quarterly', 'yearly');

        $intervals = array(
            'daily' => __('Daily', 'stripe-custom-donations'),
            'weekly' => __('Weekly', 'stripe-custom-donations'),
            'monthly' => __('Monthly', 'stripe-custom-donations'),
            'quarterly' => __('Quarterly (Every 3 months)', 'stripe-custom-donations'),
            'yearly' => __('Yearly', 'stripe-custom-donations')
        );

        foreach ($intervals as $key => $label) {
            $checked = in_array($key, $selected) ? 'checked' : '';
            echo '<label><input type="checkbox" name="' . $this->option_name . '[recurring_intervals][]" value="' . esc_attr($key) . '" ' . $checked . '> ' . esc_html($label) . '</label><br>';
        }
    }

    public function button_label_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['button_label']) ? $options['button_label'] : 'Donate';
        echo '<input type="text" name="' . $this->option_name . '[button_label]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('It will be text of the button', 'stripe-custom-donations') . '</p>';
    }

    public function submission_method_callback()
    {
        $options = get_option($this->option_name);
        $method = isset($options['submission_method']) ? $options['submission_method'] : 'ajax';
        ?>
                <select name="<?php echo $this->option_name; ?>[submission_method]">
                    <option value="ajax" <?php selected($method, 'ajax'); ?>>AJAX (admin-ajax.php)</option>
                    <option value="rest" <?php selected($method, 'rest'); ?>>REST API (/wp-json/)</option>
                </select>
                <p class="description"><?php _e('Use REST API if admin-ajax.php returns 403 for non-logged-in users', 'stripe-custom-donations'); ?></p>
        <?php
    }

    public function donation_page_url_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['donation_page_url']) ? $options['donation_page_url'] : '/support-us';
        echo '<input type="text" name="' . $this->option_name . '[donation_page_url]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Path of the page containing the donation form (used for Stripe redirects), e.g. /support-us', 'stripe-custom-donations') . '</p>';
    }

    public function statement_descriptor_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['statement_descriptor']) ? $options['statement_descriptor'] : '';
        echo '<input type="text" name="' . $this->option_name . '[statement_descriptor]" value="' . esc_attr($value) . '" class="regular-text" maxlength="22" />';
        echo '<p class="description">' . __('Shown on the donor\'s bank statement. Max 22 characters, letters/numbers/spaces only. Leave empty to use your site title.', 'stripe-custom-donations') . '</p>';
    }

    // Email section callback
    public function email_section_callback()
    {
        echo '<p>' . __('Configure email confirmation settings. Use placeholders for dynamic content.', 'stripe-custom-donations') . '</p>';
        echo '<p><strong>' . __('Available Placeholders:', 'stripe-custom-donations') . '</strong></p>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        echo '<li><code>{amount}</code> - ' . __('Donation amount with currency symbol', 'stripe-custom-donations') . '</li>';
        echo '<li><code>{email}</code> - ' . __('Donor email address', 'stripe-custom-donations') . '</li>';
        echo '<li><code>{payment_type}</code> - ' . __('One-time or Recurring', 'stripe-custom-donations') . '</li>';
        echo '<li><code>{interval}</code> - ' . __('Billing interval (for recurring donations)', 'stripe-custom-donations') . '</li>';
        echo '<li><code>{date}</code> - ' . __('Current date', 'stripe-custom-donations') . '</li>';
        echo '</ul>';
    }

    public function email_from_name_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['email_from_name']) ? $options['email_from_name'] : get_bloginfo('name');
        echo '<input type="text" name="' . $this->option_name . '[email_from_name]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Name that appears in donor\'s inbox', 'stripe-custom-donations') . '</p>';
    }

    public function email_from_address_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['email_from_address']) ? $options['email_from_address'] : get_bloginfo('admin_email');
        echo '<input type="email" name="' . $this->option_name . '[email_from_address]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Email address that appears as sender', 'stripe-custom-donations') . '</p>';
    }

    public function email_subject_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['email_subject']) ? $options['email_subject'] : 'Thank you for your contribution';
        echo '<input type="text" name="' . $this->option_name . '[email_subject]" value="' . esc_attr($value) . '" class="large-text" />';
        echo '<p class="description">' . __('Email subject line (placeholders supported)', 'stripe-custom-donations') . '</p>';
    }

    public function email_template_callback()
    {
        $options = get_option($this->option_name);
        $default_template = "Thank you for your contribution of {amount}. We deeply appreciate your support. A receipt will be emailed to you soon. If you have any questions, please reply to this email.";

        $value = isset($options['email_template']) ? $options['email_template'] : $default_template;

        echo '<textarea name="' . $this->option_name . '[email_template]" rows="8" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Email message body. Use placeholders like {amount}, {email}, {payment_type}, {interval}, {date}', 'stripe-custom-donations') . '</p>';
    }
}