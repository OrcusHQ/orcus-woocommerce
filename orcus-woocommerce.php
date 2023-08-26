<?php

/*
 * Plugin Name: Orcus for WooCommerce
 * Description: Orcus for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/orcuspay-for-woocommerce
 * Author: Orcus
 * Author URI: https://orcus.com.bd/
 * Version: 0.1.3
 * Requires at least: 5.9
 * Tested up to: 6.3
 * WC requires at least: 7.1
 * WC tested up to: 7.4
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: orcus-woo
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ORCUS_WOO_PLUGIN_SLUG', 'orcus' );
define( 'ORCUS_WOO_PLUGIN_BASEPATH', plugin_basename( __FILE__ ) );

require_once __DIR__ . '/vendor/autoload.php';

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

add_action( 'plugins_loaded', 'orcus_woo_init', 11 );

function orcus_woo_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class WC_Orcus extends WC_Payment_Gateway {
		public function __construct() {
			$this->id                 = ORCUS_WOO_PLUGIN_SLUG;
			$this->icon               = plugins_url( 'assets/images/orcus.png', __FILE__ );
			$this->has_fields         = false;
			$this->method_title       = __( 'Orcus', 'orcus-woo' );
			$this->method_description = __( 'Orcus for WooCommerce', 'orcus-woo' );
			$this->webhook_url_string = esc_url( get_site_url() . '/wc-api/' . $this->id );

			$this->init_form_fields();

			$this->init_settings();
			$this->title          = $this->get_option( 'title' );
			$this->description    = $this->get_option( 'description' );
			$this->access_key     = $this->get_option( 'access_key' );
			$this->secret_key     = $this->get_option( 'secret_key' );
			$this->webhook_secret = $this->get_option( 'webhook_secret' );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			) );
			add_action( 'woocommerce_api_orcus', array( $this, 'webhook' ) );
			add_filter( 'plugin_action_links_' . ORCUS_WOO_PLUGIN_BASEPATH, array( $this, 'actionLinks' ), 10, 5 );
		}

		public function init_form_fields() {
			$this->form_fields = apply_filters( 'orcus_woo_fields', array(
				'enabled'        => array(
					'title'       => __( 'Enable/Disable', 'orcus-woo' ),
					'label'       => __( 'Enable Orcus', 'orcus-woo' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'yes'
				),
				'title'          => array(
					'title'       => __( 'Title', 'orcus-woo' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'orcus-woo' ),
					'default'     => __( 'Orcus', 'orcus-woo' ),
					'desc_tip'    => true,
				),
				'description'    => array(
					'title'       => __( 'Description', 'orcus-woo' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'orcus-woo' ),
					'default'     => __( 'Pay with Orcus', 'orcus-woo' ),
					'desc_tip'    => true,
				),
				'access_key'     => array(
					'title'       => __( 'Access key', 'orcus-woo' ),
					'type'        => 'text',
					'description' => __( 'Get your access key from <a href="https://dashboard.orcus.com.bd/developers" target="_blank" rel="noreferrer>Orcus Dashboard</a>.', 'orcus-woo' ),
				),
				'secret_key'     => array(
					'title'       => __( 'Secret key', 'orcus-woo' ),
					'type'        => 'password',
					'description' => __( 'Get your secret key from <a href="https://dashboard.orcus.com.bd/developers" target="_blank" rel="noreferrer>Orcus Dashboard</a>.', 'orcus-woo' ),
				),
				'webhook_url'    => array(
					'type'        => 'text',
					'title'       => __( 'Webhook URL', 'orcus-woo' ),
					'default'     => sprintf(
						'%s',
						esc_url( get_site_url() . '/wc-api/' . $this->id )
					),
					'class'       => 'orcus-woo-webhook-url',
					'description' => __( 'Add this webhook <code>' . $this->webhook_url_string . '</code> on your Orcus Dashboard. <a href="https://dashboard.orcus.com.bd/developers/webhooks" target="_blank" rel="noreferrer>Click here to add</a>', 'orcus-woo' ),
				),
				'webhook_secret' => array(
					'title'       => __( 'Webhook secret', 'orcus-woo' ),
					'type'        => 'password',
					'description' => __( 'The webhook secret is used to authenticate webhooks sent from Orcus. Get it from your <a href="https://dashboard.orcus.com.bd/developers/webhooks" target="_blank" rel="noreferrer>Orcus Dashboard</a>.', 'orcus-woo' ),
				),
			) );
		}

		public function process_payment( $order_id ) {
			global $woocommerce;

			$order    = wc_get_order( $order_id );
			$products = array();

			foreach ( $order->get_items() as $item ) {
				$product    = $item->get_product();
				$products[] = array(
					'quantity'   => $item->get_quantity(),
					'price_data' => array(
						'unit_amount'  => $product->get_price() * 100,
						'product_data' => array(
							'name'        => $product->get_name(),
							'images'      => array( wp_get_attachment_url( $product->get_image_id() ) ),
							'description' => $product->get_description()
						)
					)
				);
			}

			foreach ( $order->get_coupon_codes() as $coupon ) {
				$coupon     = new WC_Coupon( $coupon );
				$products[] = array(
					'quantity'   => 1,
					'price_data' => array(
						'unit_amount'  => - ( $coupon->get_amount() * 100 ),
						'product_data' => array(
							'name' => "Coupon: " . $coupon->get_code(),
						)
					)
				);
			}

			if ( empty( $products ) ) {
				$products[] = array(
					'quantity'   => 1,
					'price_data' => array(
						'unit_amount'  => $order->total * 100,
						'product_data' => array(
							'name'        => 'Payment'
						)
					)
				);
			}

			$first_name_last_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
			$customer_name        = !empty( trim( $first_name_last_name ) ) ? $first_name_last_name : 'Guest';

			$payload = array(
				'line_items'  => $products,
				'customer'    => array(
					'name'    => $customer_name,
					'email'   => $order->get_billing_email(),
					'phone'   => $order->get_billing_phone(),
					'address' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
					'city'    => $order->get_billing_city()
				),
				'meta_data'   => array(
					'order_id' => $order_id
				),
				'success_url' => $this->get_return_url( $order ),
				'cancel_url'  => $order->get_cancel_order_url(),
			);

			$response = wp_remote_post(
				'https://api.orcus.com.bd/api/checkout/session', array(
					'method'  => 'POST',
					'headers' => array(
						'content-type'      => 'application/json',
						'x-auth-access-key' => $this->access_key,
						'x-auth-secret-key' => $this->secret_key
					),
					'body'    => json_encode( $payload ),
					'timeout' => 60,
				)
			);

			if ( is_wp_error( $response ) ) {
				wc_add_notice( 'Connection error.', 'error' );

				return;
			}

			var_dump( $response );

			if ( $response['response']['code'] != 200 ) {
				wc_add_notice( 'Gateway error.', 'error' );

				return;
			}

			$body = json_decode( $response['body'], true );

			return array(
				'result'   => 'success',
				'redirect' => $body['url']
			);
		}

		public function webhook() {
			$payload = file_get_contents( 'php://input' );
			$headers = getallheaders();

			try {
				$wh   = new \Svix\Webhook( $this->webhook_secret );
				$data = $wh->verify( $payload, [
					'svix-id'        => isset( $headers['svix-id'] ) ? $headers['svix-id'] : $headers['Svix-Id'],
					'svix-timestamp' =>
						isset( $headers['svix-timestamp'] ) ? $headers['svix-timestamp'] : $headers['Svix-Timestamp'],
					'svix-signature' =>
						isset( $headers['svix-signature'] ) ? $headers['svix-signature'] : $headers['Svix-Signature'],
				] );

				$session_id = $data['data']['id'];
				$tiny_tag   = $data['data']['tiny_tag'];

				$verify_response = wp_remote_post(
					"https://api.orcus.com.bd/api/checkout/session/$session_id", array(
						'method'  => 'POST',
						'headers' => array(
							'content-type'      => 'application/json',
							'x-auth-access-key' => $this->access_key,
							'x-auth-secret-key' => $this->secret_key
						),
						'timeout' => 60,
					)
				);

				$verify_body = json_decode( $verify_response['body'], true );

				$order_id        = $verify_body['meta_data']['order_id'];
				$status          = $verify_body['payment_status'];
				$response_amount = $verify_body['amount_total'];

				if ( $status != 'SUCCEEDED' ) {
					$order->add_order_note(
						'Payment failed with Orcus. Tiny tag: ' . $tiny_tag . ' Status: ' . $status
					);
					die();
				}

				$order        = wc_get_order( $order_id );
				$order_amount = $order->get_total() * 100;

				if ( $order_amount != $response_amount ) {
					$order->add_order_note(
						'Payment failed with Orcus. Tiny tag: ' . $tiny_tag . ' Amount mismatch.'
					);
					die();
				}

				$order->reduce_order_stock();
				$order->payment_complete();
				$order->add_order_note(
					'Payment completed with Orcus. Tiny tag: ' . $tiny_tag
				);
			} catch ( Exception $e ) {
				echo $e;
				die();
			}
		}

		final public function actionLinks( array $links ): array {
			if ( current_user_can( 'manage_woocommerce' ) ) {
				$admin_setting_path = 'admin.php?page=wc-settings&tab=checkout&section=';
				$config_url         = esc_url( admin_url( $admin_setting_path . ORCUS_WOO_PLUGIN_SLUG ) );
				$plugin_links       = array(
					'<a href="' . esc_attr( $config_url ) . '">Settings</a>',
				);

				return array_merge( $plugin_links, $links );
			}

			return $links;
		}
	}
}

add_filter( 'woocommerce_payment_gateways', 'orcus_woo_add_gateway' );
add_action( 'admin_enqueue_scripts', 'orcus_woo_css' );

function orcus_woo_add_gateway( $gateways ) {
	$gateways[] = 'WC_Orcus';

	return $gateways;
}

function orcus_woo_css() {
	wp_enqueue_style( 'orcus-woo-css', plugins_url( 'assets/css/styles.css', __FILE__ ) );
}
