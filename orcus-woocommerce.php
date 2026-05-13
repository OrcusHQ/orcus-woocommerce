<?php

/*
 * Plugin Name: OrcusPay for WooCommerce
 * Description: Accept payments with OrcusPay Checkout for WooCommerce.
 * Plugin URI: https://dash.orcuspay.com
 * Author: OrcusPay
 * Author URI: https://dash.orcuspay.com
 * Version: 0.2.0
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

define( 'ORCUS_WOO_VERSION', '0.2.0' );
define( 'ORCUS_WOO_PLUGIN_SLUG', 'orcus' );
define( 'ORCUS_WOO_PLUGIN_BASEPATH', plugin_basename( __FILE__ ) );
define( 'ORCUS_WOO_DEFAULT_API_BASE_URL', 'https://brain.orcuspay.com/api/v1' );

add_action( 'before_woocommerce_init', 'orcus_woo_declare_compatibility' );
function orcus_woo_declare_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
}

add_action( 'plugins_loaded', 'orcus_woo_init', 11 );

function orcus_woo_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class WC_Orcus extends WC_Payment_Gateway {
		protected $access_key;
		protected $secret_key;
		protected $webhook_secret;
		protected $webhook_url_string;

		public function __construct() {
			$this->id                 = ORCUS_WOO_PLUGIN_SLUG;
			$this->icon               = plugins_url( 'assets/images/orcus.png', __FILE__ );
			$this->has_fields         = false;
			$this->method_title       = __( 'OrcusPay', 'orcus-woo' );
			$this->method_description = __( 'Accept payments through OrcusPay Checkout.', 'orcus-woo' );
			$this->supports           = array( 'products' );
			$this->webhook_url_string = WC()->api_request_url( $this->id );

			$this->init_form_fields();
			$this->init_settings();

			$this->title          = $this->get_option( 'title' );
			$this->description    = $this->get_option( 'description' );
			$this->access_key     = trim( (string) $this->get_option( 'access_key' ) );
			$this->secret_key     = trim( (string) $this->get_option( 'secret_key' ) );
			$this->webhook_secret = trim( (string) $this->get_option( 'webhook_secret' ) );

			add_action(
				'woocommerce_update_options_payment_gateways_' . $this->id,
				array( $this, 'process_admin_options' )
			);
			add_action( 'woocommerce_api_orcus', array( $this, 'webhook' ) );
			add_filter( 'plugin_action_links_' . ORCUS_WOO_PLUGIN_BASEPATH, array( $this, 'actionLinks' ), 10, 5 );
		}

		public function init_form_fields() {
			$dashboard_link = '<a href="https://dash.orcuspay.com/developers" target="_blank" rel="noreferrer noopener">OrcusPay Dashboard</a>';
			$webhooks_link  = '<a href="https://dash.orcuspay.com/developers/webhooks" target="_blank" rel="noreferrer noopener">OrcusPay webhooks</a>';

			$this->form_fields = apply_filters( 'orcus_woo_fields', array(
				'enabled'        => array(
					'title'       => __( 'Enable/Disable', 'orcus-woo' ),
					'label'       => __( 'Enable OrcusPay', 'orcus-woo' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'yes',
				),
				'title'          => array(
					'title'       => __( 'Title', 'orcus-woo' ),
					'type'        => 'text',
					'description' => __( 'This controls the title customers see during checkout.', 'orcus-woo' ),
					'default'     => __( 'OrcusPay', 'orcus-woo' ),
					'desc_tip'    => true,
				),
				'description'    => array(
					'title'       => __( 'Description', 'orcus-woo' ),
					'type'        => 'text',
					'description' => __( 'This controls the description customers see during checkout.', 'orcus-woo' ),
					'default'     => __( 'Pay securely with OrcusPay.', 'orcus-woo' ),
					'desc_tip'    => true,
				),
				'access_key'     => array(
					'title'       => __( 'API key', 'orcus-woo' ),
					'type'        => 'text',
					'description' => sprintf(
						/* translators: %s: OrcusPay Dashboard link. */
						__( 'Get your API key from %s.', 'orcus-woo' ),
						$dashboard_link
					),
				),
				'secret_key'     => array(
					'title'       => __( 'API secret', 'orcus-woo' ),
					'type'        => 'password',
					'description' => sprintf(
						/* translators: %s: OrcusPay Dashboard link. */
						__( 'Get your API secret from %s.', 'orcus-woo' ),
						$dashboard_link
					),
				),
				'webhook_url'    => array(
					'type'              => 'text',
					'title'             => __( 'Webhook URL', 'orcus-woo' ),
					'default'           => $this->webhook_url_string,
					'class'             => 'orcus-woo-webhook-url',
					'custom_attributes' => array( 'readonly' => 'readonly' ),
					'description'       => sprintf(
						/* translators: 1: webhook URL, 2: OrcusPay webhooks link. */
						__( 'Add this webhook URL in %2$s: %1$s', 'orcus-woo' ),
						'<code>' . esc_html( $this->webhook_url_string ) . '</code>',
						$webhooks_link
					),
				),
				'webhook_secret' => array(
					'title'       => __( 'Webhook secret', 'orcus-woo' ),
					'type'        => 'password',
					'description' => __( 'Used to verify OrcusPay webhook signatures.', 'orcus-woo' ),
				),
			) );
		}

		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				wc_add_notice( __( 'Invalid order.', 'orcus-woo' ), 'error' );
				return array( 'result' => 'failure' );
			}

			if ( empty( $this->access_key ) || empty( $this->secret_key ) ) {
				wc_add_notice( __( 'OrcusPay is not configured.', 'orcus-woo' ), 'error' );
				return array( 'result' => 'failure' );
			}

			try {
				$payload = $this->build_checkout_payload( $order );
				$session = $this->api_request( 'POST', 'checkout/sessions', $payload );

				$checkout_url = $this->array_get( $session, 'checkout_url' );
				if ( empty( $checkout_url ) ) {
					$checkout_url = $this->array_get( $session, 'url' );
				}

				if ( empty( $checkout_url ) ) {
					throw new Exception( 'OrcusPay did not return a checkout URL.' );
				}

				$payment_id = $this->array_get( $session, 'payment_id' );
				if ( empty( $payment_id ) ) {
					$payment_id = $this->array_get( $session, 'id' );
				}

				if ( ! empty( $payment_id ) ) {
					$order->update_meta_data( '_orcuspay_payment_id', sanitize_text_field( $payment_id ) );
					$order->save();
				}

				$order->update_status( 'pending', __( 'Awaiting OrcusPay payment.', 'orcus-woo' ) );

				return array(
					'result'   => 'success',
					'redirect' => esc_url_raw( $checkout_url ),
				);
			} catch ( Exception $e ) {
				$this->log( 'Checkout error for order ' . $order_id . ': ' . $e->getMessage() );
				wc_add_notice( __( 'Unable to start OrcusPay checkout. Please try again.', 'orcus-woo' ), 'error' );

				return array( 'result' => 'failure' );
			}
		}

		public function webhook() {
			$payload = file_get_contents( 'php://input' );

			try {
				if ( empty( $payload ) ) {
					throw new Exception( 'Empty webhook payload.' );
				}

				$event = $this->verify_webhook_payload( $payload );
				$payment_id = $this->extract_payment_id( $event );
				$result = $this->apply_payment( $payment_id );

				if ( 'successful' === $result['status'] || 'already_paid' === $result['status'] ) {
					status_header( 200 );
					echo 'ok';
					exit;
				}

				status_header( 202 );
				echo esc_html( $result['status'] );
				exit;
			} catch ( Exception $e ) {
				$this->log( 'Webhook error: ' . $e->getMessage() );
				status_header( 400 );
				echo 'Webhook processing failed';
				exit;
			}
		}

		protected function build_checkout_payload( WC_Order $order ) {
			$order_number = $order->get_order_number();
			$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
			if ( empty( $name ) ) {
				$name = __( 'Guest customer', 'orcus-woo' );
			}

			return array(
				'line_items'  => array(
					array(
						'quantity'   => 1,
						'price_data' => array(
							'unit_amount'  => $this->amount_to_paisa( $order->get_total() ),
							'product_data' => array(
								'name'        => sprintf(
									/* translators: %s: WooCommerce order number. */
									__( 'WooCommerce order #%s', 'orcus-woo' ),
									$order_number
								),
								'description' => $this->get_order_description( $order ),
							),
						),
					),
				),
				'customer'    => array(
					'name'    => $name,
					'email'   => (string) $order->get_billing_email(),
					'phone'   => (string) $order->get_billing_phone(),
					'address' => trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() ),
					'city'    => (string) $order->get_billing_city(),
				),
				'meta_data'   => array(
					'source'               => 'woocommerce',
					'woocommerce_order_id' => (string) $order->get_id(),
					'order_id'             => (string) $order->get_id(),
					'order_number'         => (string) $order_number,
					'site_url'             => home_url(),
				),
				'success_url' => add_query_arg(
					array( 'orcuspay_payment_id' => '{CHECKOUT_SESSION_ID}' ),
					$this->get_return_url( $order )
				),
				'cancel_url'  => $order->get_cancel_order_url_raw(),
			);
		}

		protected function get_order_description( WC_Order $order ) {
			$item_names = array();
			foreach ( $order->get_items() as $item ) {
				$item_names[] = $item->get_name();
			}

			return wp_trim_words( implode( ', ', $item_names ), 20, '...' );
		}

		protected function apply_payment( $payment_id ) {
			$session = $this->api_request( 'GET', 'checkout/sessions/' . rawurlencode( $payment_id ) );
			$meta = $this->array_get( $session, 'meta_data', array() );
			if ( ! is_array( $meta ) ) {
				$meta = array();
			}

			$order_id = absint( $this->array_get( $meta, 'woocommerce_order_id' ) );
			if ( ! $order_id ) {
				$order_id = $this->find_order_id_by_payment_id( $payment_id );
			}

			if ( ! $order_id ) {
				return array( 'status' => 'missing_order_id' );
			}

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return array( 'status' => 'order_not_found' );
			}

			$status = strtoupper( (string) $this->array_get( $session, 'payment_status', $this->array_get( $session, 'status', '' ) ) );
			if ( 'SUCCEEDED' !== $status ) {
				$order->add_order_note(
					sprintf(
						/* translators: %s: OrcusPay payment status. */
						__( 'OrcusPay payment is not completed. Status: %s', 'orcus-woo' ),
						$status
					)
				);
				return array( 'status' => 'not_completed' );
			}

			$amount_paisa = $this->array_get( $session, 'amount_paisa' );
			if ( null === $amount_paisa ) {
				$amount_paisa = $this->array_get( $session, 'amount_total' );
			}

			if ( null === $amount_paisa ) {
				return array( 'status' => 'missing_amount' );
			}

			if ( (int) $amount_paisa !== $this->amount_to_paisa( $order->get_total() ) ) {
				$order->add_order_note( __( 'OrcusPay payment amount mismatch.', 'orcus-woo' ) );
				return array( 'status' => 'amount_mismatch' );
			}

			if ( $order->is_paid() ) {
				return array( 'status' => 'already_paid' );
			}

			$transaction_id = $this->array_get( $session, 'transaction_id' );
			if ( empty( $transaction_id ) ) {
				$transaction_id = $payment_id;
			}

			$order->update_meta_data( '_orcuspay_payment_id', sanitize_text_field( $payment_id ) );
			$order->payment_complete( sanitize_text_field( $transaction_id ) );
			$order->add_order_note(
				sprintf(
					/* translators: %s: OrcusPay payment id. */
					__( 'Payment completed with OrcusPay. Payment ID: %s', 'orcus-woo' ),
					esc_html( $payment_id )
				)
			);
			$order->save();

			return array( 'status' => 'successful' );
		}

		protected function api_request( $method, $endpoint, array $payload = array() ) {
			$base_url = apply_filters( 'orcus_woo_api_base_url', ORCUS_WOO_DEFAULT_API_BASE_URL );
			$url = trailingslashit( rtrim( $base_url, '/' ) ) . ltrim( $endpoint, '/' );

			$args = array(
				'method'  => strtoupper( $method ),
				'timeout' => 45,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
					'x-api-key'    => $this->access_key,
					'x-api-secret' => $this->secret_key,
				),
			);

			if ( ! empty( $payload ) && 'GET' !== strtoupper( $method ) ) {
				$args['body'] = wp_json_encode( $payload );
			}

			$response = wp_remote_request( esc_url_raw( $url ), $args );
			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}

			$status = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $body ) ) {
				throw new Exception( 'OrcusPay returned an invalid response.' );
			}

			if ( $status < 200 || $status >= 300 ) {
				$message = $this->array_get( $body, 'error.message', 'OrcusPay API request failed.' );
				throw new Exception( $message );
			}

			if ( array_key_exists( 'data', $body ) ) {
				return $body['data'];
			}

			return $body;
		}

		protected function verify_webhook_payload( $payload ) {
			if ( empty( $this->webhook_secret ) ) {
				throw new Exception( 'Webhook secret is not configured.' );
			}

			$svix_id = $this->get_header( 'svix-id' );
			$svix_timestamp = $this->get_header( 'svix-timestamp' );
			$svix_signature = $this->get_header( 'svix-signature' );

			if ( empty( $svix_id ) || empty( $svix_timestamp ) || empty( $svix_signature ) ) {
				throw new Exception( 'Missing Svix signature headers.' );
			}

			if ( abs( time() - (int) $svix_timestamp ) > 300 ) {
				throw new Exception( 'Webhook timestamp is outside the allowed window.' );
			}

			$secret = preg_replace( '/^whsec_/', '', $this->webhook_secret );
			$secret = strtr( $secret, '-_', '+/' );
			$secret .= str_repeat( '=', ( 4 - strlen( $secret ) % 4 ) % 4 );
			$key = base64_decode( $secret, true );
			if ( false === $key ) {
				throw new Exception( 'Invalid webhook secret.' );
			}

			$signed_payload = $svix_id . '.' . $svix_timestamp . '.' . $payload;
			$expected = base64_encode( hash_hmac( 'sha256', $signed_payload, $key, true ) );
			$signatures = preg_split( '/\s+/', trim( $svix_signature ) );

			foreach ( $signatures as $signature ) {
				$parts = explode( ',', $signature, 2 );
				if ( count( $parts ) === 2 && 'v1' === $parts[0] && hash_equals( $expected, $parts[1] ) ) {
					$event = json_decode( $payload, true );
					if ( ! is_array( $event ) ) {
						throw new Exception( 'Invalid webhook JSON payload.' );
					}

					return $event;
				}
			}

			throw new Exception( 'Invalid webhook signature.' );
		}

		protected function extract_payment_id( array $event ) {
			$payment_id = $this->array_get( $event, 'data.id' );
			if ( empty( $payment_id ) ) {
				$payment_id = $this->array_get( $event, 'data.payment_id' );
			}

			if ( empty( $payment_id ) ) {
				throw new Exception( 'Webhook payload is missing payment id.' );
			}

			return sanitize_text_field( $payment_id );
		}

		protected function find_order_id_by_payment_id( $payment_id ) {
			$orders = wc_get_orders( array(
				'limit'      => 1,
				'return'     => 'ids',
				'meta_key'   => '_orcuspay_payment_id',
				'meta_value' => sanitize_text_field( $payment_id ),
			) );

			return ! empty( $orders ) ? absint( $orders[0] ) : 0;
		}

		protected function get_header( $name ) {
			$server_key = 'HTTP_' . strtoupper( str_replace( '-', '_', $name ) );
			if ( isset( $_SERVER[ $server_key ] ) ) {
				return sanitize_text_field( wp_unslash( $_SERVER[ $server_key ] ) );
			}

			return '';
		}

		protected function amount_to_paisa( $amount ) {
			return (int) round( (float) $amount * 100 );
		}

		protected function array_get( array $array, $path, $default = null ) {
			$segments = explode( '.', $path );
			$value = $array;

			foreach ( $segments as $segment ) {
				if ( is_array( $value ) && array_key_exists( $segment, $value ) ) {
					$value = $value[ $segment ];
					continue;
				}

				return $default;
			}

			return $value;
		}

		protected function log( $message ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->info( $message, array( 'source' => 'orcuspay' ) );
				return;
			}

			error_log( 'OrcusPay WooCommerce: ' . $message );
		}

		final public function actionLinks( array $links ): array {
			if ( current_user_can( 'manage_woocommerce' ) ) {
				$admin_setting_path = 'admin.php?page=wc-settings&tab=checkout&section=';
				$config_url         = esc_url( admin_url( $admin_setting_path . ORCUS_WOO_PLUGIN_SLUG ) );
				$plugin_links       = array(
					'<a href="' . esc_attr( $config_url ) . '">' . esc_html__( 'Settings', 'orcus-woo' ) . '</a>',
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
	wp_enqueue_style( 'orcus-woo-css', plugins_url( 'assets/css/styles.css', __FILE__ ), array(), ORCUS_WOO_VERSION );
}
