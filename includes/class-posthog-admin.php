<?php
/**
 * PostHog for WordPress admin settings.
 *
 * @package PostHog_For_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostHog_For_WP_Admin
 */
class PostHog_For_WP_Admin {

	/**
	 * Option group.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'posthog_for_wp_settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . POSTHOG_FOR_WP_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Get default event names.
	 *
	 * @return array
	 */
	public static function get_default_event_names() {
		return posthog_for_wp_get_default_event_names();
	}

	/**
	 * Get event key labels.
	 *
	 * @return array
	 */
	public static function get_event_labels() {
		return array(
			'page_view'        => __( 'Page view', 'posthog-for-wp' ),
			'product_viewed'   => __( 'Product viewed', 'posthog-for-wp' ),
			'add_to_cart'      => __( 'Add to cart', 'posthog-for-wp' ),
			'cart_viewed'      => __( 'Cart viewed', 'posthog-for-wp' ),
			'checkout_started' => __( 'Checkout started', 'posthog-for-wp' ),
			'order_completed'  => __( 'Order completed', 'posthog-for-wp' ),
			'search'           => __( 'Search', 'posthog-for-wp' ),
			'form_submitted'   => __( 'Form submitted', 'posthog-for-wp' ),
		);
	}

	/**
	 * Add menu page.
	 */
	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'PostHog Settings', 'posthog-for-wp' ),
			__( 'PostHog', 'posthog-for-wp' ),
			'manage_woocommerce',
			'posthog-for-wp',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			'posthog_for_wp_enabled',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'posthog_for_wp_api_key',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'posthog_for_wp_host',
			array(
				'type'              => 'string',
				'default'           => 'https://us.i.posthog.com',
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'posthog_for_wp_distinct_id_source',
			array(
				'type'              => 'string',
				'default'           => 'user_id',
				'sanitize_callback' => function ( $v ) {
					return in_array( $v, array( 'user_id', 'email' ), true ) ? $v : 'user_id';
				},
			)
		);

		$defaults = self::get_default_event_names();
		foreach ( array_keys( $defaults ) as $key ) {
			register_setting(
				self::OPTION_GROUP,
				'posthog_for_wp_event_' . $key,
				array(
					'type'              => 'string',
					'default'           => $defaults[ $key ],
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
		}
	}

	/**
	 * Add settings link to plugins list.
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$url   = admin_url( 'admin.php?page=posthog-for-wp' );
		$label = __( 'Settings', 'posthog-for-wp' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' );
		return $links;
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		$enabled        = (bool) get_option( 'posthog_for_wp_enabled', false );
		$api_key        = get_option( 'posthog_for_wp_api_key', '' );
		$host           = get_option( 'posthog_for_wp_host', 'https://us.i.posthog.com' );
		$distinct_source = get_option( 'posthog_for_wp_distinct_id_source', 'user_id' );
		$defaults       = self::get_default_event_names();
		$labels         = self::get_event_labels();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PostHog for WordPress', 'posthog-for-wp' ); ?></h1>
			<p><?php esc_html_e( 'Send WooCommerce events to PostHog with custom event names for cross-platform analytics.', 'posthog-for-wp' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable tracking', 'posthog-for-wp' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="posthog_for_wp_enabled" value="1" <?php checked( $enabled ); ?> />
								<?php esc_html_e( 'Send events to PostHog', 'posthog-for-wp' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="posthog_for_wp_api_key"><?php esc_html_e( 'Project token', 'posthog-for-wp' ); ?></label></th>
						<td>
							<input type="text" id="posthog_for_wp_api_key" name="posthog_for_wp_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Your PostHog project API key. Find it in Project Settings.', 'posthog-for-wp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="posthog_for_wp_host"><?php esc_html_e( 'PostHog host', 'posthog-for-wp' ); ?></label></th>
						<td>
							<input type="url" id="posthog_for_wp_host" name="posthog_for_wp_host" value="<?php echo esc_attr( $host ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'e.g. https://us.i.posthog.com or https://eu.i.posthog.com', 'posthog-for-wp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="posthog_for_wp_distinct_id_source"><?php esc_html_e( 'Distinct ID source', 'posthog-for-wp' ); ?></label></th>
						<td>
							<select id="posthog_for_wp_distinct_id_source" name="posthog_for_wp_distinct_id_source">
								<option value="user_id" <?php selected( $distinct_source, 'user_id' ); ?>><?php esc_html_e( 'User ID (WordPress user ID)', 'posthog-for-wp' ); ?></option>
								<option value="email" <?php selected( $distinct_source, 'email' ); ?>><?php esc_html_e( 'Email (when available)', 'posthog-for-wp' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Unique identifier sent to PostHog. Use the same source as your other platform for unified user tracking.', 'posthog-for-wp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Session IDs', 'posthog-for-wp' ); ?></th>
						<td>
							<p class="description">
								<?php
								echo wp_kses_post(
									__( 'Server-side events automatically include <code>$session_id</code>. If you also use posthog-js on the frontend, enable <code>tracing_headers</code> in your PostHog init so AJAX requests send <code>X-POSTHOG-SESSION-ID</code>. See the <a href="https://posthog.com/docs/data/sessions#automatically-sending-session-ids" target="_blank" rel="noopener noreferrer">PostHog sessions docs</a>.', 'posthog-for-wp' )
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Custom event names', 'posthog-for-wp' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Configure custom event names so they match events from your other platforms (e.g. mobile app) in PostHog.', 'posthog-for-wp' ); ?></p>
				<table class="form-table">
					<?php foreach ( $defaults as $key => $default ) : ?>
						<tr>
							<th scope="row"><label for="posthog_for_wp_event_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $labels[ $key ] ?? $key ); ?></label></th>
							<td>
								<input type="text" id="posthog_for_wp_event_<?php echo esc_attr( $key ); ?>" name="posthog_for_wp_event_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( get_option( 'posthog_for_wp_event_' . $key, $default ) ); ?>" class="regular-text" />
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
