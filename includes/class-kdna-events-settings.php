<?php
/**
 * Settings page and top-level admin menu for KDNA Events.
 *
 * Registers the top-level Events menu at position 21 (just below Pages),
 * parents the kdna_event CPT screens under it, and renders the tabbed
 * settings page (General, Stripe, Google Maps, Pages, Emails) using the
 * WordPress Settings API.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Top-level menu and settings page controller.
 */
class KDNA_Events_Settings {

	/**
	 * Parent menu slug used to re-parent the CPT menus.
	 */
	const MENU_SLUG = 'kdna-events';

	/**
	 * Settings sub-page slug.
	 */
	const SETTINGS_SLUG = 'kdna-events-settings';

	/**
	 * Register menu, settings, and asset hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 9 );
		add_action( 'admin_menu', array( __CLASS__, 'prune_parent_submenu' ), 999 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Return the full list of tab slugs keyed by display label.
	 *
	 * @return array<string,string>
	 */
	public static function tabs() {
		return array(
			'general'   => __( 'General', 'kdna-events' ),
			'attendees' => __( 'Attendees', 'kdna-events' ),
			'stripe'    => __( 'Stripe', 'kdna-events' ),
			'maps'      => __( 'Google Maps', 'kdna-events' ),
			'pages'     => __( 'Pages', 'kdna-events' ),
			'emails'    => __( 'Emails', 'kdna-events' ),
			'crm'       => __( 'CRM', 'kdna-events' ),
		);
	}

	/**
	 * Return the supported currency codes.
	 *
	 * @return array<string,string>
	 */
	public static function supported_currencies() {
		if ( class_exists( 'KDNA_Events_Admin' ) ) {
			return KDNA_Events_Admin::supported_currencies();
		}
		return array(
			'AUD' => __( 'Australian Dollar (AUD)', 'kdna-events' ),
			'USD' => __( 'US Dollar (USD)', 'kdna-events' ),
			'EUR' => __( 'Euro (EUR)', 'kdna-events' ),
			'GBP' => __( 'Pound Sterling (GBP)', 'kdna-events' ),
			'NZD' => __( 'New Zealand Dollar (NZD)', 'kdna-events' ),
		);
	}

	/**
	 * Register the top-level 'Events' menu and all sub-items.
	 *
	 * The top-level page has no callable render target, its slug matches
	 * the CPT list screen so clicking 'Events' opens All Events directly.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'KDNA Events', 'kdna-events' ),
			__( 'Events', 'kdna-events' ),
			'edit_posts',
			self::MENU_SLUG,
			array( __CLASS__, 'render_menu_landing' ),
			'dashicons-calendar-alt',
			21
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Events', 'kdna-events' ),
			__( 'All Events', 'kdna-events' ),
			'edit_posts',
			'edit.php?post_type=' . KDNA_Events_CPT::POST_TYPE
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Add New Event', 'kdna-events' ),
			__( 'Add New', 'kdna-events' ),
			'edit_posts',
			'post-new.php?post_type=' . KDNA_Events_CPT::POST_TYPE
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Event Categories', 'kdna-events' ),
			__( 'Categories', 'kdna-events' ),
			'manage_categories',
			'edit-tags.php?taxonomy=' . KDNA_Events_CPT::TAXONOMY . '&post_type=' . KDNA_Events_CPT::POST_TYPE
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Locations', 'kdna-events' ),
			__( 'Locations', 'kdna-events' ),
			'edit_posts',
			'edit.php?post_type=' . KDNA_Events_CPT::LOCATION_POST_TYPE
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Organisers', 'kdna-events' ),
			__( 'Organisers', 'kdna-events' ),
			'edit_posts',
			'edit.php?post_type=' . KDNA_Events_CPT::ORGANISER_POST_TYPE
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'KDNA Events Settings', 'kdna-events' ),
			__( 'Settings', 'kdna-events' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Remove the auto-generated duplicate sub-item that mirrors the parent slug.
	 *
	 * WordPress adds a first sub-item with the same slug as the parent
	 * menu page. We replace that with 'All Events' for a clean sidebar.
	 *
	 * @return void
	 */
	public static function prune_parent_submenu() {
		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
	}

	/**
	 * Redirect the bare top-level menu click to the events list.
	 *
	 * @return void
	 */
	public static function render_menu_landing() {
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . KDNA_Events_CPT::POST_TYPE ) );
		exit;
	}

	/**
	 * Register every option via the Settings API.
	 *
	 * Each option sits in its own group keyed to a tab so tabs can submit
	 * independently without clobbering unrelated fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		// General.
		register_setting(
			'kdna_events_general',
			'kdna_events_default_currency',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_currency' ),
				'default'           => 'AUD',
			)
		);
		register_setting(
			'kdna_events_general',
			'kdna_events_default_max_per_order',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_positive_int' ),
				'default'           => 10,
			)
		);
		register_setting(
			'kdna_events_general',
			'kdna_events_admin_notification_email',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'default'           => '',
			)
		);
		register_setting(
			'kdna_events_general',
			'kdna_events_notify_organiser',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);
		register_setting(
			'kdna_events_general',
			'kdna_events_email_from_name',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		register_setting(
			'kdna_events_general',
			'kdna_events_email_from_address',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'default'           => '',
			)
		);
		register_setting(
			'kdna_events_general',
			'kdna_events_per_attendee_emails',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		// Stripe.
		register_setting(
			'kdna_events_stripe',
			'kdna_events_stripe_publishable_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		register_setting(
			'kdna_events_stripe',
			'kdna_events_stripe_secret_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		register_setting(
			'kdna_events_stripe',
			'kdna_events_stripe_webhook_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		register_setting(
			'kdna_events_stripe',
			'kdna_events_stripe_test_mode',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		// Google Maps.
		register_setting(
			'kdna_events_maps',
			'kdna_events_google_maps_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		// Pages.
		register_setting(
			'kdna_events_pages',
			'kdna_events_template_checkout',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);
		register_setting(
			'kdna_events_pages',
			'kdna_events_template_success',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);
		register_setting(
			'kdna_events_pages',
			'kdna_events_template_my_tickets',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);

		// Emails.
		register_setting(
			'kdna_events_emails',
			'kdna_events_booking_email_body',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_textarea' ),
				'default'           => self::default_booking_email_body(),
			)
		);

		// Attendees.
		register_setting(
			'kdna_events_attendees',
			'kdna_events_global_attendee_fields',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_attendee_fields' ),
				'default'           => '',
			)
		);

		// CRM.
		register_setting(
			'kdna_events_crm',
			'kdna_events_crm_master_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);
		register_setting(
			'kdna_events_crm',
			'kdna_events_crm_enabled',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_crm_enabled' ),
				'default'           => array(),
			)
		);
		register_setting(
			'kdna_events_crm',
			'kdna_events_crm_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_crm_settings' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitise a currency code against the supported list.
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	public static function sanitize_currency( $value ) {
		$value = strtoupper( sanitize_text_field( (string) $value ) );
		$allowed = array_keys( self::supported_currencies() );
		if ( ! in_array( $value, $allowed, true ) ) {
			return 'AUD';
		}
		return $value;
	}

	/**
	 * Sanitise a positive integer, coercing invalid input to 1.
	 *
	 * @param mixed $value Raw input.
	 * @return int
	 */
	public static function sanitize_positive_int( $value ) {
		$int = absint( $value );
		if ( $int < 1 ) {
			$int = 1;
		}
		return $int;
	}

	/**
	 * Sanitise a checkbox to a strict boolean.
	 *
	 * @param mixed $value Raw input.
	 * @return bool
	 */
	public static function sanitize_checkbox( $value ) {
		return ! empty( $value );
	}

	/**
	 * Sanitise a textarea value while preserving line breaks.
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	public static function sanitize_textarea( $value ) {
		return sanitize_textarea_field( (string) $value );
	}

	/**
	 * Sanitise the Attendees tab global-fields repeater input.
	 *
	 * Accepts the array shape posted by the repeater and stores it as
	 * a JSON string so it round-trips cleanly to both the admin UI and
	 * the front-end checkout merge helper.
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	public static function sanitize_attendee_fields( $value ) {
		if ( is_string( $value ) && '' === trim( $value ) ) {
			return '';
		}
		$data = is_array( $value ) ? $value : json_decode( (string) $value, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		$allowed = array( 'text', 'email', 'tel', 'select' );
		$clean   = array();
		foreach ( $data as $row ) {
			if ( ! is_array( $row ) || empty( $row['label'] ) ) {
				continue;
			}
			$label = sanitize_text_field( (string) $row['label'] );
			$key   = isset( $row['key'] ) && '' !== $row['key'] ? sanitize_key( (string) $row['key'] ) : sanitize_key( $label );
			$type  = isset( $row['type'] ) ? sanitize_key( (string) $row['type'] ) : 'text';
			if ( ! in_array( $type, $allowed, true ) ) {
				$type = 'text';
			}
			$clean[] = array(
				'label'    => $label,
				'key'      => $key,
				'type'     => $type,
				'required' => ! empty( $row['required'] ),
			);
		}
		return wp_json_encode( $clean );
	}

	/**
	 * Sanitise the CRM enabled map.
	 *
	 * Accepts { integration_id => truthy }, discards unknown ids by
	 * whitelisting against the live registry so stale settings don't
	 * accumulate when add-ons are deactivated.
	 *
	 * @param mixed $value Raw input.
	 * @return array<string,bool>
	 */
	public static function sanitize_crm_enabled( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$known = array();
		if ( class_exists( 'KDNA_Events_CRM' ) ) {
			$known = array_keys( KDNA_Events_CRM::registry()->get_all() );
		}

		$clean = array();
		foreach ( $value as $id => $flag ) {
			$id = sanitize_key( (string) $id );
			if ( '' === $id ) {
				continue;
			}
			if ( ! empty( $known ) && ! in_array( $id, $known, true ) ) {
				continue;
			}
			$clean[ $id ] = ! empty( $flag );
		}
		return $clean;
	}

	/**
	 * Sanitise the CRM per-integration settings map.
	 *
	 * Only retains keys declared by each integration's
	 * get_settings_fields. Text-like values are passed through
	 * sanitize_textarea_field to preserve line breaks in API body
	 * templates; checkboxes are coerced to booleans.
	 *
	 * @param mixed $value Raw input.
	 * @return array<string,array<string,mixed>>
	 */
	public static function sanitize_crm_settings( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		if ( ! class_exists( 'KDNA_Events_CRM' ) ) {
			return array();
		}

		$integrations = KDNA_Events_CRM::registry()->get_all();
		$clean        = array();

		foreach ( $value as $id => $fields ) {
			$id = sanitize_key( (string) $id );
			if ( '' === $id || ! isset( $integrations[ $id ] ) || ! is_array( $fields ) ) {
				continue;
			}

			$schema = $integrations[ $id ]->get_settings_fields();
			if ( ! is_array( $schema ) ) {
				continue;
			}

			$out = array();
			foreach ( $schema as $field ) {
				if ( empty( $field['key'] ) ) {
					continue;
				}
				$key  = sanitize_key( (string) $field['key'] );
				$type = isset( $field['type'] ) ? (string) $field['type'] : 'text';
				$raw  = array_key_exists( $key, $fields ) ? $fields[ $key ] : '';

				switch ( $type ) {
					case 'checkbox':
						$out[ $key ] = ! empty( $raw );
						break;
					case 'email':
						$out[ $key ] = sanitize_email( (string) $raw );
						break;
					case 'url':
						$out[ $key ] = esc_url_raw( (string) $raw );
						break;
					case 'textarea':
						$out[ $key ] = sanitize_textarea_field( (string) $raw );
						break;
					case 'select':
						$allowed    = isset( $field['options'] ) && is_array( $field['options'] ) ? array_keys( $field['options'] ) : array();
						$out[ $key ] = in_array( (string) $raw, array_map( 'strval', $allowed ), true ) ? (string) $raw : '';
						break;
					case 'password':
					case 'text':
					default:
						$out[ $key ] = sanitize_text_field( (string) $raw );
				}
			}

			$clean[ $id ] = $out;
		}

		return $clean;
	}

	/**
	 * Default booking confirmation email body with merge tags.
	 *
	 * @return string
	 */
	public static function default_booking_email_body() {
		return "Hi {attendee_name},\n\n" .
			"Thanks for booking {event_title}. Your booking reference is {order_ref}.\n\n" .
			"Event date: {event_date}\n" .
			"Location: {event_location}\n" .
			"Ticket code: {ticket_code}\n\n" .
			"If you have any questions, reply to this email.\n\n" .
			"{organiser_name}";
	}

	/**
	 * Enqueue the settings page stylesheet.
	 *
	 * Loads a minimal admin style on the settings screen only. Shares
	 * the existing kdna-events-admin.css file registered in Stage 1.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SETTINGS_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'kdna-events-admin',
			KDNA_EVENTS_URL . 'assets/css/kdna-events-admin.css',
			array(),
			KDNA_EVENTS_VERSION
		);
		wp_enqueue_script(
			'kdna-events-admin',
			KDNA_EVENTS_URL . 'assets/js/kdna-events-admin.js',
			array( 'jquery' ),
			KDNA_EVENTS_VERSION,
			true
		);
	}

	/**
	 * Render the tabbed settings page.
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kdna-events' ) );
		}

		$tabs    = self::tabs();
		$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $current ] ) ) {
			$current = 'general';
		}

		$base_url = add_query_arg(
			array(
				'page' => self::SETTINGS_SLUG,
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap kdna-events-settings">
			<h1><?php esc_html_e( 'KDNA Events Settings', 'kdna-events' ); ?></h1>

			<?php settings_errors(); ?>

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings tabs', 'kdna-events' ); ?>">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a
						class="nav-tab <?php echo $slug === $current ? 'nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>"
					>
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php" class="kdna-events-settings-form">
				<?php
				switch ( $current ) {
					case 'attendees':
						settings_fields( 'kdna_events_attendees' );
						self::render_attendees_tab();
						break;
					case 'stripe':
						settings_fields( 'kdna_events_stripe' );
						self::render_stripe_tab();
						break;
					case 'maps':
						settings_fields( 'kdna_events_maps' );
						self::render_maps_tab();
						break;
					case 'pages':
						settings_fields( 'kdna_events_pages' );
						self::render_pages_tab();
						break;
					case 'emails':
						settings_fields( 'kdna_events_emails' );
						self::render_emails_tab();
						break;
					case 'crm':
						settings_fields( 'kdna_events_crm' );
						self::render_crm_tab();
						break;
					case 'general':
					default:
						settings_fields( 'kdna_events_general' );
						self::render_general_tab();
				}

				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the General tab fields.
	 *
	 * @return void
	 */
	protected static function render_general_tab() {
		$currency     = (string) get_option( 'kdna_events_default_currency', 'AUD' );
		$max_per      = (int) get_option( 'kdna_events_default_max_per_order', 10 );
		$admin_email  = (string) get_option( 'kdna_events_admin_notification_email', '' );
		$notify_org   = (bool) get_option( 'kdna_events_notify_organiser', false );
		$from_name    = (string) get_option( 'kdna_events_email_from_name', '' );
		$from_address = (string) get_option( 'kdna_events_email_from_address', '' );
		$per_attendee = (bool) get_option( 'kdna_events_per_attendee_emails', false );
		$test_nonce   = wp_create_nonce( 'kdna_events_test_email' );
		?>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><label for="kdna_events_default_currency"><?php esc_html_e( 'Default currency', 'kdna-events' ); ?></label></th>
				<td>
					<select id="kdna_events_default_currency" name="kdna_events_default_currency">
						<?php foreach ( self::supported_currencies() as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $currency, $code ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Used as the fallback currency for new events.', 'kdna-events' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="kdna_events_default_max_per_order"><?php esc_html_e( 'Default max tickets per order', 'kdna-events' ); ?></label></th>
				<td>
					<input type="number" min="1" step="1" id="kdna_events_default_max_per_order" name="kdna_events_default_max_per_order" value="<?php echo esc_attr( (string) $max_per ); ?>" />
					<p class="description"><?php esc_html_e( 'Fallback cap applied when an event does not set its own maximum per order.', 'kdna-events' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="kdna_events_admin_notification_email"><?php esc_html_e( 'Admin notification email', 'kdna-events' ); ?></label></th>
				<td>
					<input type="email" class="regular-text" id="kdna_events_admin_notification_email" name="kdna_events_admin_notification_email" value="<?php echo esc_attr( $admin_email ); ?>" />
					<button
						type="button"
						class="button button-secondary"
						id="kdna-events-send-test-email"
						data-nonce="<?php echo esc_attr( $test_nonce ); ?>"
						data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
						style="margin-left:8px;"
					>
						<?php esc_html_e( 'Send test email', 'kdna-events' ); ?>
					</button>
					<span id="kdna-events-send-test-email-result" role="status" aria-live="polite" style="margin-left:8px;"></span>
					<p class="description"><?php esc_html_e( 'Booking notifications are sent to this address. Use the button to verify SMTP without completing a booking.', 'kdna-events' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Notify organiser', 'kdna-events' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="kdna_events_notify_organiser" value="1" <?php checked( $notify_org ); ?> />
						<?php esc_html_e( 'Also send admin notifications to the event organiser email when one is set.', 'kdna-events' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="kdna_events_email_from_name"><?php esc_html_e( 'Email from name', 'kdna-events' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="kdna_events_email_from_name" name="kdna_events_email_from_name" value="<?php echo esc_attr( $from_name ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="kdna_events_email_from_address"><?php esc_html_e( 'Email from address', 'kdna-events' ); ?></label></th>
				<td>
					<input type="email" class="regular-text" id="kdna_events_email_from_address" name="kdna_events_email_from_address" value="<?php echo esc_attr( $from_address ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Per-attendee emails', 'kdna-events' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="kdna_events_per_attendee_emails" value="1" <?php checked( $per_attendee ); ?> />
						<?php esc_html_e( 'Also send a personalised ticket email to each attendee when their email address is captured.', 'kdna-events' ); ?>
					</label>
				</td>
			</tr>
			</tbody>
		</table>
		<script>
		(function () {
			var btn = document.getElementById('kdna-events-send-test-email');
			var result = document.getElementById('kdna-events-send-test-email-result');
			if (!btn || !result) { return; }
			btn.addEventListener('click', function () {
				result.textContent = '<?php echo esc_js( __( 'Sending...', 'kdna-events' ) ); ?>';
				result.style.color = '#4b5563';
				var body = new URLSearchParams();
				body.append('action', 'kdna_events_send_test_email');
				body.append('nonce', btn.getAttribute('data-nonce') || '');
				fetch(btn.getAttribute('data-ajax-url') || '', {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString()
				}).then(function (r) {
					return r.json().then(function (json) { return { status: r.status, json: json }; });
				}).then(function (res) {
					var message = '';
					if (res.json && res.json.data && res.json.data.message) {
						message = res.json.data.message;
					}
					if (res.json && res.json.success) {
						result.style.color = '#059669';
						result.textContent = message || '<?php echo esc_js( __( 'Sent.', 'kdna-events' ) ); ?>';
					} else {
						result.style.color = '#b91c1c';
						result.textContent = message || '<?php echo esc_js( __( 'Send failed.', 'kdna-events' ) ); ?>';
					}
				}).catch(function () {
					result.style.color = '#b91c1c';
					result.textContent = '<?php echo esc_js( __( 'Network error.', 'kdna-events' ) ); ?>';
				});
			});
		})();
		</script>
		<?php
	}

	/**
	 * Render the Stripe tab fields.
	 *
	 * @return void
	 */
	protected static function render_stripe_tab() {
		$pub        = (string) get_option( 'kdna_events_stripe_publishable_key', '' );
		$secret     = (string) get_option( 'kdna_events_stripe_secret_key', '' );
		$webhook    = (string) get_option( 'kdna_events_stripe_webhook_secret', '' );
		$test_mode  = (bool) get_option( 'kdna_events_stripe_test_mode', true );
		?>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Test mode', 'kdna-events' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="kdna_events_stripe_test_mode" value="1" <?php checked( $test_mode ); ?> />
						<?php esc_html_e( 'Use Stripe test keys and test webhooks.', 'kdna-events' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="kdna_events_stripe_publishable_key"><?php esc_html_e( 'Publishable key', 'kdna-events' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="kdna_events_stripe_publishable_key" name="kdna_events_stripe_publishable_key" value="<?php echo esc_attr( $pub ); ?>" autocomplete="off" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="kdna_events_stripe_secret_key"><?php esc_html_e( 'Secret key', 'kdna-events' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="kdna_events_stripe_secret_key" name="kdna_events_stripe_secret_key" value="<?php echo esc_attr( $secret ); ?>" autocomplete="off" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="kdna_events_stripe_webhook_secret"><?php esc_html_e( 'Webhook signing secret', 'kdna-events' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="kdna_events_stripe_webhook_secret" name="kdna_events_stripe_webhook_secret" value="<?php echo esc_attr( $webhook ); ?>" autocomplete="off" />
					<p class="description"><?php esc_html_e( 'Set when you add the webhook endpoint in the Stripe dashboard.', 'kdna-events' ); ?></p>
				</td>
			</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the Google Maps tab fields.
	 *
	 * @return void
	 */
	protected static function render_maps_tab() {
		$key = (string) get_option( 'kdna_events_google_maps_api_key', '' );
		?>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><label for="kdna_events_google_maps_api_key"><?php esc_html_e( 'Google Maps API key', 'kdna-events' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="kdna_events_google_maps_api_key" name="kdna_events_google_maps_api_key" value="<?php echo esc_attr( $key ); ?>" autocomplete="off" />
					<p class="description">
						<?php
						printf(
							/* translators: %s: link to Google Cloud Console */
							esc_html__( 'Create an API key in the %s. Enable the Maps JavaScript API and the Geocoding API, then restrict the key by HTTP referrer to this site.', 'kdna-events' ),
							'<a href="https://console.cloud.google.com/google/maps-apis/credentials" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google Cloud Console', 'kdna-events' ) . '</a>'
						);
						?>
					</p>
				</td>
			</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the Pages tab fields.
	 *
	 * Uses wp_dropdown_pages for the three template selectors.
	 *
	 * @return void
	 */
	protected static function render_pages_tab() {
		$fields = array(
			'kdna_events_template_checkout'   => array(
				'label' => __( 'Checkout page', 'kdna-events' ),
				'help'  => __( 'Elementor page used for the checkout flow.', 'kdna-events' ),
			),
			'kdna_events_template_success'    => array(
				'label' => __( 'Success page', 'kdna-events' ),
				'help'  => __( 'Elementor page shown after a successful booking.', 'kdna-events' ),
			),
			'kdna_events_template_my_tickets' => array(
				'label' => __( 'My Tickets page', 'kdna-events' ),
				'help'  => __( 'Elementor page where logged-in users view their tickets.', 'kdna-events' ),
			),
		);
		?>
		<table class="form-table" role="presentation">
			<tbody>
			<?php foreach ( $fields as $option => $info ) : ?>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $info['label'] ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => $option,
								'id'                => $option,
								'selected'          => (int) get_option( $option, 0 ),
								'show_option_none'  => __( 'Select a page', 'kdna-events' ),
								'option_none_value' => 0,
							)
						);
						?>
						<p class="description"><?php echo esc_html( $info['help'] ); ?></p>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the CRM tab: master switch, per-integration controls, sync log.
	 *
	 * @return void
	 */
	protected static function render_crm_tab() {
		if ( ! class_exists( 'KDNA_Events_CRM' ) ) {
			echo '<p>' . esc_html__( 'CRM framework is not available.', 'kdna-events' ) . '</p>';
			return;
		}

		$master   = KDNA_Events_CRM::master_enabled();
		$all      = KDNA_Events_CRM::registry()->get_all();
		$enabled  = (array) get_option( 'kdna_events_crm_enabled', array() );
		$settings = (array) get_option( 'kdna_events_crm_settings', array() );
		$log      = KDNA_Events_CRM::get_log();
		$nonce    = wp_create_nonce( 'kdna_events_crm_test' );
		?>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable CRM sync', 'kdna-events' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="kdna_events_crm_master_enabled" value="1" <?php checked( $master ); ?> />
						<?php esc_html_e( 'Master switch. When off, no integrations receive sync calls.', 'kdna-events' ); ?>
					</label>
				</td>
			</tr>
			</tbody>
		</table>

		<h2 style="margin-top:1.5em;"><?php esc_html_e( 'Registered integrations', 'kdna-events' ); ?></h2>
		<?php if ( empty( $all ) ) : ?>
			<div class="notice notice-info inline"><p>
				<?php
				printf(
					/* translators: %s: placeholder for a docs link */
					esc_html__( 'No CRM integrations are registered yet. Build one by extending KDNA_Events_CRM_Integration and calling $registry->register on %s.', 'kdna-events' ),
					'<code>kdna_events_register_crm_integrations</code>'
				);
				?>
			</p></div>
		<?php endif; ?>

		<?php foreach ( $all as $id => $integration ) :
			$is_enabled = ! empty( $enabled[ $id ] );
			$mine       = isset( $settings[ $id ] ) && is_array( $settings[ $id ] ) ? $settings[ $id ] : array();
			$fields     = (array) $integration->get_settings_fields();
			?>
			<div class="kdna-events-crm-integration" style="margin:1em 0;padding:1em 1.25em;border:1px solid #dcdcde;background:#fff;border-radius:4px;">
				<h3 style="margin-top:0;display:flex;align-items:center;gap:0.5em;flex-wrap:wrap;">
					<span><?php echo esc_html( (string) $integration->get_name() ); ?></span>
					<code style="font-size:0.8em;opacity:0.7;"><?php echo esc_html( (string) $id ); ?></code>
				</h3>
				<p class="description" style="margin-top:0;">
					<?php echo esc_html( (string) $integration->get_description() ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable', 'kdna-events' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="kdna_events_crm_enabled[<?php echo esc_attr( $id ); ?>]" value="1" <?php checked( $is_enabled ); ?> />
								<?php esc_html_e( 'Sync new tickets to this integration.', 'kdna-events' ); ?>
							</label>
						</td>
					</tr>

					<?php foreach ( $fields as $field ) :
						if ( empty( $field['key'] ) ) {
							continue;
						}
						$f_key     = sanitize_key( (string) $field['key'] );
						$f_type    = isset( $field['type'] ) ? (string) $field['type'] : 'text';
						$f_label   = isset( $field['label'] ) ? (string) $field['label'] : $f_key;
						$f_desc    = isset( $field['description'] ) ? (string) $field['description'] : '';
						$f_default = isset( $field['default'] ) ? $field['default'] : '';
						$current   = array_key_exists( $f_key, $mine ) ? $mine[ $f_key ] : $f_default;
						$name_attr = 'kdna_events_crm_settings[' . $id . '][' . $f_key . ']';
						?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $id . '_' . $f_key ); ?>"><?php echo esc_html( $f_label ); ?></label>
							</th>
							<td>
								<?php if ( 'textarea' === $f_type ) : ?>
									<textarea
										class="large-text"
										rows="4"
										id="<?php echo esc_attr( $id . '_' . $f_key ); ?>"
										name="<?php echo esc_attr( $name_attr ); ?>"
									><?php echo esc_textarea( (string) $current ); ?></textarea>
								<?php elseif ( 'select' === $f_type ) : ?>
									<select
										id="<?php echo esc_attr( $id . '_' . $f_key ); ?>"
										name="<?php echo esc_attr( $name_attr ); ?>"
									>
										<?php foreach ( (array) ( $field['options'] ?? array() ) as $opt_val => $opt_label ) : ?>
											<option value="<?php echo esc_attr( (string) $opt_val ); ?>" <?php selected( (string) $current, (string) $opt_val ); ?>><?php echo esc_html( (string) $opt_label ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php elseif ( 'checkbox' === $f_type ) : ?>
									<label>
										<input type="checkbox"
											id="<?php echo esc_attr( $id . '_' . $f_key ); ?>"
											name="<?php echo esc_attr( $name_attr ); ?>"
											value="1"
											<?php checked( (bool) $current ); ?> />
										<?php echo esc_html( $f_desc ); ?>
									</label>
								<?php else :
									$input_type = in_array( $f_type, array( 'password', 'email', 'url', 'text' ), true ) ? $f_type : 'text';
									?>
									<input type="<?php echo esc_attr( $input_type ); ?>"
										class="regular-text"
										autocomplete="off"
										id="<?php echo esc_attr( $id . '_' . $f_key ); ?>"
										name="<?php echo esc_attr( $name_attr ); ?>"
										value="<?php echo esc_attr( (string) $current ); ?>" />
								<?php endif; ?>
								<?php if ( 'checkbox' !== $f_type && '' !== $f_desc ) : ?>
									<p class="description"><?php echo esc_html( $f_desc ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>

					<tr>
						<th scope="row"><?php esc_html_e( 'Test connection', 'kdna-events' ); ?></th>
						<td>
							<button
								type="button"
								class="button button-secondary kdna-events-crm-test"
								data-integration="<?php echo esc_attr( $id ); ?>"
								data-nonce="<?php echo esc_attr( $nonce ); ?>"
							>
								<?php esc_html_e( 'Test', 'kdna-events' ); ?>
							</button>
							<span class="kdna-events-crm-test-result" role="status" aria-live="polite" style="margin-left:8px;"></span>
						</td>
					</tr>
					</tbody>
				</table>
			</div>
		<?php endforeach; ?>

		<h2 style="margin-top:1.5em;"><?php esc_html_e( 'Sync log', 'kdna-events' ); ?></h2>
		<p class="description">
			<?php
			printf(
				/* translators: %d: max entries */
				esc_html__( 'Rolling log, most recent first. Capped at %d entries.', 'kdna-events' ),
				(int) KDNA_Events_CRM::LOG_MAX_ENTRIES
			);
			?>
		</p>
		<p>
			<button
				type="button"
				class="button button-link-delete"
				id="kdna-events-crm-clear-log"
				data-nonce="<?php echo esc_attr( $nonce ); ?>"
			>
				<?php esc_html_e( 'Clear log', 'kdna-events' ); ?>
			</button>
			<span id="kdna-events-crm-clear-log-result" role="status" aria-live="polite" style="margin-left:8px;"></span>
		</p>
		<table class="widefat striped" id="kdna-events-crm-log-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Timestamp', 'kdna-events' ); ?></th>
					<th><?php esc_html_e( 'Integration', 'kdna-events' ); ?></th>
					<th><?php esc_html_e( 'Ticket', 'kdna-events' ); ?></th>
					<th><?php esc_html_e( 'Status', 'kdna-events' ); ?></th>
					<th><?php esc_html_e( 'Message', 'kdna-events' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $log ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No entries yet.', 'kdna-events' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $log as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( (string) ( $entry['timestamp'] ?? '' ) ); ?></td>
							<td><code><?php echo esc_html( (string) ( $entry['integration'] ?? '' ) ); ?></code></td>
							<td><code><?php echo esc_html( (string) ( $entry['ticket_code'] ?? '' ) ); ?></code></td>
							<td>
								<?php if ( 'error' === ( $entry['status'] ?? '' ) ) : ?>
									<span style="color:#b91c1c;font-weight:600;"><?php esc_html_e( 'Error', 'kdna-events' ); ?></span>
								<?php else : ?>
									<span style="color:#047857;font-weight:600;"><?php esc_html_e( 'Success', 'kdna-events' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) ( $entry['message'] ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<script>
		(function () {
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

			function handleResponse(result, res) {
				var message = '';
				if (res.json && res.json.data && res.json.data.message) {
					message = res.json.data.message;
				}
				if (res.json && res.json.success) {
					result.style.color = '#047857';
					result.textContent = message || <?php echo wp_json_encode( __( 'OK', 'kdna-events' ) ); ?>;
				} else {
					result.style.color = '#b91c1c';
					result.textContent = message || <?php echo wp_json_encode( __( 'Failed', 'kdna-events' ) ); ?>;
				}
			}

			var buttons = document.querySelectorAll('.kdna-events-crm-test');
			for (var i = 0; i < buttons.length; i++) {
				buttons[i].addEventListener('click', function (ev) {
					var btn = ev.currentTarget;
					var row = btn.parentNode;
					var result = row.querySelector('.kdna-events-crm-test-result');
					result.style.color = '#4b5563';
					result.textContent = <?php echo wp_json_encode( __( 'Testing...', 'kdna-events' ) ); ?>;
					var body = new URLSearchParams();
					body.append('action', 'kdna_events_crm_test');
					body.append('nonce', btn.getAttribute('data-nonce') || '');
					body.append('integration_id', btn.getAttribute('data-integration') || '');
					fetch(ajaxUrl, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
						body: body.toString()
					}).then(function (r) { return r.json().then(function (j) { return { status: r.status, json: j }; }); })
					  .then(function (res) { handleResponse(result, res); })
					  .catch(function () { result.style.color = '#b91c1c'; result.textContent = <?php echo wp_json_encode( __( 'Network error.', 'kdna-events' ) ); ?>; });
				});
			}

			var clearBtn = document.getElementById('kdna-events-crm-clear-log');
			if (clearBtn) {
				clearBtn.addEventListener('click', function () {
					var result = document.getElementById('kdna-events-crm-clear-log-result');
					result.style.color = '#4b5563';
					result.textContent = <?php echo wp_json_encode( __( 'Clearing...', 'kdna-events' ) ); ?>;
					var body = new URLSearchParams();
					body.append('action', 'kdna_events_crm_clear_log');
					body.append('nonce', clearBtn.getAttribute('data-nonce') || '');
					fetch(ajaxUrl, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
						body: body.toString()
					}).then(function (r) { return r.json().then(function (j) { return { status: r.status, json: j }; }); })
					  .then(function (res) {
					  	handleResponse(result, res);
					  	if (res.json && res.json.success) {
					  		var tbody = document.querySelector('#kdna-events-crm-log-table tbody');
					  		if (tbody) {
					  			tbody.innerHTML = '<tr><td colspan="5">' + <?php echo wp_json_encode( __( 'No entries yet.', 'kdna-events' ) ); ?> + '</td></tr>';
					  		}
					  	}
					  })
					  .catch(function () { result.style.color = '#b91c1c'; result.textContent = <?php echo wp_json_encode( __( 'Network error.', 'kdna-events' ) ); ?>; });
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * Render the Attendees tab: a shared list of attendee fields
	 * applied to every event unless the event opts out.
	 *
	 * @return void
	 */
	protected static function render_attendees_tab() {
		$raw  = (string) get_option( 'kdna_events_global_attendee_fields', '' );
		$rows = array();
		if ( '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$rows = $decoded;
			}
		}

		$types = array(
			'text'   => __( 'Text', 'kdna-events' ),
			'email'  => __( 'Email', 'kdna-events' ),
			'tel'    => __( 'Phone', 'kdna-events' ),
			'select' => __( 'Select', 'kdna-events' ),
		);
		?>
		<p class="description" style="max-width:64em;">
			<?php esc_html_e( 'Custom fields here apply to every event by default. Individual events can add more fields or override a global field by declaring one with the same key. Events can also opt out of the global list entirely from the Event Details meta box.', 'kdna-events' ); ?>
		</p>

		<div
			class="kdna-events-attendee-fields"
			data-kdna-events-attendee-fields
			data-kdna-events-attendee-fields-name="kdna_events_global_attendee_fields"
		>
			<div class="kdna-events-attendee-fields__list" data-kdna-events-attendee-fields-list>
				<?php foreach ( $rows as $index => $row ) :
					$label    = isset( $row['label'] ) ? (string) $row['label'] : '';
					$key      = isset( $row['key'] ) ? (string) $row['key'] : '';
					$type     = isset( $row['type'] ) ? (string) $row['type'] : 'text';
					$required = ! empty( $row['required'] );
					?>
					<div class="kdna-events-attendee-field" data-kdna-events-attendee-field>
						<div class="kdna-events-attendee-field__cell">
							<label><?php esc_html_e( 'Label', 'kdna-events' ); ?></label>
							<input type="text" name="kdna_events_global_attendee_fields[<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" />
						</div>
						<div class="kdna-events-attendee-field__cell">
							<label><?php esc_html_e( 'Key', 'kdna-events' ); ?></label>
							<input type="text" name="kdna_events_global_attendee_fields[<?php echo esc_attr( (string) $index ); ?>][key]" value="<?php echo esc_attr( $key ); ?>" />
						</div>
						<div class="kdna-events-attendee-field__cell">
							<label><?php esc_html_e( 'Type', 'kdna-events' ); ?></label>
							<select name="kdna_events_global_attendee_fields[<?php echo esc_attr( (string) $index ); ?>][type]">
								<?php foreach ( $types as $value => $type_label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $type_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="kdna-events-attendee-field__cell kdna-events-attendee-field__cell--checkbox">
							<label>
								<input type="checkbox" name="kdna_events_global_attendee_fields[<?php echo esc_attr( (string) $index ); ?>][required]" value="1" <?php checked( $required ); ?> />
								<?php esc_html_e( 'Required', 'kdna-events' ); ?>
							</label>
						</div>
						<div class="kdna-events-attendee-field__cell kdna-events-attendee-field__cell--actions">
							<button type="button" class="button-link-delete" data-kdna-events-attendee-field-remove>
								<?php esc_html_e( 'Remove', 'kdna-events' ); ?>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<p>
				<button type="button" class="button" data-kdna-events-attendee-fields-add>
					<?php esc_html_e( 'Add field', 'kdna-events' ); ?>
				</button>
			</p>
			<script type="text/html" data-kdna-events-attendee-fields-template>
				<div class="kdna-events-attendee-field" data-kdna-events-attendee-field>
					<div class="kdna-events-attendee-field__cell">
						<label><?php esc_html_e( 'Label', 'kdna-events' ); ?></label>
						<input type="text" name="kdna_events_global_attendee_fields[{{INDEX}}][label]" value="" />
					</div>
					<div class="kdna-events-attendee-field__cell">
						<label><?php esc_html_e( 'Key', 'kdna-events' ); ?></label>
						<input type="text" name="kdna_events_global_attendee_fields[{{INDEX}}][key]" value="" />
					</div>
					<div class="kdna-events-attendee-field__cell">
						<label><?php esc_html_e( 'Type', 'kdna-events' ); ?></label>
						<select name="kdna_events_global_attendee_fields[{{INDEX}}][type]">
							<?php foreach ( $types as $value => $type_label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $type_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="kdna-events-attendee-field__cell kdna-events-attendee-field__cell--checkbox">
						<label>
							<input type="checkbox" name="kdna_events_global_attendee_fields[{{INDEX}}][required]" value="1" />
							<?php esc_html_e( 'Required', 'kdna-events' ); ?>
						</label>
					</div>
					<div class="kdna-events-attendee-field__cell kdna-events-attendee-field__cell--actions">
						<button type="button" class="button-link-delete" data-kdna-events-attendee-field-remove>
							<?php esc_html_e( 'Remove', 'kdna-events' ); ?>
						</button>
					</div>
				</div>
			</script>
		</div>
		<?php
	}

	/**
	 * Render the Emails tab fields.
	 *
	 * Lists every supported merge tag so site owners can edit the body
	 * without hunting through documentation.
	 *
	 * @return void
	 */
	protected static function render_emails_tab() {
		$body = get_option( 'kdna_events_booking_email_body', self::default_booking_email_body() );
		if ( '' === $body ) {
			$body = self::default_booking_email_body();
		}

		$tags = array(
			'{order_ref}'       => __( 'Order reference code.', 'kdna-events' ),
			'{event_title}'     => __( 'Event title.', 'kdna-events' ),
			'{event_date}'      => __( 'Event start date and time.', 'kdna-events' ),
			'{attendee_name}'   => __( 'Attendee full name.', 'kdna-events' ),
			'{ticket_code}'     => __( 'Individual ticket code.', 'kdna-events' ),
			'{event_location}'  => __( 'Event location, venue or virtual link.', 'kdna-events' ),
			'{organiser_name}'  => __( 'Event organiser name.', 'kdna-events' ),
		);
		?>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><label for="kdna_events_booking_email_body"><?php esc_html_e( 'Booking confirmation body', 'kdna-events' ); ?></label></th>
				<td>
					<textarea id="kdna_events_booking_email_body" name="kdna_events_booking_email_body" rows="12" class="large-text code"><?php echo esc_textarea( (string) $body ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Available merge tags:', 'kdna-events' ); ?></p>
					<ul class="kdna-events-merge-tags">
						<?php foreach ( $tags as $tag => $desc ) : ?>
							<li><code><?php echo esc_html( $tag ); ?></code> <?php echo esc_html( $desc ); ?></li>
						<?php endforeach; ?>
					</ul>
				</td>
			</tr>
			</tbody>
		</table>
		<?php
	}
}
