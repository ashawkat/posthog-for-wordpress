<?php
/**
 * PostHog API client for sending events.
 *
 * @package PostHog_For_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostHog_For_WP_API
 */
class PostHog_For_WP_API {

	/**
	 * PostHog project token.
	 *
	 * @var string
	 */
	private $api_key = '';

	/**
	 * PostHog host URL.
	 *
	 * @var string
	 */
	private $host = '';

	/**
	 * Whether the integration is enabled.
	 *
	 * @var bool
	 */
	private $enabled = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_key = get_option( 'posthog_for_wp_api_key', '' );
		$this->host    = rtrim( get_option( 'posthog_for_wp_host', 'https://us.i.posthog.com' ), '/' );
		$this->enabled = (bool) get_option( 'posthog_for_wp_enabled', false );
	}

	/**
	 * Check if the API is configured and enabled.
	 *
	 * @return bool
	 */
	public function is_ready() {
		return $this->enabled && ! empty( $this->api_key ) && ! empty( $this->host );
	}

	/**
	 * Capture a single event.
	 *
	 * @param string       $distinct_id Unique identifier for the user.
	 * @param string       $event       Event name.
	 * @param array        $properties  Optional. Event properties.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function capture( $distinct_id, $event, $properties = array() ) {
		if ( ! $this->is_ready() ) {
			return false;
		}

		if ( empty( $distinct_id ) || empty( $event ) ) {
			return new WP_Error( 'posthog_invalid_params', __( 'distinct_id and event are required.', 'posthog-for-wp' ) );
		}

		$payload = array(
			'api_key'     => $this->api_key,
			'event'       => sanitize_text_field( $event ),
			'distinct_id' => sanitize_text_field( (string) $distinct_id ),
			'properties'  => array_merge(
				array(
					'$source' => 'posthog-for-wp',
				),
				$properties
			),
		);

		$url  = $this->host . '/i/v0/e/';
		$args = array(
			'method'      => 'POST',
			'timeout'     => 5,
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'body'        => wp_json_encode( $payload ),
			'blocking'    => false,
			'data_format' => 'body',
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'PostHog for WP: ' . $response->get_error_message() );
			}
			return $response;
		}

		return true;
	}

	/**
	 * Capture multiple events in a batch.
	 *
	 * @param string $distinct_id Unique identifier for the user.
	 * @param array  $events      Array of event data: [ ['event' => 'name', 'properties' => []] ].
	 * @return bool|WP_Error
	 */
	public function capture_batch( $distinct_id, $events ) {
		if ( ! $this->is_ready() ) {
			return false;
		}

		if ( empty( $distinct_id ) || empty( $events ) ) {
			return new WP_Error( 'posthog_invalid_params', __( 'distinct_id and events are required.', 'posthog-for-wp' ) );
		}

		$batch = array();
		foreach ( $events as $ev ) {
			$batch[] = array(
				'event'      => sanitize_text_field( $ev['event'] ),
				'properties' => array_merge(
					array(
						'distinct_id' => (string) $distinct_id,
						'$source'     => 'posthog-for-wp',
					),
					isset( $ev['properties'] ) ? $ev['properties'] : array()
				),
			);
		}

		$payload = array(
			'api_key' => $this->api_key,
			'batch'   => $batch,
		);

		$url  = $this->host . '/batch/';
		$args = array(
			'method'      => 'POST',
			'timeout'     => 10,
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'body'        => wp_json_encode( $payload ),
			'blocking'    => false,
			'data_format' => 'body',
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'PostHog for WP: ' . $response->get_error_message() );
			}
			return $response;
		}

		return true;
	}
}
