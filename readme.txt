=== OrcusPay for WooCommerce ===
Contributors: orcuspay
Tags: woocommerce, payment, orcuspay, checkout, bkash, nagad, rocket
Requires at least: 6.8
Tested up to: 6.9.4
Requires PHP: 7.4
Stable tag: 0.2.1
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

== Changelog ==

== 0.2.1 ==
* Confirmed compatibility with the current WordPress and WooCommerce releases.
* Updated plugin author/support details for Orcus Technology.
* Improved admin webhook URL visibility.
* Hardened gateway logging and settings link escaping.

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
