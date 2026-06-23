<?php
/**
 * PostHog session ID helper for server-side events.
 *
 * @package PostHog_For_WP
 * @see https://posthog.com/docs/data/sessions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostHog_For_WP_Session
 */
class PostHog_For_WP_Session {

	const COOKIE_NAME    = 'posthog_wp_session_id';
	const COOKIE_TS_NAME = 'posthog_wp_session_ts';

	/**
	 * PostHog default session timeout (30 minutes).
	 */
	const SESSION_TIMEOUT = 1800;

	/**
	 * Get the PostHog session ID for the current request.
	 *
	 * @return string
	 */
	public static function get_session_id() {
		$session_id = self::get_from_tracing_header();
		if ( $session_id ) {
			return $session_id;
		}

		$session_id = self::get_from_sync_cookie();
		if ( $session_id ) {
			return $session_id;
		}

		$session_id = self::get_from_posthog_cookie();
		if ( $session_id ) {
			return $session_id;
		}

		return self::get_or_create_server_session_id();
	}

	/**
	 * Read session ID from PostHog tracing header (posthog-js tracing_headers).
	 *
	 * @return string
	 */
	private static function get_from_tracing_header() {
		if ( empty( $_SERVER['HTTP_X_POSTHOG_SESSION_ID'] ) ) {
			return '';
		}

		return self::sanitize_session_id( wp_unslash( $_SERVER['HTTP_X_POSTHOG_SESSION_ID'] ) );
	}

	/**
	 * Read session ID synced from posthog-js via our frontend script.
	 *
	 * @return string
	 */
	private static function get_from_sync_cookie() {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return '';
		}

		return self::sanitize_session_id( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
	}

	/**
	 * Parse session ID from posthog-js identity cookie (ph_*_posthog).
	 *
	 * @return string
	 */
	private static function get_from_posthog_cookie() {
		if ( empty( $_COOKIE ) || ! is_array( $_COOKIE ) ) {
			return '';
		}

		foreach ( $_COOKIE as $name => $value ) {
			if ( ! is_string( $name ) || ! preg_match( '/^ph_.*_posthog$/', $name ) ) {
				continue;
			}

			$session_id = self::parse_posthog_cookie_session_id( $value );
			if ( $session_id ) {
				return $session_id;
			}
		}

		return '';
	}

	/**
	 * Parse $sesid from a PostHog persistence cookie value.
	 *
	 * @param string $raw_cookie Raw cookie value.
	 * @return string
	 */
	private static function parse_posthog_cookie_session_id( $raw_cookie ) {
		$candidates = array( $raw_cookie );

		$decoded = rawurldecode( (string) $raw_cookie );
		if ( $decoded !== $raw_cookie ) {
			$candidates[] = $decoded;
		}

		$base64 = base64_decode( (string) $raw_cookie, true );
		if ( false !== $base64 ) {
			$candidates[] = $base64;
		}

		foreach ( $candidates as $candidate ) {
			$data = json_decode( $candidate, true );
			if ( ! is_array( $data ) || empty( $data['$sesid'] ) || ! is_array( $data['$sesid'] ) ) {
				continue;
			}

			if ( ! empty( $data['$sesid'][1] ) ) {
				return self::sanitize_session_id( (string) $data['$sesid'][1] );
			}
		}

		return '';
	}

	/**
	 * Create or refresh a server-managed session ID when no frontend session is available.
	 *
	 * @return string
	 */
	private static function get_or_create_server_session_id() {
		$session_id = self::get_from_sync_cookie();
		$timestamp  = isset( $_COOKIE[ self::COOKIE_TS_NAME ] ) ? (int) $_COOKIE[ self::COOKIE_TS_NAME ] : 0;
		$now        = time();

		if ( $session_id && $timestamp && ( $now - $timestamp ) < self::SESSION_TIMEOUT ) {
			self::set_server_session_cookies( $session_id, $now );
			return $session_id;
		}

		$session_id = wp_generate_uuid4();
		self::set_server_session_cookies( $session_id, $now );

		return $session_id;
	}

	/**
	 * Persist server-managed session cookies.
	 *
	 * @param string $session_id Session ID.
	 * @param int    $timestamp  Last activity timestamp.
	 */
	private static function set_server_session_cookies( $session_id, $timestamp ) {
		if ( headers_sent() ) {
			return;
		}

		$expires = $timestamp + self::SESSION_TIMEOUT;

		setcookie( self::COOKIE_NAME, $session_id, $expires, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		setcookie( self::COOKIE_TS_NAME, (string) $timestamp, $expires, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	}

	/**
	 * Sanitize a session ID value.
	 *
	 * @param string $session_id Raw session ID.
	 * @return string
	 */
	private static function sanitize_session_id( $session_id ) {
		$session_id = sanitize_text_field( (string) $session_id );

		if ( empty( $session_id ) || strlen( $session_id ) > 200 ) {
			return '';
		}

		return $session_id;
	}
}
