=== Stripe Custom Donations ===
Contributors: iamakram22
Tags: stripe, donations, donation form, recurring donations, payment gateway, donate, fundraising, checkout, webhook
Requires at least: 6.0
Tested up to: 7.0.2
Requires PHP: 7.4
Stable tag: 3.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept one-time and recurring donations via Stripe Checkout with custom amounts, sandbox testing, and webhook-based confirmation.

== Description ==

Stripe Custom Donations lets you accept donations directly through [Stripe Checkout](https://stripe.com/checkout), the PCI-compliant hosted payment page. Donations go straight to your Stripe account - no transaction fees beyond Stripe's own.

= Key Features =

* **One-time & recurring donations** - donors choose either payment type
* **Custom amount input** - donors enter any amount they want
* **Predefined amount buttons** - quick-select popular donation amounts
* **Flexible billing intervals** - daily, weekly, monthly, quarterly, or yearly
* **Sandbox / Test mode** - store separate test keys and switch live/test anytime without touching your live keys
* **Email confirmations** - automatic thank-you emails with dynamic placeholders
* **Donation tracking** - every session logged in the database
* **Webhook support** - real-time payment confirmation via Stripe webhooks
* **AJAX or REST API submission** - choose between `admin-ajax.php` and the WordPress REST API if your host blocks AJAX for visitors
* **Multi-currency** - works with any Stripe-supported currency

= How it works =

The plugin renders a shortcode form. When a donor submits it, a Stripe Checkout Session is created and the donor is redirected to Stripe's hosted payment page. Stripe sends a webhook back to your site to confirm the payment, mark the donation completed, and send the thank-you email.

This plugin is an interface to the [Stripe](https://stripe.com) payment service. By configuring it with your Stripe API keys you consent to sending donation data (donor email and amount) to Stripe for processing. Review Stripe's [Terms of Service](https://stripe.com/legal/ssa) and [Privacy Policy](https://stripe.com/privacy) before use.

= Live vs Test mode =

* **Live mode** - processes real payments with your `sk_live_` keys
* **Test mode** - processes test payments with `sk_test_` keys (use test card `4242 4242 4242 4242`)

Your live keys are stored separately and are never touched when you switch modes. A warning banner is shown in settings while test mode is active.

== Installation ==

1. Upload the `stripe-custom-donations` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Settings → Stripe Donations** and enter your Stripe Secret Key, Publishable Key, and Webhook Secret (from [Stripe Dashboard → Developers](https://dashboard.stripe.com/developers))
4. Add the `[stripe_donation_form]` shortcode to any page

= Webhook setup =

1. In Stripe Dashboard, go to **Developers → Webhooks** and click **Add endpoint**
2. Enter your webhook URL: `https://yoursite.com/wp-json/scd/v1/webhook`
3. Subscribe to these events:
    * `checkout.session.completed`
    * `invoice.payment_succeeded`
    * `customer.subscription.deleted`
4. Copy the signing secret (`whsec_...`) and paste it in the plugin settings
5. If using test mode, create a second webhook endpoint in Stripe's test mode and paste its secret in the test webhook field

= Composer note =

The Stripe PHP SDK is bundled with the plugin, so no Composer installation is required.

== Frequently Asked Questions ==

= Is a Stripe account required? =

Yes. The plugin works with any Stripe account. Free plan is sufficient for donations.

= Are there any transaction fees? =

The plugin itself is free. Payments go directly to your Stripe account and are subject only to Stripe's standard processing fees.

= Can I use it without HTTPS? =

Stripe Checkout works on HTTP, but a valid SSL certificate is strongly recommended and required for many Stripe features.

= How do I change where donors return after payment? =

Set the **Donation Page Path** in Settings → Stripe Donations. This is the path of the page containing the donation form (default: `/support-us`).

= Which payment methods are supported? =

Cards through Stripe Checkout. Stripe handles the full payment experience.

== Privacy ==

This plugin does not collect, store, or transmit any personal data on its own. Donation information (donor email address and amount) is sent to Stripe, the payment processor, when creating a checkout session. Data is stored in your site's database (`stripe_donations` table) for donation tracking purposes.

== Changelog ==

= 3.1.0 =
* Added Sandbox / Test mode with separate test keys and a live/test toggle
* Added warning banner when test mode is active
* Added donation page path setting for Stripe redirect URLs
* General cleanup for WordPress.org compliance

= 3.0.0 =
* Added REST API submission method to work around `admin-ajax.php` 403 errors for non-logged-in users
* Added email template support with dynamic placeholders

== Upgrade Notice ==

= 3.1.0 =
New installs default to AJAX submission. If your host blocks `admin-ajax.php` for visitors, switch **Submission Method** to REST API in settings.
