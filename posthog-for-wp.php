<?php
/**
 * Plugin Name: PostHog for WordPress
 * Plugin URI: https://github.com/ashawkat/posthog-for-wordpress
 * Description: Send WooCommerce events to PostHog with customizable event names. Integrates with PostHog for unified analytics across platforms.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Launchtitans Team
 * Author URI: https://launchtitans.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: posthog-for-wp
 * Domain Path: /languages
 * WC requires at least: 7.0
 * WC tested up to: 9.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'POSTHOG_FOR_WP_VERSION', '1.0.0' );
define( 'POSTHOG_FOR_WP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Declare HPOS (High-Performance Order Storage) compatibility.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
define( 'POSTHOG_FOR_WP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'POSTHOG_FOR_WP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Get default PostHog event names.
 *
 * @return array<string, string>
 */
function posthog_for_wp_get_default_event_names() {
	return array(
		'page_view'        => 'wc_page_view',
		'product_viewed'   => 'wc_product_viewed',
		'add_to_cart'      => 'wc_add_to_cart',
		'cart_viewed'      => 'wc_cart_viewed',
		'checkout_started' => 'wc_checkout_started',
		'order_completed'  => 'wc_order_completed',
		'search'           => 'wc_search',
		'form_submitted'   => 'form_submitted',
	);
}

/**
 * Check if WooCommerce is active.
 */
function posthog_for_wp_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Initialize the plugin.
 */
function posthog_for_wp_init() {
	if ( ! posthog_for_wp_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'posthog_for_wp_woocommerce_required_notice' );
		return;
	}

	require_once POSTHOG_FOR_WP_PLUGIN_DIR . 'includes/class-posthog-api.php';
	require_once POSTHOG_FOR_WP_PLUGIN_DIR . 'includes/class-posthog-tracker.php';
	require_once POSTHOG_FOR_WP_PLUGIN_DIR . 'includes/class-posthog-forms-tracker.php';

	if ( is_admin() ) {
		require_once POSTHOG_FOR_WP_PLUGIN_DIR . 'includes/class-posthog-admin.php';
		new PostHog_For_WP_Admin();
	}

	new PostHog_For_WP_Tracker();
	new PostHog_For_WP_Forms_Tracker();
}
add_action( 'plugins_loaded', 'posthog_for_wp_init' );

/**
 * Admin notice when WooCommerce is not active.
 */
function posthog_for_wp_woocommerce_required_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'PostHog for WordPress requires WooCommerce to be installed and active.', 'posthog-for-wp' ); ?></p>
	</div>
	<?php
}
