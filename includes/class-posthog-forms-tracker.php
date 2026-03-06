<?php
/**
 * PostHog form submission tracker.
 *
 * Tracks form submissions from Elementor Forms, Contact Form 7, WPForms,
 * Gravity Forms, and Funnel Builder optin forms.
 *
 * @package PostHog_For_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostHog_For_WP_Forms_Tracker
 */
class PostHog_For_WP_Forms_Tracker {

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

		// Elementor Pro Forms.
		add_action( 'elementor_pro/forms/new_record', array( $this, 'track_elementor_form' ), 10, 2 );

		// Contact Form 7.
		add_action( 'wpcf7_mail_sent', array( $this, 'track_contact_form_7' ), 10, 1 );

		// WPForms.
		add_action( 'wpforms_process_complete', array( $this, 'track_wpforms' ), 10, 4 );

		// Gravity Forms.
		add_action( 'gform_after_submission', array( $this, 'track_gravity_forms' ), 10, 2 );

		// Funnel Builder (WooFunnels) optin forms.
		add_action( 'wffn_optin_form_submit', array( $this, 'track_wffn_optin' ), 10, 2 );
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

		if ( function_exists( 'WC' ) && WC()->session ) {
			$sid = WC()->session->get_customer_id();
			if ( ! empty( $sid ) ) {
				return 'guest_' . $sid;
			}
		}

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
	 * Get form submission event name.
	 *
	 * @return string
	 */
	private function get_event_name() {
		$defaults = posthog_for_wp_get_default_event_names();
		return get_option( 'posthog_for_wp_event_form_submitted', $defaults['form_submitted'] ?? 'form_submitted' );
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

	/**
	 * Track Elementor Pro form submission.
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record     Form record.
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler Ajax handler.
	 */
	public function track_elementor_form( $record, $ajax_handler ) {
		if ( ! is_object( $record ) || ! method_exists( $record, 'get_form_settings' ) ) {
			return;
		}

		$form_name = 'elementor_form';
		if ( method_exists( $record, 'get_form_settings' ) ) {
			$settings = $record->get_form_settings();
			$form_name = isset( $settings['form_name'] ) ? sanitize_text_field( $settings['form_name'] ) : 'elementor_form';
		}

		$fields = array();
		if ( method_exists( $record, 'get' ) ) {
			$raw = $record->get( 'fields' );
			if ( is_array( $raw ) ) {
				foreach ( $raw as $field ) {
					if ( isset( $field['title'], $field['value'] ) ) {
						$fields[ sanitize_text_field( $field['title'] ) ] = sanitize_text_field( (string) $field['value'] );
					}
				}
			}
		}

		$this->api->capture(
			$this->get_distinct_id(),
			$this->get_event_name(),
			array(
				'form_provider' => 'elementor',
				'form_name'     => $form_name,
				'fields'        => $fields,
				'$current_url'  => $this->get_current_url(),
			)
		);
	}

	/**
	 * Track Contact Form 7 submission.
	 *
	 * @param \WPCF7_ContactForm $contact_form CF7 form object.
	 */
	public function track_contact_form_7( $contact_form ) {
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'title' ) ) {
			return;
		}

		$form_name = $contact_form->title();
		$fields    = array();

		if ( class_exists( 'WPCF7_Submission' ) ) {
			$submission = WPCF7_Submission::get_instance();
			if ( $submission ) {
				$posted = $submission->get_posted_data();
				if ( is_array( $posted ) ) {
					foreach ( $posted as $key => $value ) {
						if ( is_array( $value ) ) {
							$value = implode( ', ', array_map( 'sanitize_text_field', $value ) );
						} else {
							$value = sanitize_text_field( (string) $value );
						}
						$fields[ sanitize_text_field( $key ) ] = $value;
					}
				}
			}
		}

		if ( empty( $fields ) && isset( $_POST ) && is_array( $_POST ) ) {
			foreach ( $_POST as $key => $value ) {
				if ( strpos( (string) $key, '_' ) === 0 ) {
					continue;
				}
				if ( is_array( $value ) ) {
					$value = implode( ', ', array_map( 'sanitize_text_field', $value ) );
				} else {
					$value = sanitize_text_field( (string) $value );
				}
				$fields[ sanitize_text_field( (string) $key ) ] = $value;
			}
		}

		$this->api->capture(
			$this->get_distinct_id(),
			$this->get_event_name(),
			array(
				'form_provider' => 'contact_form_7',
				'form_name'     => $form_name,
				'form_id'       => $contact_form->id(),
				'fields'        => $fields,
				'$current_url'  => $this->get_current_url(),
			)
		);
	}

	/**
	 * Track WPForms submission.
	 *
	 * @param array $fields    Sanitized field data.
	 * @param array $entry     Raw submitted data.
	 * @param array $form_data Form settings and data.
	 * @param array $entry_id  Entry ID.
	 */
	public function track_wpforms( $fields, $entry, $form_data, $entry_id ) {
		$form_name = isset( $form_data['settings']['form_title'] ) ? sanitize_text_field( $form_data['settings']['form_title'] ) : 'wpform';
		$field_map = array();

		if ( is_array( $fields ) ) {
			foreach ( $fields as $f ) {
				if ( isset( $f['name'], $f['value'] ) ) {
					$val = $f['value'];
					if ( is_array( $val ) ) {
						$val = implode( ', ', array_map( 'sanitize_text_field', $val ) );
					} else {
						$val = sanitize_text_field( (string) $val );
					}
					$field_map[ sanitize_text_field( $f['name'] ) ] = $val;
				}
			}
		}

		$this->api->capture(
			$this->get_distinct_id(),
			$this->get_event_name(),
			array(
				'form_provider' => 'wpforms',
				'form_name'     => $form_name,
				'form_id'       => isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0,
				'entry_id'      => $entry_id,
				'fields'        => $field_map,
				'$current_url'  => $this->get_current_url(),
			)
		);
	}

	/**
	 * Track Gravity Forms submission.
	 *
	 * @param array $entry The entry that was just created.
	 * @param array $form  The current form.
	 */
	public function track_gravity_forms( $entry, $form ) {
		if ( ! is_array( $entry ) || ! is_array( $form ) ) {
			return;
		}

		$form_name = isset( $form['title'] ) ? sanitize_text_field( $form['title'] ) : 'gf_form';
		$fields    = array();

		if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				$id    = isset( $field['id'] ) ? $field['id'] : null;
				$label = isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : (string) $id;
				if ( $id !== null && isset( $entry[ (string) $id ] ) ) {
					$val = $entry[ (string) $id ];
					if ( is_array( $val ) ) {
						$val = implode( ', ', array_map( 'sanitize_text_field', $val ) );
					} else {
						$val = sanitize_text_field( (string) $val );
					}
					$fields[ $label ] = $val;
				}
			}
		}

		$this->api->capture(
			$this->get_distinct_id(),
			$this->get_event_name(),
			array(
				'form_provider' => 'gravity_forms',
				'form_name'     => $form_name,
				'form_id'       => isset( $form['id'] ) ? absint( $form['id'] ) : 0,
				'entry_id'      => isset( $entry['id'] ) ? absint( $entry['id'] ) : 0,
				'fields'        => $fields,
				'$current_url'  => $this->get_current_url(),
			)
		);
	}

	/**
	 * Track Funnel Builder (WooFunnels) optin form submission.
	 *
	 * @param int   $optin_page_id Optin page ID.
	 * @param array $posted_data   Posted form data.
	 */
	public function track_wffn_optin( $optin_page_id, $posted_data ) {
		if ( ! is_array( $posted_data ) ) {
			$posted_data = array();
		}

		$fields = array();
		foreach ( $posted_data as $key => $value ) {
			if ( in_array( $key, array( 'wffn-captcha-response', '_wpnonce' ), true ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'sanitize_text_field', $value ) );
			} else {
				$value = sanitize_text_field( (string) $value );
			}
			$fields[ sanitize_text_field( $key ) ] = $value;
		}

		$this->api->capture(
			$this->get_distinct_id(),
			$this->get_event_name(),
			array(
				'form_provider' => 'funnel_builder',
				'form_name'     => 'optin_form',
				'form_id'       => absint( $optin_page_id ),
				'fields'        => $fields,
				'$current_url'  => $this->get_current_url(),
			)
		);
	}
}
