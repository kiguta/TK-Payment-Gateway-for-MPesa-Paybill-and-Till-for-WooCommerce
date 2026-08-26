# TK Paybill and Till Checkout for M-Pesa

MPesa WooCommerce payment gateway that allows customers to pay directly from their mobile phone via MPesa STK Push (Lipa Na MPesa Online). Supports both Paybill and Buy Goods Till, the classic shortcode checkout, and the WooCommerce Checkout Block.

This plugin is not affiliated with, endorsed by, or sponsored by Safaricom PLC. M-Pesa and Daraja are trademarks of Safaricom PLC, referenced here only to describe compatibility with their payment service.

##### Requires at least: Wordpress 6.4

##### Tested up to: Wordpress 7.1

##### Requires PHP: 7.4

##### WooCommerce requires at least: 6.0

##### WooCommerce tested up to: 11.0.1

---

### Features

- Sends an MPesa STK Push prompt straight to the customer's phone
- Supports both **Paybill** (CustomerPayBillOnline) and **Buy Goods Till** (CustomerBuyGoodsOnline)
- Compatible with the classic shortcode checkout and the WooCommerce Checkout Block
- Sandbox / test mode for development using the Safaricom Daraja sandbox
- Payment request tracking via a dedicated admin panel (WP Admin → MPesa Payments)

---

### Getting MPesa Credentials

1. Register a Paybill or Buy Goods Till with Safaricom MPesa
2. If using a Buy Goods Till, it must be settling funds to a bank account
3. Once ready, register an Administrator for your Paybill/Till through Safaricom
4. Use the registered Administrator credentials to create an App on the [MPesa Daraja Portal](https://developer.safaricom.co.ke)
5. The following credentials will be provided: **PassKey**, **Consumer Key**, and **Consumer Secret**

---

### Uploading and Activating

1. In your WordPress dashboard, go to **Plugins → Add New → Upload Plugin** and choose the plugin zip file (or upload the `tk-paybill-and-till-checkout-for-mpesa` folder to `/wp-content/plugins/` via FTP/SFTP)
2. Click **Activate**. WooCommerce must already be installed and active - if it isn't, activation stops and tells you to install WooCommerce first

### Configuring the Gateway

1. Navigate to **WooCommerce → Settings → Payments**
2. Click **Manage** on the MPesa Gateway row
3. Check **Enable MPesa Gateway**
4. Add your **PassKey**, **Consumer Key**, and **Consumer Secret**
5. Select the payment type (Paybill or Buy Goods Till)
6. Enter your **Short Code** and **Payment To** number
7. Set a unique **Callback URL** name - a short slug only (e.g. `abc123xyz`), not your website's URL. No spaces, and it must not contain the word "mpesa". The plugin builds the full callback link for you automatically
8. Save changes

> **Test mode:** Enable the **Test mode** checkbox to use the Safaricom Daraja sandbox API. Disable for live transactions.

### Managing Your Credentials Later

Return to **WooCommerce → Settings → Payments → MPesa Gateway** any time to update your credentials, for example after Safaricom issues new keys or when moving from sandbox to live. Existing orders and payment history are unaffected by a credentials change. Make sure the credentials you've entered match whichever mode (**Test mode** on or off) is enabled, since sandbox keys will not work in live mode and vice versa.

### Viewing Logs

If a payment doesn't go through, check **MPesa Payments → Logs** in your WordPress admin menu (or **WooCommerce → Status → Logs**, source `tk-mpesa`) for the exact error Safaricom's API returned.

---

### Requirements

- WordPress 6.4 or higher
- WooCommerce 6.0 or higher
- PHP 7.4 or higher
- HTTPS-enabled site (required by the MPesa callback)
- Active Safaricom MPesa Daraja API application

---

### Changelog

#### 2.2.0

- Security: the M-Pesa callback endpoint now requires a per-install secret token before processing any callback

#### 2.1.0

- Added an activation check requiring WooCommerce to be active
- Added `uninstall.php` to clean up settings and payment-request records on deletion
- Fixed: invalid Author URI header, incorrect output-escaping order on the checkout description field, and removed the deprecated `load_plugin_textdomain()` call
- Improved error logging: token and STK Push failures now log the HTTP status code and full API response, at Error level, so failures are easy to diagnose from WooCommerce → Status → Logs
- Fixed: duplicate MPesa callbacks (Safaricom sometimes resends the same notification) are now recognised and logged quietly instead of as errors
- Added a "Logs" submenu under MPesa Payments, linking straight to this plugin's WooCommerce log entries

#### 2.0.0

- Added WooCommerce Checkout Blocks support (`AbstractPaymentMethodType` integration)
- Added HPOS compatibility declaration
- Added sandbox/test mode with Daraja sandbox API URLs
- Fixed: nonce verification on payment form submission
- Fixed: input sanitisation on phone number field
- Fixed: output escaping on all admin column and order meta displays
- Fixed: broken `permission_callback` on REST callback endpoint
- Fixed: nested function declarations inside `process_payment()` - promoted to class methods
- Fixed: `parse_tel()` phone normalisation (replaced broken `ltrim()` with regex)
- Fixed: `date()` replaced with `gmdate()` for timezone correctness
- Replaced file-based logging (`StkRequests.txt`) with WooCommerce logger

#### 1.0.0

- Initial release

---

#### Donate

<a href="https://www.paypal.com/donate/?hosted_button_id=CSQFKDWQZVE4W" target="_blank">
  <img src="https://img.shields.io/badge/Donate-PayPal-green.svg" alt="Donate with PayPal">
</a>

##### MPesa Donation: 254725682556 or MPesa Till 5267627
