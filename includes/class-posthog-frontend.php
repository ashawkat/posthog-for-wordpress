<?php
/**
 * PostHog frontend helpers.
 *
 * @package PostHog_For_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostHog_For_WP_Frontend
 */
class PostHog_For_WP_Frontend {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_session_sync' ) );
	}

	/**
	 * Enqueue script that syncs posthog-js session ID to a cookie.
	 */
	public function enqueue_session_sync() {
		if ( is_admin() ) {
			return;
		}

		$api = new PostHog_For_WP_API();
		if ( ! $api->is_ready() ) {
			return;
		}

		wp_enqueue_script(
			'posthog-for-wp-session-sync',
			POSTHOG_FOR_WP_PLUGIN_URL . 'assets/js/session-sync.js',
			array(),
			POSTHOG_FOR_WP_VERSION,
			true
		);
	}
}
