<?php
/**
 * Uninstall PostHog for WordPress.
 *
 * @package PostHog_For_WP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'posthog_for_wp_enabled' );
delete_option( 'posthog_for_wp_api_key' );
delete_option( 'posthog_for_wp_host' );
delete_option( 'posthog_for_wp_distinct_id_source' );

$defaults = array( 'page_view', 'product_viewed', 'add_to_cart', 'cart_viewed', 'checkout_started', 'order_completed', 'search', 'form_submitted' );
foreach ( $defaults as $key ) {
	delete_option( 'posthog_for_wp_event_' . $key );
}
