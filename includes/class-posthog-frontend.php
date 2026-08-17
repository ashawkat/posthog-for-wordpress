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
		add_action( 'wp_head', array( $this, 'print_js_snippet' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_session_sync' ) );
	}

	/**
	 * Print the official posthog-js snippet in the document head.
	 *
	 * Session Replay requires this browser SDK. The PHP plugin cannot record sessions.
	 */
	public function print_js_snippet() {
		if ( is_admin() ) {
			return;
		}

		if ( ! $this->is_js_enabled() ) {
			return;
		}

		$api = new PostHog_For_WP_API();
		if ( ! $api->is_ready() ) {
			return;
		}

		$config = array(
			'api_host'                   => $api->get_host(),
			'person_profiles'            => 'identified_only',
			'capture_pageview'           => true,
			'capture_pageleave'          => true,
			'disable_session_recording'  => false,
			'tracing_headers'            => $this->get_tracing_hosts(),
		);

		$identify_id = $this->get_identify_distinct_id();
		?>
		<script>
		!function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+" (stub)"},o="init capture register register_once register_for_session unregister unregister_for_session getFeatureFlag getFeatureFlagPayload isFeatureFlagEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSessionId getSurveys getActiveMatchingSurveys renderSurvey canRenderSurvey getNextSurveyStep identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty createPersonProfile opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing clear_opt_in_out_capturing debug".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
		if ( ! window.posthog || ! window.posthog.__SV ) { return; }
		posthog.init(<?php echo wp_json_encode( $api->get_api_key() ); ?>, <?php echo wp_json_encode( $config ); ?>);
		<?php if ( $identify_id ) : ?>
		posthog.identify(<?php echo wp_json_encode( $identify_id ); ?>);
		<?php endif; ?>
		</script>
		<?php
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

	/**
	 * Whether the JavaScript SDK should be loaded.
	 *
	 * @return bool
	 */
	private function is_js_enabled() {
		return (bool) get_option( 'posthog_for_wp_js_enabled', true );
	}

	/**
	 * Hostnames that should receive PostHog tracing headers on AJAX requests.
	 *
	 * @return array
	 */
	private function get_tracing_hosts() {
		$hosts = array();

		foreach ( array( home_url(), site_url() ) as $url ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( $host ) {
				$hosts[] = $host;
			}
		}

		return array_values( array_unique( array_filter( $hosts ) ) );
	}

	/**
	 * Distinct ID used by posthog.identify() so frontend matches PHP events.
	 *
	 * @return string
	 */
	private function get_identify_distinct_id() {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user   = wp_get_current_user();
		$source = get_option( 'posthog_for_wp_distinct_id_source', 'user_id' );

		if ( 'email' === $source && ! empty( $user->user_email ) ) {
			return $user->user_email;
		}

		return (string) $user->ID;
	}
}
