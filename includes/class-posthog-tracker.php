<?php
/**
 * PostHog WooCommerce event tracker.
 *
 * @package PostHog_For_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostHog_For_WP_Tracker
 */
class PostHog_For_WP_Tracker {

	/**
	 * PostHog API instance.
	 *
	 * @var PostHog_For_WP_API
	 */
	private $api;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api = new PostHog_For_WP_API();

		if ( ! $this->api->is_ready() ) {
			return;
		}

		// Add to cart.
		add_action( 'woocommerce_add_to_cart', array( $this, 'track_add_to_cart' ), 10, 6 );

		// Checkout started.
		add_action( 'woocommerce_checkout_init', array( $this, 'track_checkout_started' ) );

		// Order completed.
		add_action( 'woocommerce_thankyou', array( $this, 'track_order_completed' ), 5, 1 );

		// Product viewed.
		add_action( 'woocommerce_before_single_product', array( $this, 'track_product_viewed' ) );

		// Cart viewed.
		add_action( 'template_redirect', array( $this, 'track_cart_viewed' ) );

		// Page view (WooCommerce pages).
		add_action( 'template_redirect', array( $this, 'track_page_view' ), 5 );

		// Search.
		add_action( 'template_redirect', array( $this, 'track_search' ) );
	}

	/**
	 * Get the distinct ID for the current user.
	 *
	 * @return string
	 */
	private function get_distinct_id() {
		$source = get_option( 'posthog_for_wp_distinct_id_source', 'user_id' );

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( 'email' === $source && ! empty( $user->user_email ) ) {
				return $user->user_email;
			}
			return (string) $user->ID;
		}

		// Guest: use session ID.
		if ( function_exists( 'WC' ) && WC()->session ) {
			$sid = WC()->session->get_customer_id();
			if ( ! empty( $sid ) ) {
				return 'guest_' . $sid;
			}
		}

		// Fallback: create/use anonymous cookie-based ID.
		if ( empty( $_COOKIE['posthog_wp_anon_id'] ) ) {
			$anon_id = 'anon_' . wp_generate_password( 32, false );
			if ( ! headers_sent() ) {
				setcookie( 'posthog_wp_anon_id', $anon_id, time() + ( 365 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			}
			return $anon_id;
		}

		return 'anon_' . sanitize_text_field( wp_unslash( $_COOKIE['posthog_wp_anon_id'] ) );
	}

	/**
	 * Get custom event name by key.
	 *
	 * @param string $key Event key.
	 * @return string
	 */
	private function get_event_name( $key ) {
		$defaults = posthog_for_wp_get_default_event_names();
		return get_option( 'posthog_for_wp_event_' . $key, $defaults[ $key ] ?? $key );
	}

	/**
	 * Track add to cart.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id    Product ID.
	 * @param int    $quantity      Quantity.
	 * @param int    $variation_id  Variation ID.
	 * @param array  $variation     Variation data.
	 * @param array  $cart_item_data Cart item data.
	 */
	public function track_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		$product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $product ) {
			return;
		}

		$properties = array(
			'product_id'   => $product_id,
			'quantity'     => $quantity,
			'product_name' => $product->get_name(),
			'price'        => (float) $product->get_price(),
			'$current_url' => $this->get_current_url(),
		);

		if ( $variation_id ) {
			$properties['variation_id'] = $variation_id;
		}

		$this->api->capture(
			$this->get_distinct_id(),
			$this->get_event_name( 'add_to_cart' ),
			$properties
		);
	}

	/**
	 * Track checkout started.
	 */
	public function track_checkout_started() {
		// Avoid duplicate events on AJAX.
		static $fired = false;
		if ( $fired ) {
			return;
		}
		if ( wp_doing_ajax() ) {
			return;
		}
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		$fired = true;

		$cart_total = 0;
		$item_count = 0;
		if ( WC()->cart ) {
			$cart_total = (float) WC()->cart->get_cart_total();
			$item_count = WC()->cart->get_cart_contents_count();
		}

		$this->api->capture(
			$this->get_distinct_id(),
			$this->get_event_name( 'checkout_started' ),
			array(
				'cart_total'   => $cart_total,
				'item_count'   => $item_count,
				'$current_url' => $this->get_current_url(),
			)
		);
	}

	/**
	 * Track order completed.
	 *
	 * @param int $order_id Order ID.
	 */
	public function track_order_completed( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$source    = get_option( 'posthog_for_wp_distinct_id_source', 'user_id' );
		$distinct  = $this->get_distinct_id();
		$order_uid = $order->get_user_id();
		if ( $order_uid && 'email' === $source ) {
			$user = get_user_by( 'id', $order_uid );
			if ( $user && ! empty( $user->user_email ) ) {
				$distinct = $user->user_email;
			}
		} elseif ( $order_uid ) {
			$distinct = (string) $order_uid;
		} else {
			$distinct = $order->get_billing_email() ?: $distinct;
		}

		$this->api->capture(
			$distinct,
			$this->get_event_name( 'order_completed' ),
			array(
				'order_id'     => $order_id,
				'total'        => (float) $order->get_total(),
				'item_count'   => $order->get_item_count(),
				'currency'     => $order->get_currency(),
				'$current_url' => $this->get_current_url(),
			)
		);
	}

	/**
	 * Track product viewed.
	 */
	public function track_product_viewed() {
		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		$this->api->capture(
			$this->get_distinct_id(),
			$this->get_event_name( 'product_viewed' ),
			array(
				'product_id'   => $product->get_id(),
				'product_name' => $product->get_name(),
				'price'        => (float) $product->get_price(),
				'$current_url' => $this->get_current_url(),
			)
		);
	}

	/**
	 * Track cart viewed.
	 */
	public function track_cart_viewed() {
		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}

		static $fired = false;
		if ( $fired ) {
			return;
		}
		$fired = true;

		$cart_total = 0;
		$item_count = 0;
		if ( WC()->cart ) {
			$cart_total = (float) WC()->cart->get_cart_total();
			$item_count = WC()->cart->get_cart_contents_count();
		}

		$this->api->capture(
			$this->get_distinct_id(),
			$this->get_event_name( 'cart_viewed' ),
			array(
				'cart_total'   => $cart_total,
				'item_count'   => $item_count,
				'$current_url' => $this->get_current_url(),
			)
		);
	}

	/**
	 * Track WooCommerce page views.
	 * Fires on shop and account pages. Product/cart/checkout have dedicated events.
	 */
	public function track_page_view() {
		if ( ! function_exists( 'is_woocommerce' ) || ! is_woocommerce() ) {
			return;
		}

		// Skip pages with dedicated events to avoid duplicates.
		if ( ( function_exists( 'is_product' ) && is_product() )
			|| ( function_exists( 'is_cart' ) && is_cart() )
			|| ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
			return;
		}

		static $fired = false;
		if ( $fired ) {
			return;
		}
		$fired = true;

		$page_type = ( function_exists( 'is_account_page' ) && is_account_page() ) ? 'account' : 'shop';

		$this->api->capture(
			$this->get_distinct_id(),
			$this->get_event_name( 'page_view' ),
			array(
				'$current_url' => $this->get_current_url(),
				'page_type'    => $page_type,
			)
		);
	}

	/**
	 * Track search.
	 */
	public function track_search() {
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		if ( empty( $search ) ) {
			return;
		}

		// Only on WooCommerce product search.
		if ( ! function_exists( 'is_shop' ) && ! function_exists( 'is_search' ) ) {
			return;
		}
		if ( ! ( ( function_exists( 'is_shop' ) && is_shop() ) || ( function_exists( 'is_search' ) && is_search() ) ) ) {
			return;
		}

		static $fired = false;
		if ( $fired ) {
			return;
		}
		$fired = true;

		$this->api->capture(
			$this->get_distinct_id(),
			$this->get_event_name( 'search' ),
			array(
				'search_query' => $search,
				'$current_url' => $this->get_current_url(),
			)
		);
	}

	/**
	 * Get current URL.
	 *
	 * @return string
	 */
	private function get_current_url() {
		if ( isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ) {
			$scheme = is_ssl() ? 'https' : 'http';
			return $scheme . '://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}
		return '';
	}
}
