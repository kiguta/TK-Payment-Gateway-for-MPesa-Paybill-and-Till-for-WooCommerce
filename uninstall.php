<?php
/**
 * Runs automatically when the plugin is deleted via the WordPress admin.
 * Removes the gateway settings and all payment-request tracking data.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'woocommerce_tk_mpesa_settings' );

$payment_requests = get_posts(
	array(
		'post_type'   => 'tk_mpesa_payment_req',
		'numberposts' => -1,
		'post_status' => 'any',
		'fields'      => 'ids',
	)
);

foreach ( $payment_requests as $post_id ) {
	wp_delete_post( $post_id, true );
}
