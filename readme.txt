=== OrcusPay for WooCommerce ===
Contributors: orcushq
Tags: woocommerce, payment-gateway, orcuspay, checkout, payments
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.2.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: woocommerce

Accept payments on WooCommerce with OrcusPay Checkout.

== Description ==

OrcusPay for WooCommerce redirects customers to OrcusPay Checkout, verifies completed payments with OrcusPay, and marks WooCommerce orders paid after secure webhook confirmation.

= Main features =
- Hosted OrcusPay Checkout redirect
- API key and secret authentication
- Svix-compatible webhook signature verification without extra Composer install
- Payment amount verification before completing WooCommerce orders
- Idempotent payment handling to avoid double-crediting paid orders
- WooCommerce HPOS compatibility declaration
- Compatible with the current WordPress and WooCommerce releases

= Setup =
1. Upload the plugin to WordPress and activate it.
2. Go to WooCommerce > Settings > Payments > OrcusPay.
3. Add your API key, API secret, and webhook secret from the OrcusPay dashboard.
4. Copy the Webhook URL shown in WooCommerce and add it to OrcusPay webhooks.

For support, contact support@orcustech.com.

== External services ==

This plugin connects to the OrcusPay API to create hosted checkout sessions, verify payment status, and receive payment webhooks for WooCommerce orders.

The service is provided by OrcusPay. The API endpoint used by default is https://brain.orcuspay.com/api/v1.

When a customer chooses OrcusPay at checkout, the plugin sends order and payment details needed to create the checkout session, including the order ID, amount, currency, customer name, customer email, customer phone number when available, billing details when available, return/cancel/webhook URLs, and order line item descriptions.

When WooCommerce receives an OrcusPay webhook or verifies a payment, the plugin sends the OrcusPay payment/session identifiers and webhook signature data needed to confirm the payment and update the order status.

This data is sent only when OrcusPay is enabled as a WooCommerce payment method and is needed to process payments for the order.

Terms of service: https://orcuspay.com/terms/

Privacy policy: https://orcuspay.com/privacy/

== Changelog ==

== 0.2.2 ==
* Updated WordPress compatibility metadata.
* Added external service disclosure for the OrcusPay API.
* Matched the plugin text domain to the WordPress.org plugin slug.
* Restored a valid WordPress.org contributor username.

== 0.2.0 ==
* Updated branding to OrcusPay.
* Updated checkout API integration.
* Added self-contained Svix-compatible webhook signature verification.
* Added payment amount verification and safer idempotent order completion.
* Added WooCommerce HPOS compatibility declaration.
* Removed the hard dependency on a bundled Composer vendor folder.

== 0.1.3 ==
* Bug fixes

== 0.1.2 ==
* Added support for WooCommerce collect payment option

== 0.1.1 ==
* Fix webhook url issue

= 0.1.0 =
* Initial release
