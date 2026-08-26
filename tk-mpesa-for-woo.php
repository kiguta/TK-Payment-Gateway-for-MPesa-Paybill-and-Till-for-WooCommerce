<?php
/**
 * Plugin Name: TK Paybill and Till Checkout for M-Pesa
 * Plugin URI: https://github.com/kiguta/TK-Payment-Gateway-for-MPesa-Paybill-and-Till-for-WooCommerce
 * Description: WooCommerce payment gateway for MPesa payments via Safaricom's Daraja API. Supports STK Push for Paybill and Buy Goods Till, and works with the classic checkout and the WooCommerce Checkout Block.
 * Version: 2.2.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: Tonie Kiguta
 * Author URI: https://github.com/kiguta
 * Text Domain: tk-paybill-and-till-checkout-for-mpesa
 * Domain Path: /languages
 * WC requires at least: 6.0
 * WC tested up to: 11.0.1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
	exit;
}

// HPOS compatibility declaration.
add_action('before_woocommerce_init', function () {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
});

// Checkout Blocks integration.
add_action('woocommerce_blocks_loaded', function () {
	if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
		return;
	}
	require_once plugin_dir_path(__FILE__) . 'includes/class-wc-mpesa-blocks-integration.php';
	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function ($registry) {
			$registry->register(new TK_MPesa_Blocks_Integration());
		}
	);
});

// Require WooCommerce to be active.
register_activation_hook(__FILE__, 'tk_mpesa_activation_check');
function tk_mpesa_activation_check()
{
	if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true)) {
		deactivate_plugins(plugin_basename(__FILE__));
		wp_die(
			esc_html__('TK Paybill and Till Checkout for M-Pesa requires WooCommerce to be installed and active.', 'tk-paybill-and-till-checkout-for-mpesa'),
			esc_html__('Plugin activation error', 'tk-paybill-and-till-checkout-for-mpesa'),
			array('back_link' => true)
		);
	}

	// Generate a per-install secret used to authenticate MPesa callback requests.
	// Only generated once; reactivation must not rotate an already-configured Daraja CallBackURL's token.
	if (false === get_option('tk_mpesa_callback_secret')) {
		update_option('tk_mpesa_callback_secret', wp_generate_password(32, false), false);
	}
}

// Donate link (Plugins screen row meta).
add_filter('plugin_row_meta', 'tk_mpesa_plugin_row_meta', 10, 2);
function tk_mpesa_plugin_row_meta($links, $file)
{
	if (plugin_basename(__FILE__) !== $file) {
		return $links;
	}

	foreach ($links as $key => $link) {
		if (strpos($link, 'plugin-uri') !== false || strpos($link, 'Visit plugin site') !== false) {
			$links[$key] = str_replace('<a ', '<a target="_blank" ', $link);
		}
	}

	$links[] = '<a href="https://www.paypal.com/donate/?hosted_button_id=CSQFKDWQZVE4W" target="_blank" class="tk-mpesa-donate-button">'
		. '<span class="tk-mpesa-donate-label">' . esc_html__('Donate', 'tk-paybill-and-till-checkout-for-mpesa') . '</span>'
		. '<span class="tk-mpesa-donate-paypal">' . esc_html__('PayPal', 'tk-paybill-and-till-checkout-for-mpesa') . '</span>'
		. '</a>';
	$links[] = esc_html__('MPesa Donation: 254725682556 or MPesa Till 5267627', 'tk-paybill-and-till-checkout-for-mpesa');

	return $links;
}

// Donate button styling (Plugins screen only, no remote assets).
add_action('admin_enqueue_scripts', 'tk_mpesa_donate_button_styles');
function tk_mpesa_donate_button_styles($hook)
{
	if ('plugins.php' !== $hook) {
		return;
	}

	wp_register_style('tk-mpesa-donate-button', false, array(), '2.2.0');
	wp_enqueue_style('tk-mpesa-donate-button');
	wp_add_inline_style(
		'tk-mpesa-donate-button',
		'.tk-mpesa-donate-button { display: inline-flex; align-items: stretch; text-decoration: none; font-size: 11px; font-weight: 600; line-height: 1; border-radius: 3px; overflow: hidden; vertical-align: middle; }
		.tk-mpesa-donate-button span { padding: 3px 8px; }
		.tk-mpesa-donate-label { background: #4b4f56; color: #fff; }
		.tk-mpesa-donate-paypal { background: #4caf50; color: #fff; }'
	);
}

// Register gateway class with WooCommerce.
add_filter('woocommerce_payment_gateways', 'tk_add_mpesa_gateway_class');
function tk_add_mpesa_gateway_class($gateways)
{
	$gateways[] = 'TK_MPesa_Gateway';
	return $gateways;
}

// Hooks.
add_action('plugins_loaded', 'tk_mpesa_init_gateway_class');
add_action('woocommerce_checkout_update_order_meta', 'tk_woo_checkout_update_order_meta', 10, 1);
add_action('woocommerce_admin_order_data_after_billing_address', 'tk_woo_order_data_after_billing_address', 10, 1);
add_action('rest_api_init', 'tk_woo_add_callback_url_endpoint');
add_action('init', 'add_tk_woo_post_type');

function tk_mpesa_init_gateway_class()
{

	class TK_MPesa_Gateway extends WC_Payment_Gateway
	{

		/** @var bool */
		public $testmode;

		public function __construct()
		{
			$this->id = 'tk_mpesa';
			$this->icon = apply_filters('tk_mpesa_gateway_icon', plugins_url('/assets/mpesa_icon.png', __FILE__));
			$this->has_fields = true;
			$this->method_title = __('MPesa Gateway', 'tk-paybill-and-till-checkout-for-mpesa');
			$this->method_description = __('Receive payments from your customers via MPesa.', 'tk-paybill-and-till-checkout-for-mpesa');
			$this->supports = array('products');

			$this->init_form_fields();
			$this->init_settings();

			$this->title = $this->get_option('title');
			$this->description = $this->get_option('description');
			$this->enabled = $this->get_option('enabled');
			$this->testmode = 'yes' === $this->get_option('testmode');

			// MPesa parameters.
			$this->customer_key = $this->get_option('customer_key');
			$this->customer_pass = $this->get_option('customer_pass');
			$this->short_code = $this->get_option('short_code');
			$this->pay_to = $this->get_option('pay_to');
			$this->payment_type = $this->get_option('payment_type');
			$this->pass_key = $this->get_option('pass_key');
			$this->callback_url = $this->get_option('callback_url');

			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
		}

		public function init_form_fields()
		{
			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable/Disable', 'tk-paybill-and-till-checkout-for-mpesa'),
					'label' => __('Enable MPesa Gateway', 'tk-paybill-and-till-checkout-for-mpesa'),
					'type' => 'checkbox',
					'default' => 'no',
				),
				'testmode' => array(
					'title' => __('Test mode', 'tk-paybill-and-till-checkout-for-mpesa'),
					'label' => __('Enable Daraja Sandbox', 'tk-paybill-and-till-checkout-for-mpesa'),
					'type' => 'checkbox',
					'default' => 'no',
					'description' => __('Use the Safaricom sandbox API for testing. Disable for live transactions.', 'tk-paybill-and-till-checkout-for-mpesa'),
				),
				'title' => array(
					'title' => __('Title', 'tk-paybill-and-till-checkout-for-mpesa'),
					'type' => 'text',
					'description' => __('This controls the title which the user sees during checkout.', 'tk-paybill-and-till-checkout-for-mpesa'),
					'default' => __('MPesa Payments', 'tk-paybill-and-till-checkout-for-mpesa'),
					'desc_tip' => true,
				),
				'description' => array(
					'title' => __('Description', 'tk-paybill-and-till-checkout-for-mpesa'),
					'type' => 'textarea',
					'description' => __('This controls the description which the user sees during checkout.', 'tk-paybill-and-till-checkout-for-mpesa'),
					'default' => __('A payment request will be sent to the payment MPesa number you will provide below. Please ensure your phone is ON and Unlocked', 'tk-paybill-and-till-checkout-for-mpesa'),
				),
				'customer_key' => array(
					'title' => __('App Consumer Key', 'tk-paybill-and-till-checkout-for-mpesa'),
					'type' => 'text',
				),
				'customer_pass' => array(
					'title' => __('App Consumer Secret', 'tk-paybill-and-till-checkout-for-mpesa'),
					'type' => 'password',
				),
				'pass_key' => array(
					'title' => __('Pass Key', 'tk-paybill-and-till-checkout-for-mpesa'),
					'type' => 'text',
				),
				'payment_type' => array(
					'title' => __('Payment Type', 'tk-paybill-and-till-checkout-for-mpesa'),
					'type' => 'select',
					'options' => array(
						'none' => __('Select Payment Type', 'tk-paybill-and-till-checkout-for-mpesa'),
						'CustomerPayBillOnline' => 'CustomerPayBillOnline',
						'CustomerBuyGoodsOnline' => 'CustomerBuyGoodsOnline',
					),
					'description' => __('CustomerPayBillOnline if using Paybill or CustomerBuyGoodsOnline if using a Till Number', 'tk-paybill-and-till-checkout-for-mpesa'),
				),
				'short_code' => array(
					'title' => __('Short Code', 'tk-paybill-and-till-checkout-for-mpesa'),
					'description' => __('This is Paybill Number for Paybill or the Till Short Code', 'tk-paybill-and-till-checkout-for-mpesa'),
					'type' => 'text',
				),
				'pay_to' => array(
					'title' => __('Payment to', 'tk-paybill-and-till-checkout-for-mpesa'),
					'type' => 'text',
					'description' => __('This is the Paybill Number / Till Number', 'tk-paybill-and-till-checkout-for-mpesa'),
				),
				'callback_url' => array(
					'title' => __('Callback URL', 'tk-paybill-and-till-checkout-for-mpesa'),
					'type' => 'text',
					'description' => __('Enter only a short, unique endpoint name (e.g. abc123xyz) - not your website\'s URL. No spaces, and it must not contain the word "mpesa". Your full callback link is built automatically from this.', 'tk-paybill-and-till-checkout-for-mpesa'),
				),
			);
		}

		public function payment_fields()
		{
			if ($this->description) {
				echo wp_kses_post(wpautop($this->description));
			}

			wp_nonce_field('tk_mpesa_payment', 'tk_mpesa_nonce');

			echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form">';

			do_action('woocommerce_credit_card_form_start', $this->id);

			echo '<div class="form-row form-row-wide">
				<label>' . esc_html__('MPesa Phone Number', 'tk-paybill-and-till-checkout-for-mpesa') . ' <span class="required">*</span></label>
				<input id="tk-mpesa-phone" name="phonenumber" type="text" autocomplete="off" required="required" class="tk-mpesa-phone-input" placeholder="0XXX XXX XXX">
				<div class="clear"></div>
			</div>';

			do_action('woocommerce_credit_card_form_end', $this->id);

			echo '<div class="clear"></div></fieldset>';
		}

		public function payment_scripts()
		{
			if (!is_checkout()) {
				return;
			}
			wp_enqueue_style(
				'tk-mpesa-checkout',
				plugins_url('assets/css/mpesa-checkout.css', __FILE__),
				array(),
				'2.2.0'
			);
		}

		public function validate_fields()
		{
			if (
				!isset($_POST['tk_mpesa_nonce'])
				|| !wp_verify_nonce(sanitize_key(wp_unslash($_POST['tk_mpesa_nonce'])), 'tk_mpesa_payment')
			) {
				wc_add_notice(__('Security check failed. Please refresh and try again.', 'tk-paybill-and-till-checkout-for-mpesa'), 'error');
				return false;
			}

			if (empty($_POST['phonenumber'])) {
				wc_add_notice(__('MPesa Phone Number is required!', 'tk-paybill-and-till-checkout-for-mpesa'), 'error');
				return false;
			}

			return true;
		}

		public function process_payment($order_id)
		{
			$order = wc_get_order($order_id);

			// Support both classic checkout ($_POST) and Blocks checkout (payment_method_data).
			$raw_phone = '';
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in validate_fields().
			if (!empty($_POST['phonenumber'])) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in validate_fields().
				$raw_phone = sanitize_text_field(wp_unslash($_POST['phonenumber']));
			} elseif (isset($this->payment_method_data['phonenumber'])) {
				$raw_phone = sanitize_text_field($this->payment_method_data['phonenumber']);
			}

			$payment_args = array(
				'customer_key' => $this->customer_key,
				'customer_pass' => $this->customer_pass,
				'short_code' => $this->short_code,
				'payment_type' => $this->payment_type,
				'pass_key' => $this->pass_key,
				'callbackurl' => get_site_url(null, '/wp-json/tk_woopesa/v1/' . $this->callback_url, 'https')
					. '?token=' . rawurlencode(get_option('tk_mpesa_callback_secret')),
				'payment_amount' => (int) ceil($order->get_total()),
				'tel' => $this->parse_tel($raw_phone),
				'ref' => $order->get_id(),
				'pay_to' => $this->pay_to,
			);

			$response = $this->stk_push($payment_args);

			if (is_wp_error($response) || isset($response->errorMessage)) {
				$error_msg = is_wp_error($response) ? $response->get_error_message() : $response->errorMessage;
				wc_add_notice(__('MPesa payment request failed. Please try again.', 'tk-paybill-and-till-checkout-for-mpesa'), 'error');
				$this->log('STK Push error: ' . $error_msg, 'error');
				return;
			}

			$order->update_status('pending', __('Awaiting MPesa Payment Confirmation', 'tk-paybill-and-till-checkout-for-mpesa'));
			wc_reduce_stock_levels($order->get_id());
			WC()->cart->empty_cart();

			$post_id = wp_insert_post(array(
				'post_title' => $order->get_id(),
				'post_content' => wp_json_encode($response),
				'post_status' => 'draft',
				'post_type' => 'tk_mpesa_payment_req',
			));

			update_post_meta($post_id, 'MerchantRequestID', $response->MerchantRequestID);
			update_post_meta($post_id, 'CheckoutRequestID', $response->CheckoutRequestID);
			update_post_meta($post_id, 'ResponseCode', $response->ResponseCode);
			update_post_meta($post_id, 'ResponseDescription', $response->ResponseDescription);
			update_post_meta($post_id, 'CustomerMessage', $response->CustomerMessage);
			update_post_meta($post_id, 'DepositRef', $order->get_id());
			update_post_meta($post_id, 'RequestAmount', (int) ceil($order->get_total()));
			update_post_meta($post_id, 'MpesaRequestNumber', $payment_args['tel']);
			update_post_meta($post_id, 'PaymentStatus', 'pending');

			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url($order),
			);
		}

		public function webhook()
		{
		}

		// -------------------------------------------------------------------------
		// Private helpers
		// -------------------------------------------------------------------------

		private function generate_token(array $payment_args): string
		{
			$credentials = base64_encode($payment_args['customer_key'] . ':' . $payment_args['customer_pass']);
			$url = $this->testmode
				? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
				: 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

			$response = wp_remote_get($url, array(
				'headers' => array('Authorization' => 'Basic ' . $credentials),
				'timeout' => 30,
			));

			if (is_wp_error($response)) {
				$this->log('Token request failed: ' . $response->get_error_message(), 'error');
				return '';
			}

			$status_code = wp_remote_retrieve_response_code($response);
			$raw_body = wp_remote_retrieve_body($response);
			$body = json_decode($raw_body);

			if (empty($body->access_token)) {
				$this->log(sprintf('Token request returned no access_token (HTTP %d): %s', $status_code, $raw_body), 'error');
				return '';
			}

			return $body->access_token;
		}

		private function stk_push(array $payment_args)
		{
			$token = $this->generate_token($payment_args);

			if (empty($token)) {
				return new WP_Error('token_error', __('Could not authenticate with MPesa API.', 'tk-paybill-and-till-checkout-for-mpesa'));
			}

			$url = $this->testmode
				? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
				: 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

			$timestamp = gmdate('YmdHis');
			$password = base64_encode($payment_args['short_code'] . $payment_args['pass_key'] . $timestamp);

			$body = array(
				'BusinessShortCode' => $payment_args['short_code'],
				'Password' => $password,
				'Timestamp' => $timestamp,
				'TransactionType' => $payment_args['payment_type'],
				'Amount' => $payment_args['payment_amount'],
				'PartyA' => $payment_args['tel'],
				'PartyB' => $payment_args['pay_to'],
				'PhoneNumber' => $payment_args['tel'],
				'CallBackURL' => $payment_args['callbackurl'],
				'AccountReference' => $payment_args['ref'],
				'TransactionDesc' => 'stk',
			);

			$response = wp_remote_post($url, array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				),
				'body' => wp_json_encode($body),
				'timeout' => 30,
			));

			if (is_wp_error($response)) {
				$this->log('STK push request failed: ' . $response->get_error_message(), 'error');
				return $response;
			}

			$status_code = wp_remote_retrieve_response_code($response);
			$raw_body = wp_remote_retrieve_body($response);
			$data = json_decode($raw_body);

			if (!is_object($data) || !isset($data->CheckoutRequestID)) {
				$this->log(sprintf('STK push returned an unexpected response (HTTP %d): %s', $status_code, $raw_body), 'error');
				return new WP_Error(
					'stk_push_error',
					isset($data->errorMessage) ? $data->errorMessage : __('Unexpected response from MPesa API.', 'tk-paybill-and-till-checkout-for-mpesa')
				);
			}

			return $data;
		}

		private function parse_tel(string $tel): string
		{
			$tel = preg_replace('/\s+/', '', $tel);
			$tel = preg_replace('/^\+?254/', '', $tel);
			$tel = ltrim($tel, '0');
			return '254' . $tel;
		}

		private function log(string $message, string $level = 'info'): void
		{
			wc_get_logger()->log($level, $message, array('source' => 'tk-mpesa'));
		}
	}
}

// Save MPesa phone number to order meta.
function tk_woo_checkout_update_order_meta($order_id)
{
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in validate_fields().
	if (isset($_POST['phonenumber']) && !empty($_POST['phonenumber'])) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in validate_fields().
		update_post_meta($order_id, 'phonenumber', sanitize_text_field(wp_unslash($_POST['phonenumber'])));
	}
}

// Display MPesa phone number in admin order view.
function tk_woo_order_data_after_billing_address($order)
{
	echo '<p><strong>' . esc_html__('Billed MPesa Number:', 'tk-paybill-and-till-checkout-for-mpesa') . '</strong><br>'
		. esc_html(get_post_meta($order->get_id(), 'phonenumber', true)) . '</p>';
}

// Register the MPesa callback REST endpoint.
function tk_woo_add_callback_url_endpoint()
{
	$payment_gateway = WC()->payment_gateways->payment_gateways()['tk_mpesa'] ?? null;
	if ($payment_gateway && !empty($payment_gateway->callback_url)) {
		// permission_callback is intentionally __return_true: Safaricom cannot authenticate via WP
		// application passwords/cookies/nonces. Real authorization happens inside
		// tk_woo_receive_callback(), which verifies the ?token= query param against the
		// per-install secret in the 'tk_mpesa_callback_secret' option before trusting anything else.
		register_rest_route(
			'tk_woopesa/v1',
			$payment_gateway->callback_url,
			array(
				'methods' => 'POST',
				'callback' => 'tk_woo_receive_callback',
				'permission_callback' => '__return_true',
			)
		);
	}
}

// Handle MPesa STK callback.
function tk_woo_receive_callback($request_data)
{
	$provided_token = (string) $request_data->get_param('token');
	$expected_token = (string) get_option('tk_mpesa_callback_secret');

	if (empty($expected_token) || !hash_equals($expected_token, $provided_token)) {
		return 'Invalid';
	}

	$parameters = $request_data->get_params();

	$logger = wc_get_logger();
	$logger->info(wp_json_encode($parameters), array('source' => 'tk-mpesa'));

	$MerchantRequestID = isset($parameters['Body']['stkCallback']['MerchantRequestID']) ? $parameters['Body']['stkCallback']['MerchantRequestID'] : null;
	$CheckoutRequestID = isset($parameters['Body']['stkCallback']['CheckoutRequestID']) ? $parameters['Body']['stkCallback']['CheckoutRequestID'] : null;
	$ResultCode = isset($parameters['Body']['stkCallback']['ResultCode']) ? $parameters['Body']['stkCallback']['ResultCode'] : null;
	$ResultDesc = isset($parameters['Body']['stkCallback']['ResultDesc']) ? $parameters['Body']['stkCallback']['ResultDesc'] : null;

	$CallbackMetadata = null;
	$Amount = null;
	$MpesaReceiptNumber = null;
	$Balance = null;
	$TransactionDate = null;
	$PhoneNumber = null;

	if (isset($parameters['Body']['stkCallback']['CallbackMetadata'])) {
		$CallbackMetadata = $parameters['Body']['stkCallback']['CallbackMetadata'];
		$Amount = $parameters['Body']['stkCallback']['CallbackMetadata']['Item']['0']['Value'];
		$MpesaReceiptNumber = $parameters['Body']['stkCallback']['CallbackMetadata']['Item']['1']['Value'];
		$Balance = $parameters['Body']['stkCallback']['CallbackMetadata']['Item']['2']['Value'];
		$TransactionDate = $parameters['Body']['stkCallback']['CallbackMetadata']['Item']['3']['Value'];
		$PhoneNumber = $parameters['Body']['stkCallback']['CallbackMetadata']['Item']['4']['Value'];
	}

	if (!isset($CheckoutRequestID)) {
		return 'Invalid';
	}

	$requestpost = get_posts(array(
		'post_type' => 'tk_mpesa_payment_req',
		'post_status' => 'any',
		'numberposts' => 1,
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- no dedicated lookup table exists for payment requests.
		'meta_query' => array(
			array('key' => 'CheckoutRequestID', 'value' => $CheckoutRequestID),
		),
	));

	if (empty($requestpost)) {
		$logger->error('No payment request found for CheckoutRequestID: ' . $CheckoutRequestID, array('source' => 'tk-mpesa'));
		return 'Invalid';
	}

	$post_id = $requestpost[0]->ID;
	$PaymentStatus = get_post_meta($post_id, 'PaymentStatus', true);

	// Safaricom sometimes resends the same callback; ignore it quietly once already processed.
	if ('pending' !== $PaymentStatus) {
		$logger->info('Duplicate callback ignored for CheckoutRequestID: ' . $CheckoutRequestID . ' (already ' . $PaymentStatus . ')', array('source' => 'tk-mpesa'));
		return 'Duplicate';
	}

	$RequestAmount = get_post_meta($post_id, 'RequestAmount', true);
	$orderID = get_post_meta($post_id, 'DepositRef', true);

	if (!isset($CallbackMetadata)) {
		update_post_meta($post_id, 'ResultCode', $ResultCode);
		update_post_meta($post_id, 'ResultDesc', $ResultDesc);
		update_post_meta($post_id, 'PaymentStatus', 'cancelled');
		wp_trash_post($post_id);

		$order = wc_get_order($orderID);
		if ($order) {
			$order->update_status('cancelled', __('Payment process not completed.', 'tk-paybill-and-till-checkout-for-mpesa'));
		}
	} else {
		update_post_meta($post_id, 'ResultCode', $ResultCode);
		update_post_meta($post_id, 'ResultDesc', $ResultDesc);
		update_post_meta($post_id, 'Amount', $Amount);
		update_post_meta($post_id, 'MpesaReceiptNumber', $MpesaReceiptNumber);
		update_post_meta($post_id, 'TransactionDate', $TransactionDate);
		update_post_meta($post_id, 'PhoneNumber', $PhoneNumber);
		update_post_meta($post_id, 'Balance', $Balance);

		if (abs((float) $RequestAmount - (float) $Amount) < 0.01) {
			update_post_meta($post_id, 'PaymentStatus', 'Paid');
			wp_update_post(array('ID' => $post_id, 'post_status' => 'publish'));

			$order = wc_get_order($orderID);
			if ($order) {
				/* translators: %s: MPesa receipt number */
				$order->update_status('completed', sprintf(__('Paid via MPesa: %s.', 'tk-paybill-and-till-checkout-for-mpesa'), $MpesaReceiptNumber));
			}
		}
	}

	return 'Updated';
}

// Register the payment requests custom post type.
function add_tk_woo_post_type()
{
	$labels = array(
		'name' => __('MPesa Payments', 'tk-paybill-and-till-checkout-for-mpesa'),
		'singular_name' => __('MPesa Payment', 'tk-paybill-and-till-checkout-for-mpesa'),
	);

	$args = array(
		'label' => __('MPesa Payments', 'tk-paybill-and-till-checkout-for-mpesa'),
		'labels' => $labels,
		'public' => true,
		'publicly_queryable' => false,
		'show_ui' => true,
		'show_in_rest' => true,
		'has_archive' => false,
		'show_in_menu' => true,
		'capabilities' => array('create_posts' => 'do_not_allow'),
		'map_meta_cap' => true,
		'show_in_nav_menus' => false,
		'delete_with_user' => false,
		'exclude_from_search' => true,
		'capability_type' => 'post',
		'hierarchical' => false,
		'can_export' => true,
		'query_var' => true,
		'menu_icon' => 'dashicons-menu',
		'supports' => array('custom-fields'),
		'show_in_graphql' => false,
	);

	register_post_type('tk_mpesa_payment_req', $args);
}

// Logs submenu under the MPesa Payments menu.
add_action('admin_menu', 'tk_mpesa_add_logs_submenu');
function tk_mpesa_add_logs_submenu()
{
	add_submenu_page(
		'edit.php?post_type=tk_mpesa_payment_req',
		__('MPesa Logs', 'tk-paybill-and-till-checkout-for-mpesa'),
		__('Logs', 'tk-paybill-and-till-checkout-for-mpesa'),
		'manage_woocommerce',
		'tk-mpesa-logs',
		'tk_mpesa_render_logs_redirect'
	);
}

function tk_mpesa_render_logs_redirect()
{
	if (!current_user_can('manage_woocommerce')) {
		wp_die(esc_html__('You do not have permission to view this page.', 'tk-paybill-and-till-checkout-for-mpesa'));
	}

	$target = add_query_arg('source', 'tk-mpesa', 'admin.php?page=wc-status&tab=logs');

	if (function_exists('wc_get_log_file_path')) {
		$path = wc_get_log_file_path('tk-mpesa');
		if ($path && file_exists($path)) {
			$target = add_query_arg('log_file', basename($path), $target);
		}
	}

	wp_safe_redirect(admin_url($target));
	exit;
}

// Admin columns for the payment requests CPT.
add_filter('manage_tk_mpesa_payment_req_posts_columns', 'tk_mpesa_woo_filter_posts_columns');
function tk_mpesa_woo_filter_posts_columns($columns)
{
	unset($columns['title']);
	$columns['CheckoutRequestID'] = __('Request ID', 'tk-paybill-and-till-checkout-for-mpesa');
	$columns['DepositRef'] = __('Order ID', 'tk-paybill-and-till-checkout-for-mpesa');
	$columns['MpesaRequestNumber'] = __('Requesting Number', 'tk-paybill-and-till-checkout-for-mpesa');
	$columns['RequestAmount'] = __('Request Amount', 'tk-paybill-and-till-checkout-for-mpesa');
	$columns['PaymentStatus'] = __('Payment Status', 'tk-paybill-and-till-checkout-for-mpesa');
	return $columns;
}

add_action('manage_tk_mpesa_payment_req_posts_custom_column', 'tk_mpesa_woo_paymentrequests_column', 10, 2);
function tk_mpesa_woo_paymentrequests_column($column, $post_id)
{
	switch ($column) {
		case 'CheckoutRequestID':
			echo esc_html(get_post_meta($post_id, 'CheckoutRequestID', true));
			break;
		case 'DepositRef':
			echo esc_html(get_post_meta($post_id, 'DepositRef', true));
			break;
		case 'MpesaRequestNumber':
			echo esc_html(get_post_meta($post_id, 'MpesaRequestNumber', true));
			break;
		case 'RequestAmount':
			echo esc_html(number_format((float) get_post_meta($post_id, 'RequestAmount', true), 2));
			break;
		case 'PaymentStatus':
			echo esc_html(ucfirst(get_post_meta($post_id, 'PaymentStatus', true)));
			break;
		default:
			echo '';
			break;
	}
}

add_filter('manage_edit-tk_mpesa_payment_req_sortable_columns', 'tk_mpesa_woo_sortable_columns');
function tk_mpesa_woo_sortable_columns($columns)
{
	$columns['DepositRef'] = 'DepositRef';
	$columns['MpesaRequestNumber'] = 'MpesaRequestNumber';
	return $columns;
}
