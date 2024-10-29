<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
//// admin interface settings fields ////

return apply_filters( 'wc_bancr_settings',
	array(
		'enabled' => array(
			'title'       => __( 'Enable/Disable', 'woo-gateway-bancr' ),
			'label'       => __( 'Enable Bancr', 'woo-gateway-bancr' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',

		),
		'title' => array(
			'title'       => __( 'Title', 'woo-gateway-bancr' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woo-gateway-bancr' ),
			'default'     => __( 'Bancr Payments', 'woo-gateway-bancr' ),
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => __( 'Description', 'woo-gateway-bancr' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woo-gateway-bancr' ),
			'default'     => __( 'Pay your Orders via Bancr.', 'woo-gateway-bancr' ),
			'desc_tip'    => true,
		),
		'testmode' => array(
			'title'       => __( 'Test mode', 'woo-gateway-bancr' ),
			'label'       => __( 'Enable Test Mode', 'woo-gateway-bancr' ),
			'type'        => 'checkbox',
			'description' => __( 'Place the payment gateway in test mode using test Merchant ID.', 'woo-gateway-bancr' ),
			'default'     => 'yes',
			'desc_tip'    => false,
		),
		'sandbox_merchant_id' => array(
			'title'       => __( 'Sandbox Merchant ID', 'woo-gateway-bancr' ),
			'type'        => 'text',
			'description' => __( 'Get your Merchant ID from your Bancr account.', 'woo-gateway-bancr' ),
			'default'     => '',
			'desc_tip'    => true,
		),

		'live_merchant_id' => array(
			'title'       => __( 'Live Merchant ID', 'woo-gateway-bancr' ),
			'type'        => 'text',
			'description' => __( 'Get your Merchant ID from your Bancr account.', 'woo-gateway-bancr' ),
			'default'     => '',
			'desc_tip'    => true,
		)
	)
);
