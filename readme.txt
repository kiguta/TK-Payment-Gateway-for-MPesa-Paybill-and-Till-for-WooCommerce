=== TK Paybill and Till Checkout for M-Pesa ===
Contributors: tkiguta
Tags: mpesa, woocommerce, payment, kenya, safaricom
Requires at least: 6.4
Tested up to: 7.1
Stable tag: 2.2.0
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 6.0
WC tested up to: 11.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce payment gateway for MPesa payments via Safaricom's Daraja API. Supports Paybill, Buy Goods Till, and the WooCommerce Checkout Block.

== Description ==

TK MPesa Payment Gateway allows your WooCommerce customers to pay directly from their mobile phone via MPesa STK Push (Lipa Na MPesa Online).

This plugin is not affiliated with, endorsed by, or sponsored by Safaricom PLC. M-Pesa and Daraja are trademarks of Safaricom PLC, referenced here only to describe compatibility with their payment service.

**How it works:**

1. A customer checks out and selects MPesa Payments, entering the MPesa number the STK prompt should be sent to.
2. The plugin authenticates with the Safaricom Daraja API and sends an STK Push (`stkpush/v1/processrequest`) request for the order total.
3. The customer receives a "Lipa Na MPesa" prompt directly on their phone and enters their MPesa PIN to approve it.
4. Safaricom sends a payment-result callback to this site's dedicated REST endpoint, protected by a per-install secret token so only genuine Safaricom callbacks are accepted.
5. On a successful callback, the order is marked paid automatically and the confirmation - including the MPesa number that was billed - is recorded on the order.

If a customer doesn't complete the prompt in time or the order is otherwise left unpaid, they can return to it later through WooCommerce's standard "Pay for order" screen to retry.

**Payment types:**

* Paybill (`CustomerPayBillOnline`) - pay into a Paybill number and account
* Buy Goods Till (`CustomerBuyGoodsOnline`) - pay into a Till number; the Till must be settling funds to a bank account
* Configurable **Short Code** (your Paybill/Till number) and **Payment To** number, independent of the payment type selected

**Checkout compatibility:**

* Classic shortcode checkout
* WooCommerce Checkout Block, via a full `AbstractPaymentMethodType` integration
* HPOS (High-Performance Order Storage) compatible

**Payment tracking & admin tools:**

* A dedicated **MPesa Payments** admin menu, separate from WooCommerce Orders, listing every STK Push request with its date, Request ID, linked Order ID, requesting MPesa number, amount, and payment status
* A **Logs** submenu that jumps straight to this plugin's entries under WooCommerce → Status → Logs (source `tk-mpesa`), useful when a payment doesn't go through
* Paid orders show the MPesa confirmation and billed phone number directly on the WooCommerce order screen, alongside the standard order details

**Sandbox / test mode:**

* A **Test mode** toggle switches the gateway to the Safaricom Daraja sandbox API for development, without touching your live credentials

**Security:**

* The MPesa callback endpoint requires a per-install secret token (generated on activation) before it will process any callback, preventing forged requests from marking an order as paid
* Callback payment amounts are compared using a tolerant float comparison rather than a loose `==`
* All checkout input is sanitised and all admin/checkout output is escaped
* Checkout requests are protected by WordPress nonces

**Requirements:**

* A Safaricom MPesa Paybill or Buy Goods Till number
* A registered Safaricom Daraja application (Consumer Key, Consumer Secret, PassKey)
* An HTTPS-enabled WordPress site (required by the MPesa callback)

== Installation ==

**Uploading and activating**

1. In your WordPress dashboard, go to **Plugins → Add New → Upload Plugin** and choose the plugin zip file (or upload the `tk-paybill-and-till-checkout-for-mpesa` folder to `/wp-content/plugins/` via FTP/SFTP).
2. Click **Activate**. WooCommerce must already be installed and active - if it isn't, activation stops and tells you to install WooCommerce first.

**Configuring the gateway**

3. Go to **WooCommerce → Settings → Payments**.
4. Click **Manage** on the MPesa Gateway row.
5. Check **Enable MPesa Gateway**.
6. Enter your **Pass Key**, **Consumer Key**, and **Consumer Secret** from the Daraja portal.
7. Select your **Payment Type** (Paybill or Buy Goods Till).
8. Enter your **Short Code** and **Payment To** number.
9. Enter a unique **Callback URL** name - a short slug only (e.g. `abc123xyz`), not your website's URL. No spaces, and it must not contain the word "mpesa". The plugin builds the full callback link for you automatically.
10. Save changes.

**Getting MPesa credentials**

1. Register a Paybill or Buy Goods Till with Safaricom MPesa.
2. If using a Buy Goods Till, it must be settling funds to a bank account.
3. Register an Administrator for your Paybill/Till through Safaricom.
4. Use those credentials to create an App on the MPesa Daraja Portal.
5. Your Pass Key, Consumer Key, and Consumer Secret will be provided.

**Managing your credentials later**

Return to **WooCommerce → Settings → Payments → MPesa Gateway** any time to update your credentials, for example after Safaricom issues new keys or when moving from sandbox to live. Existing orders and payment history are unaffected by a credentials change. The **Test mode** checkbox switches between the Safaricom sandbox and live production API - make sure the credentials you've entered match whichever mode is enabled, since sandbox keys will not work in live mode and vice versa.

**Viewing logs**

If a payment doesn't go through, check **MPesa Payments → Logs** in your WordPress admin menu (or **WooCommerce → Status → Logs**, source `tk-mpesa`) for the exact error Safaricom's API returned.

== Frequently Asked Questions ==

= Does this work with the WooCommerce Checkout Block? =

Yes. Version 2.0.0 and above fully supports the WooCommerce Checkout Block via the `AbstractPaymentMethodType` integration.

= Can I test without going live? =

Yes. Enable **Test mode** in the gateway settings to use the Safaricom Daraja sandbox API. Use sandbox credentials from the Daraja portal.

= Why must my callback URL not contain "mpesa"? =

Safaricom's systems block callback URLs containing the word "mpesa" as a security measure.

= Is this plugin PCI DSS compliant? =

Yes. No card data is handled. All payment data flows directly between the customer's phone and Safaricom's servers. The plugin only initiates an STK Push request and receives a callback confirmation.

== Screenshots ==

1. The MPesa Gateway listed on the WooCommerce Payments settings screen
2. The gateway configuration screen - credentials, Paybill/Till setup, and callback URL
3. Selecting the payment type - Paybill or Buy Goods Till
4. The gateway enabled and ready to accept payments
5. What the customer sees at checkout - MPesa Payments selected, prompting for their phone number
6. The customer-facing "Pay for order" screen for retrying or completing payment on an existing order
7. A completed MPesa order in the WooCommerce Orders list
8. Order detail showing the MPesa payment confirmation and the customer's billed MPesa number
9. The plugin on the WordPress Plugins screen, ready to activate
10. The plugin active, with donation links on the Plugins screen
11. The MPesa Payments admin menu, with a dedicated Logs submenu
12. Payment request tracking - every STK Push request logged with its status
13. Diagnostic logs under WooCommerce → Status → Logs (source `tk-mpesa`)

== External services ==

This plugin connects to the Safaricom Daraja API to process M-Pesa payments. This is required for the plugin's core function - initiating an M-Pesa STK Push payment request to the customer's phone and receiving confirmation that it was paid.

Two Daraja endpoints are used:

* `oauth/v1/generate` - authenticates the site with Safaricom using the Consumer Key and Consumer Secret you enter in the gateway settings. No customer data is sent to this endpoint.
* `mpesa/stkpush/v1/processrequest` - initiates the STK Push. When a customer places an order and chooses this payment method at checkout, the following is sent: your configured Paybill/Till short code, the order amount, the customer's phone number, a callback URL, and the WooCommerce order ID (as a reference). Safaricom then sends a payment-result callback to this site's REST endpoint.

This plugin is not affiliated with, endorsed by, or sponsored by Safaricom PLC. Learn more: [Daraja API Terms and Conditions](https://developer.safaricom.co.ke/t&c), [Safaricom Data Privacy Statement](https://www.safaricom.co.ke/dataprivacystatement/).

== Changelog ==

= 2.2.0 =
* Security: the M-Pesa callback endpoint now requires a per-install secret token (generated on activation) before processing any callback, preventing a forged request from marking an order as paid
* Security: callback amount comparison now uses a tolerant float comparison instead of loose `==`

= 2.1.0 =
* Added activation check that requires WooCommerce to be active
* Added `uninstall.php` to clean up gateway settings and payment-request records on deletion
* Fixed: invalid Author URI header, incorrect output-escaping order on the checkout description field, and removed the deprecated `load_plugin_textdomain()` call
* Improved error logging: token and STK Push failures now log the HTTP status code and full API response, at Error level, so failures are easy to diagnose from WooCommerce → Status → Logs
* Fixed: duplicate MPesa callbacks (Safaricom sometimes resends the same notification) are now recognised and logged quietly instead of as errors
* Added a "Logs" submenu under MPesa Payments, linking straight to this plugin's WooCommerce log entries

= 2.0.0 =
* Added WooCommerce Checkout Blocks support
* Added HPOS (High-Performance Order Storage) compatibility
* Added sandbox/test mode with Daraja sandbox API URLs
* Fixed security: nonce verification on payment form
* Fixed security: input sanitisation on phone number field
* Fixed security: output escaping on all admin displays
* Fixed: broken `permission_callback` on REST callback endpoint
* Fixed: nested function declarations promoted to class methods
* Fixed: `parse_tel()` phone normalisation using regex (replaced broken `ltrim()`)
* Fixed: `date()` replaced with `gmdate()` for timezone correctness
* Replaced file-based logging with WooCommerce logger (WooCommerce → Status → Logs)

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.2.0 =
Plugin renamed for WordPress.org trademark compliance; internal identifiers were also namespaced, including the payment-request storage. Existing payment-request records from prior versions will no longer appear in the admin list after upgrading (the data is retained in the database, just under the old internal type). Your gateway settings, orders, and webhook URL are unaffected - only the underlying payment-request tracking records are affected.

= 2.1.0 =
Plugin renamed to TK MPesa Payment Gateway (new slug). If upgrading from a manual install: activate this version first, confirm your settings and orders still show correctly, then remove the old copy. Don't delete either copy until migration is confirmed.

= 2.0.0 =
Security and compatibility update. Adds WooCommerce Blocks support and HPOS compatibility. Fully backward compatible — existing settings, orders, and webhook URLs are unchanged.
