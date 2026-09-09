<?php
/**
 * Settings page for Stripe configuration
 * Uses WordPress Settings API for secure storage
 */

if (!defined('ABSPATH'))
    exit;

class FDFS_Settings
{

    private $option_group = 'fdfs_settings_group';
    private $option_name = 'fdfs_stripe_settings';

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
            __('Flexible Donations Settings', 'flexible-donations-for-stripe'),
            __('Flexible Donations', 'flexible-donations-for-stripe'),
            'manage_options',
            'fdfs-settings',
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
            'fdfs_api_section',
            __('Stripe API Configuration', 'flexible-donations-for-stripe'),
            array($this, 'api_section_callback'),
            'fdfs-settings'
        );

        add_settings_field(
            'stripe_secret_key',
            __('Secret Key', 'flexible-donations-for-stripe'),
            array($this, 'secret_key_callback'),
            'fdfs-settings',
            'fdfs_api_section'
        );

        add_settings_field(
            'stripe_publishable_key',
            __('Publishable Key', 'flexible-donations-for-stripe'),
            array($this, 'publishable_key_callback'),
            'fdfs-settings',
            'fdfs_api_section'
        );

        add_settings_field(
            'stripe_webhook_secret',
            __('Webhook Secret', 'flexible-donations-for-stripe'),
            array($this, 'webhook_secret_callback'),
            'fdfs-settings',
            'fdfs_api_section'
        );

        // Section: Sandbox / Test Mode
        add_settings_section(
            'fdfs_test_section',
            __('Sandbox / Test Mode', 'flexible-donations-for-stripe'),
            array($this, 'test_section_callback'),
            'fdfs-settings'
        );

        add_settings_field(
            'stripe_mode',
            __('Mode', 'flexible-donations-for-stripe'),
            array($this, 'stripe_mode_callback'),
            'fdfs-settings',
            'fdfs_test_section'
        );

        add_settings_field(
            'stripe_test_secret_key',
            __('Test Secret Key', 'flexible-donations-for-stripe'),
            array($this, 'test_secret_key_callback'),
            'fdfs-settings',
            'fdfs_test_section'
        );

        add_settings_field(
            'stripe_test_publishable_key',
            __('Test Publishable Key', 'flexible-donations-for-stripe'),
            array($this, 'test_publishable_key_callback'),
            'fdfs-settings',
            'fdfs_test_section'
        );

        add_settings_field(
            'stripe_test_webhook_secret',
            __('Test Webhook Secret', 'flexible-donations-for-stripe'),
            array($this, 'test_webhook_secret_callback'),
            'fdfs-settings',
            'fdfs_test_section'
        );

        // Section: Donation Configuration
        add_settings_section(
            'fdfs_donation_section',
            __('Donation Configuration', 'flexible-donations-for-stripe'),
            array($this, 'donation_section_callback'),
            'fdfs-settings'
        );

        add_settings_field(
            'predefined_amounts',
            __('Predefined Amounts', 'flexible-donations-for-stripe'),
            array($this, 'predefined_amounts_callback'),
            'fdfs-settings',
            'fdfs_donation_section'
        );

        add_settings_field(
            'currency',
            __('Currency', 'flexible-donations-for-stripe'),
            array($this, 'currency_callback'),
            'fdfs-settings',
            'fdfs_donation_section'
        );

        add_settings_field(
            'recurring_intervals',
            __('Recurring Intervals', 'flexible-donations-for-stripe'),
            array($this, 'recurring_intervals_callback'),
            'fdfs-settings',
            'fdfs_donation_section'
        );

        add_settings_field(
            'button_label',
            __('Donation Button Label', 'flexible-donations-for-stripe'),
            array($this, 'button_label_callback'),
            'fdfs-settings',
            'fdfs_donation_section'
        );

        add_settings_field(
            'enable_note_field',
            __('Donation Note Field', 'flexible-donations-for-stripe'),
            array($this, 'enable_note_field_callback'),
            'fdfs-settings',
            'fdfs_donation_section'
        );

        add_settings_field(
            'submission_method',
            __('Submission Method', 'flexible-donations-for-stripe'),
            array($this, 'submission_method_callback'),
            'fdfs-settings',
            'fdfs_donation_section'
        );

        add_settings_field(
            'donation_page_url',
            __('Donation Page Path', 'flexible-donations-for-stripe'),
            array($this, 'donation_page_url_callback'),
            'fdfs-settings',
            'fdfs_donation_section'
        );

        add_settings_field(
            'statement_descriptor',
            __('Statement Descriptor', 'flexible-donations-for-stripe'),
            array($this, 'statement_descriptor_callback'),
            'fdfs-settings',
            'fdfs_donation_section'
        );

        // Section: Email Configuration
        add_settings_section(
            'fdfs_email_section',
            __('Email Configuration', 'flexible-donations-for-stripe'),
            array($this, 'email_section_callback'),
            'fdfs-settings'
        );

        add_settings_field(
            'email_from_name',
            __('Email From Name', 'flexible-donations-for-stripe'),
            array($this, 'email_from_name_callback'),
            'fdfs-settings',
            'fdfs_email_section'
        );

        add_settings_field(
            'email_from_address',
            __('Email From Address', 'flexible-donations-for-stripe'),
            array($this, 'email_from_address_callback'),
            'fdfs-settings',
            'fdfs_email_section'
        );

        add_settings_field(
            'email_subject',
            __('Email Subject', 'flexible-donations-for-stripe'),
            array($this, 'email_subject_callback'),
            'fdfs-settings',
            'fdfs_email_section'
        );

        add_settings_field(
            'email_template',
            __('Email Template', 'flexible-donations-for-stripe'),
            array($this, 'email_template_callback'),
            'fdfs-settings',
            'fdfs_email_section'
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

        $sanitized['enable_note_field'] = !empty($input['enable_note_field']) ? 1 : 0;

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
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- settings API redirect param
        if (isset($_GET['settings-updated'])) {
            add_settings_error('fdfs_messages', 'fdfs_message', __('Settings Saved', 'flexible-donations-for-stripe'), 'updated');
        }

        settings_errors('fdfs_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php
            $fdfs_options = get_option($this->option_name);
            if (isset($fdfs_options['stripe_mode']) && $fdfs_options['stripe_mode'] === 'test') :
                ?>
                <div class="notice notice-warning">
                    <p><strong><?php esc_html_e('WARNING: Test mode is ACTIVE.', 'flexible-donations-for-stripe'); ?></strong>
                        <?php esc_html_e('Donations will NOT charge real cards. Switch back to Live mode when ready to accept real payments.', 'flexible-donations-for-stripe'); ?>
                    </p>
                </div>
            <?php endif; ?>
            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_group);
                do_settings_sections('fdfs-settings');
                submit_button(__('Save Settings', 'flexible-donations-for-stripe'));
                ?>
            </form>

            <hr>
            <h2><?php esc_html_e('Webhook Configuration', 'flexible-donations-for-stripe'); ?></h2>
            <p><strong><?php esc_html_e('Webhook URL:', 'flexible-donations-for-stripe'); ?></strong>
                <code><?php echo esc_url(home_url('/wp-json/fdfs/v1/webhook')); ?></code></p>

            <p><?php echo wp_kses_post(__('In your Stripe Dashboard, go to <strong>Developers &rarr; Webhooks</strong> and click <strong>Add endpoint</strong>.', 'flexible-donations-for-stripe')); ?>
            </p>

            <p><?php esc_html_e('Required events to subscribe:', 'flexible-donations-for-stripe'); ?></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><code>checkout.session.completed</code> -
                    <?php esc_html_e('Confirms one-time donations and starts recurring subscriptions', 'flexible-donations-for-stripe'); ?>
                </li>
                <li><code>invoice.payment_succeeded</code> -
                    <?php esc_html_e('Confirms recurring payment renewals', 'flexible-donations-for-stripe'); ?></li>
                <li><code>customer.subscription.deleted</code> -
                    <?php esc_html_e('Marks subscription as cancelled when donor cancels', 'flexible-donations-for-stripe'); ?>
                </li>
            </ul>

            <p><?php echo wp_kses_post(__('After adding the endpoint, copy the <strong>Signing Secret</strong> (starts with <code>whsec_</code>) and paste it in the <strong>Webhook Secret</strong> field in the Stripe API Configuration section above.', 'flexible-donations-for-stripe')); ?>
            </p>

            <p><strong><?php esc_html_e('Test Mode:', 'flexible-donations-for-stripe'); ?></strong>
                <?php echo wp_kses_post(__('If using Test mode, create a separate webhook endpoint in Stripe\'s test mode (toggle at top of Dashboard) and paste its signing secret in the <strong>Test Webhook Secret</strong> field in the Sandbox section.', 'flexible-donations-for-stripe')); ?>
            </p>

            <h2><?php esc_html_e('Shortcode Usage', 'flexible-donations-for-stripe'); ?></h2>
            <p><code>[fdfs_donation_form]</code></p>
        </div>
        <?php
    }

    // Section callbacks
    public function api_section_callback()
    {
        ?>
        <p><?php esc_html_e('Enter your Stripe API keys. Keys are stored securely in the database.', 'flexible-donations-for-stripe'); ?>
        </p>
        <?php
    }

    public function donation_section_callback()
    {
        echo '<p>' . esc_html__('Configure donation amounts and recurring intervals.', 'flexible-donations-for-stripe') . '</p>';
    }

    public function test_section_callback()
    {
        echo '<p>' . esc_html__('Store separate Stripe test keys and switch between Live and Test mode without touching your live keys.', 'flexible-donations-for-stripe') . '</p>';
    }

    // Field callbacks
    public function secret_key_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['stripe_secret_key']) ? $options['stripe_secret_key'] : '';
        echo '<input type="password" name="' . esc_attr($this->option_name) . '[stripe_secret_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Starts with sk_test_ or sk_live_', 'flexible-donations-for-stripe') . '</p>';
    }

    public function publishable_key_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['stripe_publishable_key']) ? $options['stripe_publishable_key'] : '';
        echo '<input type="text" name="' . esc_attr($this->option_name) . '[stripe_publishable_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Starts with pk_test_ or pk_live_', 'flexible-donations-for-stripe') . '</p>';
    }

    public function webhook_secret_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['stripe_webhook_secret']) ? $options['stripe_webhook_secret'] : '';
        echo '<input type="password" name="' . esc_attr($this->option_name) . '[stripe_webhook_secret]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Starts with whsec_', 'flexible-donations-for-stripe') . '</p>';
    }

    public function stripe_mode_callback()
    {
        $options = get_option($this->option_name);
        $mode = isset($options['stripe_mode']) ? $options['stripe_mode'] : 'live';
        ?>
        <label>
            <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[stripe_mode]" value="live" <?php checked($mode, 'live'); ?>>
            <?php esc_html_e('Live mode (real payments)', 'flexible-donations-for-stripe'); ?>
        </label>
        <br>
        <label>
            <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[stripe_mode]" value="test" <?php checked($mode, 'test'); ?>>
            <?php esc_html_e('Test mode (sandbox testing)', 'flexible-donations-for-stripe'); ?>
        </label>
        <?php
    }

    public function test_secret_key_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['stripe_test_secret_key']) ? $options['stripe_test_secret_key'] : '';
        echo '<input type="password" name="' . esc_attr($this->option_name) . '[stripe_test_secret_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Test key starts with sk_test_', 'flexible-donations-for-stripe') . '</p>';
    }

    public function test_publishable_key_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['stripe_test_publishable_key']) ? $options['stripe_test_publishable_key'] : '';
        echo '<input type="text" name="' . esc_attr($this->option_name) . '[stripe_test_publishable_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Test key starts with pk_test_', 'flexible-donations-for-stripe') . '</p>';
    }

    public function test_webhook_secret_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['stripe_test_webhook_secret']) ? $options['stripe_test_webhook_secret'] : '';
        echo '<input type="password" name="' . esc_attr($this->option_name) . '[stripe_test_webhook_secret]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Test webhook secret starts with whsec_ (from Stripe test webhook endpoint)', 'flexible-donations-for-stripe') . '</p>';
    }

    public function predefined_amounts_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['predefined_amounts']) ? $options['predefined_amounts'] : '10,20,50,100';
        echo '<input type="text" name="' . esc_attr($this->option_name) . '[predefined_amounts]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Comma-separated values (e.g., 10,20,50,100)', 'flexible-donations-for-stripe') . '</p>';
    }

    public function currency_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['currency']) ? $options['currency'] : 'usd';
        echo '<input type="text" name="' . esc_attr($this->option_name) . '[currency]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('3-letter ISO code (e.g., usd)', 'flexible-donations-for-stripe') . '</p>';
    }

    public function recurring_intervals_callback()
    {
        $options = get_option($this->option_name);
        $selected = isset($options['recurring_intervals']) ? $options['recurring_intervals'] : array('monthly', 'quarterly', 'yearly');

        $intervals = array(
            'daily' => __('Daily', 'flexible-donations-for-stripe'),
            'weekly' => __('Weekly', 'flexible-donations-for-stripe'),
            'monthly' => __('Monthly', 'flexible-donations-for-stripe'),
            'quarterly' => __('Quarterly (Every 3 months)', 'flexible-donations-for-stripe'),
            'yearly' => __('Yearly', 'flexible-donations-for-stripe')
        );

        foreach ($intervals as $key => $label) {
            echo '<label><input type="checkbox" name="' . esc_attr($this->option_name) . '[recurring_intervals][]" value="' . esc_attr($key) . '" ' . checked(in_array($key, $selected), true, false) . '> ' . esc_html($label) . '</label><br>';
        }
    }

    public function button_label_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['button_label']) ? $options['button_label'] : 'Donate';
        echo '<input type="text" name="' . esc_attr($this->option_name) . '[button_label]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('It will be text of the button', 'flexible-donations-for-stripe') . '</p>';
    }

    public function enable_note_field_callback()
    {
        $options = get_option($this->option_name);
        $enabled = isset($options['enable_note_field']) ? (int) $options['enable_note_field'] : 1;
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_note_field]" value="1" <?php checked($enabled, 1); ?>>
            <?php esc_html_e('Show an optional note field on the donation form. The note is stored with the donation in Stripe and your database.', 'flexible-donations-for-stripe'); ?>
        </label>
        <?php
    }

    public function submission_method_callback()
    {
        $options = get_option($this->option_name);
        $method = isset($options['submission_method']) ? $options['submission_method'] : 'ajax';
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[submission_method]">
            <option value="ajax" <?php selected($method, 'ajax'); ?>>AJAX (admin-ajax.php)</option>
            <option value="rest" <?php selected($method, 'rest'); ?>>REST API (/wp-json/)</option>
        </select>
        <p class="description">
            <?php esc_html_e('Use REST API if admin-ajax.php returns 403 for non-logged-in users', 'flexible-donations-for-stripe'); ?>
        </p>
        <?php
    }

    public function donation_page_url_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['donation_page_url']) ? $options['donation_page_url'] : '/support-us';
        echo '<input type="text" name="' . esc_attr($this->option_name) . '[donation_page_url]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Path of the page containing the donation form (used for Stripe redirects), e.g. /support-us', 'flexible-donations-for-stripe') . '</p>';
    }

    public function statement_descriptor_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['statement_descriptor']) ? $options['statement_descriptor'] : '';
        echo '<input type="text" name="' . esc_attr($this->option_name) . '[statement_descriptor]" value="' . esc_attr($value) . '" class="regular-text" maxlength="22" />';
        echo '<p class="description">' . esc_html__('Shown on the donor\'s bank statement. Max 22 characters, letters/numbers/spaces only. Leave empty to use your site title.', 'flexible-donations-for-stripe') . '</p>';
    }

    // Email section callback
    public function email_section_callback()
    {
        echo '<p>' . esc_html__('Configure email confirmation settings. Use placeholders for dynamic content.', 'flexible-donations-for-stripe') . '</p>';
        echo '<p><strong>' . esc_html__('Available Placeholders:', 'flexible-donations-for-stripe') . '</strong></p>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        echo '<li><code>{amount}</code> - ' . esc_html__('Donation amount with currency symbol', 'flexible-donations-for-stripe') . '</li>';
        echo '<li><code>{email}</code> - ' . esc_html__('Donor email address', 'flexible-donations-for-stripe') . '</li>';
        echo '<li><code>{payment_type}</code> - ' . esc_html__('One-time or Recurring', 'flexible-donations-for-stripe') . '</li>';
        echo '<li><code>{interval}</code> - ' . esc_html__('Billing interval (for recurring donations)', 'flexible-donations-for-stripe') . '</li>';
        echo '<li><code>{date}</code> - ' . esc_html__('Current date', 'flexible-donations-for-stripe') . '</li>';
        echo '</ul>';
    }

    public function email_from_name_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['email_from_name']) ? $options['email_from_name'] : get_bloginfo('name');
        echo '<input type="text" name="' . esc_attr($this->option_name) . '[email_from_name]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Name that appears in donor\'s inbox', 'flexible-donations-for-stripe') . '</p>';
    }

    public function email_from_address_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['email_from_address']) ? $options['email_from_address'] : get_bloginfo('admin_email');
        echo '<input type="email" name="' . esc_attr($this->option_name) . '[email_from_address]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Email address that appears as sender', 'flexible-donations-for-stripe') . '</p>';
    }

    public function email_subject_callback()
    {
        $options = get_option($this->option_name);
        $value = isset($options['email_subject']) ? $options['email_subject'] : 'Thank you for your contribution';
        echo '<input type="text" name="' . esc_attr($this->option_name) . '[email_subject]" value="' . esc_attr($value) . '" class="large-text" />';
        echo '<p class="description">' . esc_html__('Email subject line (placeholders supported)', 'flexible-donations-for-stripe') . '</p>';
    }

    public function email_template_callback()
    {
        $options = get_option($this->option_name);
        $default_template = "Thank you for your contribution of {amount}. We deeply appreciate your support. A receipt will be emailed to you soon. If you have any questions, please reply to this email.";

        $value = isset($options['email_template']) ? $options['email_template'] : $default_template;

        echo '<textarea name="' . esc_attr($this->option_name) . '[email_template]" rows="8" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('Email message body. Use placeholders like {amount}, {email}, {payment_type}, {interval}, {date}', 'flexible-donations-for-stripe') . '</p>';
    }
}