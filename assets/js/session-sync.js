/**
 * Sync PostHog session ID to a cookie for server-side event capture.
 *
 * @see https://posthog.com/docs/data/sessions#automatically-sending-session-ids
 */
( function () {
	'use strict';

	var COOKIE_NAME = 'posthog_wp_session_id';
	var MAX_AGE     = 1800;

	function setSessionCookie( sessionId ) {
		if ( ! sessionId ) {
			return;
		}

		var cookie = COOKIE_NAME + '=' + encodeURIComponent( sessionId ) + '; path=/; max-age=' + MAX_AGE + '; SameSite=Lax';
		if ( window.location.protocol === 'https:' ) {
			cookie += '; Secure';
		}

		document.cookie = cookie;
	}

	function syncSession() {
		if ( typeof window.posthog === 'undefined' || typeof window.posthog.get_session_id !== 'function' ) {
			return false;
		}

		var sessionId = window.posthog.get_session_id();
		if ( sessionId ) {
			setSessionCookie( sessionId );
		}

		return !! sessionId;
	}

	function waitForPostHog( attempts ) {
		if ( syncSession() ) {
			return;
		}

		if ( attempts <= 0 ) {
			return;
		}

		window.setTimeout( function () {
			waitForPostHog( attempts - 1 );
		}, 200 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			waitForPostHog( 25 );
		} );
	} else {
		waitForPostHog( 25 );
	}
} )();
