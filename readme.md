# Stripe Custom Donations - WordPress Plugin

A powerful, lightweight WordPress plugin for accepting one-time and recurring donations via Stripe with custom amount support.

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/wordpress-6.0%2B-green.svg)
![PHP](https://img.shields.io/badge/php-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0-red.svg)

## ✨ Features

- ✅ **One-time & Recurring Donations** - Support both payment types seamlessly
- 💰 **Custom Amount Input** - Let donors choose their own donation amount
- 🎯 **Predefined Amount Buttons** - Quick-select popular donation amounts
- 🔄 **Flexible Billing Intervals** - Daily, Weekly, Monthly, Quarterly, and Yearly options
- 💳 **Direct Stripe Integration** - Zero transaction fees (payments go directly to your Stripe account)
- 🔒 **Secure Storage** - API keys stored securely using WordPress Settings API
- 📧 **Email Confirmations** - Automatic thank you emails to donors
- 📊 **Donation Tracking** - Database logging of all donations
- 🎨 **Responsive Design** - Mobile-friendly donation forms
- 🌍 **Multi-currency Support** - Accept donations in any Stripe-supported currency
- ⚙️ **Easy Configuration** - User-friendly admin settings page
- 🪝 **Webhook Support** - Real-time payment confirmation via Stripe webhooks

## 📋 Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- Composer (for dependency management)
- Stripe Account (test or live mode)
- SSL Certificate (required for production)

## 🚀 Installation

### Step 1: Upload Plugin Files

1. Download or clone this repository
2. Upload the `stripe-custom-donations` folder to `/wp-content/plugins/`

### Step 2: Install Dependencies

Navigate to the plugin directory and run:

```bash
cd wp-content/plugins/stripe-custom-donations
composer install --no-dev
```

This will install the Stripe PHP SDK (v19.1).

### Step 3: Activate Plugin

1. Go to WordPress Admin → **Plugins**
2. Find "Stripe Custom Donations"
3. Click **Activate**

## ⚙️ Configuration

### 1. Configure Plugin Settings

Navigate to **Settings → Stripe Donations** in WordPress admin.

#### Stripe API Configuration

| Setting | Description | Format |
| :-- | :-- | :-- |
| **Secret Key** | Your Stripe secret key | Starts with `sk_test_` or `sk_live_` |
| **Publishable Key** | Your Stripe publishable key | Starts with `pk_test_` or `pk_live_` |
| **Webhook Secret** | Stripe webhook signing secret | Starts with `whsec_` |

**Where to find your Stripe keys:**

- Go to [Stripe Dashboard](https://dashboard.stripe.com/)
- Navigate to **Developers → API Keys**
- Copy your keys (use test keys for testing)


#### Donation Configuration

| Setting | Description | Example |
| :-- | :-- | :-- |
| **Predefined Amounts** | Comma-separated donation amounts | `10,20,50,100` |
| **Currency** | 3-letter ISO currency code | `usd`, `inr`, `eur` |
| **Recurring Intervals** | Check boxes for enabled intervals | Daily, Weekly, Monthly, Quarterly, or Yearly |
| **Button Label** | Custom text for submit button | "Proceed to Payment" or whatever set in settings page |

### 2. Set Up Stripe Webhook

Webhooks are required for payment confirmation and recurring donation tracking.

1. Go to [Stripe Dashboard → Webhooks](https://dashboard.stripe.com/webhooks)
2. Click **Add endpoint**
3. Enter your webhook URL:

```
https://yoursite.com/wp-json/scd/v1/webhook
```

4. Select events to listen to:
    - `checkout.session.completed`
    - `invoice.payment_succeeded`
    - `customer.subscription.deleted`
5. Copy the **Signing secret** (starts with `whsec_`)
6. Paste it in **Settings → Stripe Donations → Webhook Secret**

### 3. Add Donation Form to Pages

Use the shortcode to display the donation form anywhere:

```
[stripe_donation_form]
```

**Example Usage:**

- Add to a page: Create/edit any page and insert `[stripe_donation_form]`
- Add to a post: Insert shortcode in post content
- Add to sidebar: Use a text/HTML widget with the shortcode
- Add to theme: Use `<?php echo do_shortcode('[stripe_donation_form]'); ?>`


## 📁 File Structure

```
stripe-custom-donations/
├── stripe-custom-donations.php    # Main plugin file
├── composer.json                   # Composer dependencies
├── README.md                       # This file
├── includes/
│   ├── class-settings.php         # Admin settings page
│   ├── class-donation-handler.php # Donation form & Stripe integration
│   └── class-webhook-handler.php  # Webhook processing
├── assets/
│   ├── css/
│   │   └── donation-form.css      # Form styling
│   └── js/
│       └── donation-form.js       # Form interactions
└── vendor/                         # Stripe PHP SDK (via Composer)
```


## 💡 Usage Guide

### For Donors

1. Visit page with donation form
2. Choose a predefined amount or enter custom amount
3. Enter email address
4. Select **One-time** or **Recurring** donation
5. If recurring, choose frequency (Daily/Weekly/Monthly/Quarterly/Yearly)
6. Click **Proceed to Payment**
7. Complete payment on Stripe Checkout page
8. Receive confirmation email

### For Administrators

#### View Donations

Donations are stored in the database table `wp_stripe_donations`:

```sql
SELECT * FROM wp_stripe_donations ORDER BY created_at DESC;
```

**Database Fields:**

- `stripe_session_id` - Unique Stripe session ID
- `stripe_subscription_id` - For recurring donations
- `customer_email` - Donor's email
- `amount` - Donation amount
- `currency` - Currency code
- `payment_type` - `one_time` or `recurring`
- `billing_interval` - `daily`, `weekly`, `monthly`, `quarterly`, `yearly`, etc.
- `status` - `pending`, `completed`, `cancelled`
- `created_at` - Timestamp


#### Export Donations

You can query and export donation data using phpMyAdmin or database plugins.

## 🎨 Customization

### Custom Styling

Override default styles by adding CSS to your theme:

```css
/* Override root vars */
:root {
    --scd-accent: #0073aa;
    --scd-accent-hover: #005177;
    --scd-border: #ddd;
    --scd-error: #f44336;
}

/* Custom donation form styling */
.scd-donation-form {
    background: #ffffff;
    border: 2px solid var(--scd-accent);
}

.scd-amount-btn.active {
    background: var(--scd-error);
    border-color: var(--scd-error);
}
```


### Modify Form Layout

Edit `assets/css/donation-form.css` for layout changes.

### Change Button Text

Use the **Donation Button Label** setting in admin panel.

## 🔧 Development

### Local Development Setup

```bash
# Clone repository
git clone https://github.com/iamakram22/stripe-custom-donations.git

# Install dependencies
cd stripe-custom-donations
composer install

# Enable WordPress debug mode in wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```


### Testing

1. Use Stripe **test mode** keys for development
2. Test cards: [Stripe Testing Cards](https://stripe.com/docs/testing)
    - Success: `4242 4242 4242 4242`
    - Decline: `4000 0000 0000 0002`

### Webhook Testing

Use Stripe CLI for local webhook testing:

```bash
stripe listen --forward-to https://yoursite.local/wp-json/scd/v1/webhook
```


## 🐛 Troubleshooting

### Common Issues

**Problem: "Stripe is not configured" error**

- Solution: Verify API keys are entered correctly in settings
- Check that keys start with correct prefix (`sk_test_` or `sk_live_`)

**Problem: Webhooks not working**

- Solution: Ensure webhook URL is correct and accessible
- Verify webhook secret matches Stripe dashboard
- Check WordPress REST API is enabled

**Problem: Composer dependencies missing**

- Solution: Run `composer install` in plugin directory
- Ensure PHP has write permissions to create `vendor/` folder

**Problem: Form not displaying**

- Solution: Check shortcode is correct: `[stripe_donation_form]`
- Verify plugin is activated
- Check for JavaScript errors in browser console

**Problem: "Minimum donation amount is 50" error**

- Solution: Stripe has minimum charge amounts per currency
- USD: \$0.50, INR: ₹50, EUR: €0.50


### Debug Mode

Enable WordPress debug logging:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs at: `wp-content/debug.log`

## 🔒 Security Best Practices

1. **Always use HTTPS** in production
2. **Never commit API keys** to version control
3. **Use test keys** during development
4. **Keep WordPress and PHP updated**
5. **Regularly update Stripe SDK**: `composer update stripe/stripe-php`
6. **Validate webhook signatures** (already implemented)
7. **Sanitize all user inputs** (already implemented)

## 📝 Changelog

### Version 1.0.0 (January 2026)

- Initial release
- One-time and recurring donation support
- Custom amount input
- Predefined amount buttons
- Multi-interval recurring options (daily, weekly, monthly, quarterly, yearly)
- Stripe webhook integration
- Email confirmations
- Database donation tracking
- Admin settings page with secure key storage


## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature-name`
3. Commit changes: `git commit -am 'Add new feature'`
4. Push to branch: `git push origin feature-name`
5. Submit a pull request

### Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Use proper sanitization and escaping
- Add inline documentation for functions
- Test thoroughly before submitting PR


## 📄 License

This project is licensed under the GPL-2.0 License - see WordPress.org for details.

## 👨‍💻 Author

**Mohd Akram**
- Email: [info@iamakram.com](mailto:info@iamakram.com)
- GitHub: [@iamakram22](https://github.com/iamakram22)


## 🙏 Credits

- [Stripe PHP SDK](https://github.com/stripe/stripe-php)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)


## 📧 Support

For support, please:

1. Check the [Troubleshooting](#-troubleshooting) section
2. Search existing [GitHub Issues](https://github.com/yourusername/stripe-custom-donations/issues)
3. Open a new issue with detailed information

## 🔗 Links

- [Stripe Documentation](https://stripe.com/docs)
- [Stripe Dashboard](https://dashboard.stripe.com/)
- [WordPress Plugin Development](https://developer.wordpress.org/plugins/)
- [Composer](https://getcomposer.org/)

---

**Made with ❤️ for the WordPress community**

If you find this plugin helpful, please consider:

- ⭐ Starring the repository
- 🐛 Reporting bugs
- 💡 Suggesting features
- 🤝 Contributing code