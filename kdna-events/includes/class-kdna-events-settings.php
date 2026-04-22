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
		add_filter( 'parent_file', array( __CLASS__, 'filter_parent_file' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'filter_submenu_file' ), 10, 2 );
	}

	/**
	 * Return the full list of tab slugs keyed by display label.
	 *
	 * @return array<string,string>
	 */
	public static function tabs() {
		return array(
			'general'      => __( 'General', 'kdna-events' ),
			'attendees'    => __( 'Attendees', 'kdna-events' ),
			'stripe'       => __( 'Stripe', 'kdna-events' ),
			'maps'         => __( 'Google Maps', 'kdna-events' ),
			'pages'        => __( 'Pages', 'kdna-events' ),
			'emails'       => __( 'Emails', 'kdna-events' ),
			'email_design' => __( 'Email Design', 'kdna-events' ),
			'crm'          => __( 'CRM', 'kdna-events' ),
		);
	}

	/**
	 * Return every Email Design option as $key => $defaults.
	 *
	 * Central registry used by register_settings (for Settings API
	 * registration), get_all (for template rendering), the live preview
	 * and the sanitiser. Adding a new control here is enough to make
	 * it flow through the entire stack.
	 *
	 * @return array<string,array{type:string,default:mixed,sanitize?:string|array}>
	 */
	public static function email_design_schema() {
		$heading_font_default = "'Inter', Arial, Helvetica, sans-serif";
		$body_font_default    = "'Inter', Arial, Helvetica, sans-serif";
		$monospace_default    = "Consolas, 'Courier New', monospace";

		return array(
			// Brand.
			'kdna_events_email_logo_id'                => array( 'type' => 'integer', 'default' => 0 ),
			'kdna_events_email_logo_width'             => array( 'type' => 'integer', 'default' => 160 ),
			'kdna_events_email_logo_align'             => array( 'type' => 'string',  'default' => 'center' ),
			'kdna_events_email_default_header_image'   => array( 'type' => 'integer', 'default' => 0 ),
			'kdna_events_email_header_image_max_h'     => array( 'type' => 'integer', 'default' => 300 ),

			// Colours.
			'kdna_events_email_color_primary'          => array( 'type' => 'string', 'default' => '#2E75B6' ),
			'kdna_events_email_color_accent'           => array( 'type' => 'string', 'default' => '#F07759' ),
			'kdna_events_email_color_page_bg'          => array( 'type' => 'string', 'default' => '#EFEFEF' ),
			'kdna_events_email_color_content_bg'       => array( 'type' => 'string', 'default' => '#FFFFFF' ),
			'kdna_events_email_color_heading'          => array( 'type' => 'string', 'default' => '#1A1A1A' ),
			'kdna_events_email_color_body'             => array( 'type' => 'string', 'default' => '#555555' ),
			'kdna_events_email_color_muted'            => array( 'type' => 'string', 'default' => '#888888' ),
			'kdna_events_email_color_divider'          => array( 'type' => 'string', 'default' => '#E5E5E5' ),
			'kdna_events_email_color_button_bg'        => array( 'type' => 'string', 'default' => '#F07759' ),
			'kdna_events_email_color_button_text'      => array( 'type' => 'string', 'default' => '#FFFFFF' ),

			// Typography.
			'kdna_events_email_heading_font'           => array( 'type' => 'string',  'default' => 'google:Inter' ),
			'kdna_events_email_heading_font_custom'    => array( 'type' => 'string',  'default' => $heading_font_default ),
			'kdna_events_email_heading_font_size'      => array( 'type' => 'integer', 'default' => 28 ),
			'kdna_events_email_heading_font_weight'    => array( 'type' => 'integer', 'default' => 700 ),
			'kdna_events_email_body_font'              => array( 'type' => 'string',  'default' => 'google:Inter' ),
			'kdna_events_email_body_font_custom'       => array( 'type' => 'string',  'default' => $body_font_default ),
			'kdna_events_email_body_font_size'         => array( 'type' => 'integer', 'default' => 16 ),
			'kdna_events_email_body_line_height'       => array( 'type' => 'string',  'default' => '1.55' ),
			'kdna_events_email_monospace_font'         => array( 'type' => 'string',  'default' => $monospace_default ),

			// Layout.
			'kdna_events_email_content_max_width'      => array( 'type' => 'integer', 'default' => 600 ),
			'kdna_events_email_content_padding_y'      => array( 'type' => 'integer', 'default' => 32 ),
			'kdna_events_email_content_padding_x'      => array( 'type' => 'integer', 'default' => 28 ),
			'kdna_events_email_card_border_radius'     => array( 'type' => 'integer', 'default' => 8 ),
			'kdna_events_email_button_border_radius'   => array( 'type' => 'integer', 'default' => 28 ),

			// Virtual Event button.
			'kdna_events_email_virtual_button_label'   => array( 'type' => 'string',  'default' => 'Virtual Event link' ),
			'kdna_events_email_virtual_button_bg'      => array( 'type' => 'string',  'default' => '#F07759' ),
			'kdna_events_email_virtual_button_text'    => array( 'type' => 'string',  'default' => '#FFFFFF' ),
			'kdna_events_email_virtual_button_radius'  => array( 'type' => 'integer', 'default' => 28 ),

			// Footer.
			'kdna_events_email_footer_text'            => array(
				'type'    => 'string',
				'default' => 'You received this email because you booked a ticket with us. Please do not reply, this inbox is not monitored.',
			),
			'kdna_events_email_footer_business_name'   => array( 'type' => 'string', 'default' => '' ),

			// Content strings with merge tag support.
			'kdna_events_email_subject_default'        => array(
				'type'    => 'string',
				'default' => 'Your booking for {event_title} is confirmed',
			),
			'kdna_events_email_heading_default'        => array(
				'type'    => 'string',
				'default' => "So you're going to the {event_title}, see you there.",
			),
			'kdna_events_email_content_1_default'      => array(
				'type'    => 'string',
				'default' => "Hi {attendee_name},\n\nThanks for booking {event_title}. Your booking reference is {order_ref}.",
			),
			'kdna_events_email_content_2_default'      => array(
				'type'    => 'string',
				'default' => "If you have any questions, reply to this email.\n\n{organiser_name}",
			),

			// Admin notification strings.
			'kdna_events_email_admin_subject'          => array(
				'type'    => 'string',
				'default' => 'New booking: {event_title} ({quantity} tickets)',
			),
			'kdna_events_email_admin_heading'          => array(
				'type'    => 'string',
				'default' => 'New booking received',
			),
			'kdna_events_email_admin_intro'            => array(
				'type'    => 'string',
				'default' => 'A new booking has been placed for {event_title}.',
			),
			'kdna_events_email_admin_summary_heading'  => array(
				'type'    => 'string',
				'default' => 'Booking Summary',
			),
			'kdna_events_email_admin_event_heading'    => array(
				'type'    => 'string',
				'default' => 'Event Details',
			),
			'kdna_events_email_admin_attendees_heading' => array(
				'type'    => 'string',
				'default' => 'Attendees',
			),
			'kdna_events_email_admin_footer_note'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'kdna_events_email_admin_header_compact'   => array(
				'type'    => 'integer',
				'default' => 1,
			),
		);
	}

	/**
	 * Return every Email Design option value keyed by setting name.
	 *
	 * Applies defaults from email_design_schema() so templates never
	 * have to guard for missing keys.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_email_design() {
		$out = array();
		foreach ( self::email_design_schema() as $name => $def ) {
			$value = get_option( $name, $def['default'] );
			if ( '' === $value || null === $value ) {
				$value = $def['default'];
			}
			$out[ $name ] = $value;
		}
		return $out;
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
	 * Highlight the Events top-level menu when editing any of our CPTs.
	 *
	 * Our CPTs register with show_in_menu=false so WordPress' auto
	 * submenu adder doesn't create duplicates. The trade-off is that
	 * the edit.php / post.php screens lose their default highlighting,
	 * so we point parent_file at the Events parent slug here.
	 *
	 * @param string $parent_file Default parent file.
	 * @return string
	 */
	public static function filter_parent_file( $parent_file ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return $parent_file;
		}

		$ours = array(
			KDNA_Events_CPT::POST_TYPE,
			KDNA_Events_CPT::LOCATION_POST_TYPE,
			KDNA_Events_CPT::ORGANISER_POST_TYPE,
		);
		if ( in_array( $screen->post_type, $ours, true ) ) {
			return self::MENU_SLUG;
		}
		return $parent_file;
	}

	/**
	 * Highlight the correct sub-item on each CPT list / edit screen.
	 *
	 * @param string $submenu_file Default submenu file.
	 * @param string $parent_file  Resolved parent file.
	 * @return string
	 */
	public static function filter_submenu_file( $submenu_file, $parent_file ) {
		unset( $parent_file );
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return $submenu_file;
		}

		switch ( $screen->post_type ) {
			case KDNA_Events_CPT::POST_TYPE:
				return 'edit.php?post_type=' . KDNA_Events_CPT::POST_TYPE;
			case KDNA_Events_CPT::LOCATION_POST_TYPE:
				return 'edit.php?post_type=' . KDNA_Events_CPT::LOCATION_POST_TYPE;
			case KDNA_Events_CPT::ORGANISER_POST_TYPE:
				return 'edit.php?post_type=' . KDNA_Events_CPT::ORGANISER_POST_TYPE;
		}

		return $submenu_file;
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

		// Booking References.
		register_setting(
			'kdna_events_general',
			'kdna_events_reference_prefix',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_reference_token' ),
				'default'           => '',
			)
		);
		register_setting(
			'kdna_events_general',
			'kdna_events_reference_suffix',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_reference_token' ),
				'default'           => '',
			)
		);
		register_setting(
			'kdna_events_general',
			'kdna_events_reference_include_year',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);
		register_setting(
			'kdna_events_general',
			'kdna_events_reference_pad_width',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_pad_width' ),
				'default'           => 6,
			)
		);
		register_setting(
			'kdna_events_general',
			'kdna_events_reference_format',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_reference_format' ),
				'default'           => 'sequential',
			)
		);
		register_setting(
			'kdna_events_general',
			'kdna_events_reference_next',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_reference_next' ),
				'default'           => 1,
			)
		);
		// Send-related settings moved from the v1.0 General tab to the
		// Emails tab in v1.1. Option names are unchanged so existing
		// values carry over; only the form group changes.
		register_setting(
			'kdna_events_emails',
			'kdna_events_admin_notification_email',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'default'           => '',
			)
		);
		register_setting(
			'kdna_events_emails',
			'kdna_events_notify_organiser',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);
		register_setting(
			'kdna_events_emails',
			'kdna_events_email_from_name',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		register_setting(
			'kdna_events_emails',
			'kdna_events_email_from_address',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'default'           => '',
			)
		);
		register_setting(
			'kdna_events_emails',
			'kdna_events_email_reply_to',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'default'           => '',
			)
		);
		register_setting(
			'kdna_events_emails',
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

		// Email Design (branded HTML templates, v1.1 Brief A).
		foreach ( self::email_design_schema() as $name => $def ) {
			$sanitize = array( __CLASS__, 'sanitize_email_design_value' );
			register_setting(
				'kdna_events_email_design',
				$name,
				array(
					'type'              => 'string' === $def['type'] ? 'string' : $def['type'],
					'sanitize_callback' => $sanitize,
					'default'           => $def['default'],
				)
			);
		}

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
	 * Sanitise the prefix / suffix tokens for the booking reference.
	 *
	 * Allowed characters are ASCII letters, digits, and the safe
	 * punctuation used in reference strings (dash, underscore, dot,
	 * slash, hash, space). Anything else is dropped.
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	public static function sanitize_reference_token( $value ) {
		$value = trim( (string) $value );
		$value = preg_replace( '/[^A-Za-z0-9_\-\.\/# ]/', '', $value );
		return substr( (string) $value, 0, 24 );
	}

	/**
	 * Clamp the padding width to 1..12.
	 *
	 * @param mixed $value Raw input.
	 * @return int
	 */
	public static function sanitize_pad_width( $value ) {
		$int = absint( $value );
		if ( $int < 1 ) {
			$int = 1;
		}
		if ( $int > 12 ) {
			$int = 12;
		}
		return $int;
	}

	/**
	 * Guard the booking reference format selector.
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	public static function sanitize_reference_format( $value ) {
		$value = (string) $value;
		if ( 'random' === $value ) {
			return 'random';
		}
		return 'sequential';
	}

	/**
	 * Sanitise the 'Next reference number' counter with a collision guard.
	 *
	 * Admins can jump the counter forwards freely, but jumping backwards
	 * onto a number that already exists in the orders table would
	 * produce duplicate references. When that would happen we clamp the
	 * value to the highest used number + 1 and flash an admin notice.
	 *
	 * @param mixed $value Raw input.
	 * @return int
	 */
	public static function sanitize_reference_next( $value ) {
		global $wpdb;

		$int = absint( $value );
		if ( $int < 1 ) {
			$int = 1;
		}

		if ( ! class_exists( 'KDNA_Events_DB' ) ) {
			return $int;
		}

		$table  = KDNA_Events_DB::orders_table();
		$prefix = (string) get_option( 'kdna_events_reference_prefix', '' );
		$suffix = (string) get_option( 'kdna_events_reference_suffix', '' );
		$width  = max( 1, min( 12, (int) get_option( 'kdna_events_reference_pad_width', 6 ) ) );
		$year   = (int) current_time( 'Y' );
		$inc_y  = (bool) get_option( 'kdna_events_reference_include_year', true );

		$candidate_body = str_pad( (string) $int, $width, '0', STR_PAD_LEFT );
		$parts = array();
		if ( '' !== $prefix ) {
			$parts[] = $prefix;
		}
		if ( $inc_y ) {
			$parts[] = (string) $year;
		}
		$parts[] = $candidate_body;
		$candidate = implode( '-', $parts ) . $suffix;

		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE order_reference = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$candidate
			)
		);

		if ( $exists > 0 ) {
			add_settings_error(
				'kdna_events_reference_next',
				'kdna_events_reference_collision',
				__( 'The starting number you chose is already in use. KDNA Events advanced it to the next free number.', 'kdna-events' ),
				'warning'
			);
			// Advance until we find a free slot.
			$guard = 0;
			do {
				$int++;
				$guard++;
				$candidate_body = str_pad( (string) $int, $width, '0', STR_PAD_LEFT );
				$parts          = array();
				if ( '' !== $prefix ) {
					$parts[] = $prefix;
				}
				if ( $inc_y ) {
					$parts[] = (string) $year;
				}
				$parts[]   = $candidate_body;
				$candidate = implode( '-', $parts ) . $suffix;
				$exists    = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table} WHERE order_reference = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$candidate
					)
				);
			} while ( $exists > 0 && $guard < 1000 );
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
		// Colour pickers + media pickers power the Email Design tab.
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();
		wp_enqueue_script(
			'kdna-events-admin',
			KDNA_EVENTS_URL . 'assets/js/kdna-events-admin.js',
			array( 'jquery', 'wp-color-picker' ),
			KDNA_EVENTS_VERSION,
			true
		);

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'email_design' === $current_tab ) {
			wp_add_inline_script(
				'kdna-events-admin',
				'window.kdnaEventsEmailDesign = ' . wp_json_encode(
					array(
						'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
						'previewNonce' => wp_create_nonce( 'kdna_events_preview_email' ),
						'testNonce'    => wp_create_nonce( 'kdna_events_preview_test_send' ),
					)
				) . ';'
			);
		}
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
					case 'email_design':
						settings_fields( 'kdna_events_email_design' );
						self::render_email_design_tab();
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
		$currency = (string) get_option( 'kdna_events_default_currency', 'AUD' );
		$max_per  = (int) get_option( 'kdna_events_default_max_per_order', 10 );
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
			</tbody>
		</table>

		<?php self::render_reference_settings(); ?>
		<?php
	}

	/**
	 * Render the Booking References settings block.
	 *
	 * Lives on the General tab so admins can set the prefix, suffix,
	 * padding, starting number and sequential / random mode. A live
	 * example string shows exactly what the next booking reference
	 * will look like, which saves a lot of confused back-and-forth.
	 *
	 * @return void
	 */
	protected static function render_reference_settings() {
		$prefix       = (string) get_option( 'kdna_events_reference_prefix', '' );
		$suffix       = (string) get_option( 'kdna_events_reference_suffix', '' );
		$include_year = (bool) get_option( 'kdna_events_reference_include_year', true );
		$pad_width    = max( 1, min( 12, (int) get_option( 'kdna_events_reference_pad_width', 6 ) ) );
		$format       = (string) get_option( 'kdna_events_reference_format', 'sequential' );
		$next         = max( 1, (int) get_option( 'kdna_events_reference_next', 1 ) );

		$year = (int) current_time( 'Y' );
		$example_body = 'sequential' === $format
			? str_pad( (string) $next, $pad_width, '0', STR_PAD_LEFT )
			: strtoupper( wp_generate_password( 6, false, false ) );
		$example_parts = array();
		if ( '' !== $prefix ) {
			$example_parts[] = $prefix;
		}
		if ( $include_year ) {
			$example_parts[] = (string) $year;
		}
		$example_parts[] = $example_body;
		$example = implode( '-', $example_parts ) . $suffix;
		?>
		<h2 class="title" style="margin-top:1.5em;"><?php esc_html_e( 'Booking References', 'kdna-events' ); ?></h2>
		<p class="description" style="max-width:64em;">
			<?php esc_html_e( 'Controls the booking reference shown on confirmation emails, Stripe Checkout and every admin screen. Existing references never change, only new bookings follow the new format.', 'kdna-events' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><label for="kdna_events_reference_prefix"><?php esc_html_e( 'Prefix', 'kdna-events' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="kdna_events_reference_prefix" name="kdna_events_reference_prefix" value="<?php echo esc_attr( $prefix ); ?>" maxlength="24" />
					<p class="description"><?php esc_html_e( 'Optional. Letters, digits, dash, underscore, dot, slash, hash, space. Leave blank for none.', 'kdna-events' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="kdna_events_reference_suffix"><?php esc_html_e( 'Suffix', 'kdna-events' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="kdna_events_reference_suffix" name="kdna_events_reference_suffix" value="<?php echo esc_attr( $suffix ); ?>" maxlength="24" />
					<p class="description"><?php esc_html_e( 'Optional. Appended after the number.', 'kdna-events' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Include year', 'kdna-events' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="kdna_events_reference_include_year" value="1" <?php checked( $include_year ); ?> />
						<?php esc_html_e( 'Insert the current 4-digit year between prefix and number.', 'kdna-events' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="kdna_events_reference_pad_width"><?php esc_html_e( 'Number padding width', 'kdna-events' ); ?></label></th>
				<td>
					<input type="number" min="1" max="12" step="1" id="kdna_events_reference_pad_width" name="kdna_events_reference_pad_width" value="<?php echo esc_attr( (string) $pad_width ); ?>" />
					<p class="description"><?php esc_html_e( 'Zero-pads the sequential counter. 6 renders 1 as 000001.', 'kdna-events' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="kdna_events_reference_format"><?php esc_html_e( 'Format', 'kdna-events' ); ?></label></th>
				<td>
					<select id="kdna_events_reference_format" name="kdna_events_reference_format">
						<option value="sequential" <?php selected( $format, 'sequential' ); ?>><?php esc_html_e( 'Sequential (000001, 000002, ...)', 'kdna-events' ); ?></option>
						<option value="random" <?php selected( $format, 'random' ); ?>><?php esc_html_e( 'Random (6-char alphanumeric)', 'kdna-events' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="kdna_events_reference_next"><?php esc_html_e( 'Next reference number', 'kdna-events' ); ?></label></th>
				<td>
					<input type="number" min="1" step="1" id="kdna_events_reference_next" name="kdna_events_reference_next" value="<?php echo esc_attr( (string) $next ); ?>" />
					<p class="description"><?php esc_html_e( 'Used in Sequential mode. Set to 1000 and the next booking gets that number, then auto-increments from there. Jumping backwards onto an already-used number is auto-corrected forward.', 'kdna-events' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Example', 'kdna-events' ); ?></th>
				<td>
					<code style="font-size:14px;padding:6px 10px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;"><?php echo esc_html( $example ); ?></code>
					<p class="description"><?php esc_html_e( 'Exact preview of the next generated reference. Save changes to update.', 'kdna-events' ); ?></p>
				</td>
			</tr>
			</tbody>
		</table>
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
							esc_html__( 'Create an API key in the %s. Enable the Maps JavaScript API, the Geocoding API and the Places API (New), then restrict the key by HTTP referrer to this site. The Places API powers address autocomplete on the event and location edit screens.', 'kdna-events' ),
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
		$admin_email  = (string) get_option( 'kdna_events_admin_notification_email', '' );
		$notify_org   = (bool) get_option( 'kdna_events_notify_organiser', false );
		$from_name    = (string) get_option( 'kdna_events_email_from_name', '' );
		$from_address = (string) get_option( 'kdna_events_email_from_address', '' );
		$reply_to     = (string) get_option( 'kdna_events_email_reply_to', '' );
		$per_attendee = (bool) get_option( 'kdna_events_per_attendee_emails', false );
		$test_nonce   = wp_create_nonce( 'kdna_events_test_email' );
		?>
		<p class="description" style="max-width:64em;">
			<?php
			printf(
				/* translators: %s: tab label */
				esc_html__( 'Send-related settings for booking and organiser notifications. To change how the email looks, visit the %s tab.', 'kdna-events' ),
				'<strong>' . esc_html__( 'Email Design', 'kdna-events' ) . '</strong>'
			);
			?>
		</p>
		<table class="form-table" role="presentation">
			<tbody>
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
					<p class="description"><?php esc_html_e( 'Booking notifications are sent to this address. Use the button to verify SMTP without completing a booking. The test uses the new branded template.', 'kdna-events' ); ?></p>
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
				<th scope="row"><label for="kdna_events_email_reply_to"><?php esc_html_e( 'Reply-to address', 'kdna-events' ); ?></label></th>
				<td>
					<input type="email" class="regular-text" id="kdna_events_email_reply_to" name="kdna_events_email_reply_to" value="<?php echo esc_attr( $reply_to ); ?>" />
					<p class="description"><?php esc_html_e( 'Optional. If blank, replies go back to the From address.', 'kdna-events' ); ?></p>
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
	 * Render the Email Design tab with every brand / colour /
	 * typography / layout / footer / content-strings control plus a
	 * live preview panel and test-send form.
	 *
	 * @return void
	 */
	protected static function render_email_design_tab() {
		$d = self::get_email_design();

		$logo_id       = (int) $d['kdna_events_email_logo_id'];
		$logo_url      = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		$default_img_id = (int) $d['kdna_events_email_default_header_image'];
		$default_img_url = $default_img_id ? (string) wp_get_attachment_image_url( $default_img_id, 'medium' ) : '';

		$heading_font_options = self::email_font_options();
		?>
		<div class="kdna-events-email-design-grid">
			<div class="kdna-events-email-design-controls">
				<?php self::render_email_design_controls( $d, $logo_id, $logo_url, $default_img_id, $default_img_url, $heading_font_options ); ?>
			</div>
			<?php self::render_email_design_preview_panel(); ?>
		</div>
		<?php self::render_email_design_preview_script(); ?>
		<?php
	}

	/**
	 * Curated font list for the Email Design tab.
	 *
	 * @return array<string,string>
	 */
	public static function email_font_options() {
		return array(
			'google:Inter'            => __( 'Inter (Google, recommended)', 'kdna-events' ),
			'google:Roboto'           => __( 'Roboto (Google)', 'kdna-events' ),
			'google:Poppins'          => __( 'Poppins (Google)', 'kdna-events' ),
			'google:Montserrat'       => __( 'Montserrat (Google)', 'kdna-events' ),
			'google:Open Sans'        => __( 'Open Sans (Google)', 'kdna-events' ),
			'google:Lato'             => __( 'Lato (Google)', 'kdna-events' ),
			'google:Nunito'           => __( 'Nunito (Google)', 'kdna-events' ),
			'google:Work Sans'        => __( 'Work Sans (Google)', 'kdna-events' ),
			'google:DM Sans'          => __( 'DM Sans (Google)', 'kdna-events' ),
			'google:Manrope'          => __( 'Manrope (Google)', 'kdna-events' ),
			'google:Playfair Display' => __( 'Playfair Display (Google, serif)', 'kdna-events' ),
			'google:Merriweather'     => __( 'Merriweather (Google, serif)', 'kdna-events' ),
			'system:arial'            => __( 'Arial (system)', 'kdna-events' ),
			'system:helvetica'        => __( 'Helvetica (system)', 'kdna-events' ),
			'system:georgia'          => __( 'Georgia (serif, system)', 'kdna-events' ),
			'system:verdana'          => __( 'Verdana (system)', 'kdna-events' ),
			'system:tahoma'           => __( 'Tahoma (system)', 'kdna-events' ),
			'system:trebuchet'        => __( 'Trebuchet MS (system)', 'kdna-events' ),
			'system:times'            => __( 'Times New Roman (serif, system)', 'kdna-events' ),
			'custom'                  => __( 'Custom stack', 'kdna-events' ),
		);
	}

	/**
	 * Resolve a font selector to an Outlook-safe font-family stack.
	 *
	 * @param string $value  Selector value.
	 * @param string $custom Custom stack when $value is 'custom'.
	 * @return string
	 */
	public static function email_resolve_font_stack( $value, $custom = '' ) {
		$value = (string) $value;
		if ( 'custom' === $value ) {
			$stack = trim( (string) $custom );
			return '' === $stack ? 'Arial, Helvetica, sans-serif' : $stack;
		}
		if ( 0 === strpos( $value, 'google:' ) ) {
			$family = trim( substr( $value, 7 ) );
			if ( in_array( $family, array( 'Playfair Display', 'Merriweather' ), true ) ) {
				return "'" . $family . "', Georgia, 'Times New Roman', serif";
			}
			return "'" . $family . "', Arial, Helvetica, sans-serif";
		}
		switch ( $value ) {
			case 'system:arial':     return 'Arial, Helvetica, sans-serif';
			case 'system:helvetica': return 'Helvetica, Arial, sans-serif';
			case 'system:verdana':   return 'Verdana, Geneva, sans-serif';
			case 'system:tahoma':    return 'Tahoma, Geneva, sans-serif';
			case 'system:trebuchet': return "'Trebuchet MS', Tahoma, Arial, sans-serif";
			case 'system:georgia':   return "Georgia, 'Times New Roman', serif";
			case 'system:times':     return "'Times New Roman', Times, serif";
		}
		return 'Arial, Helvetica, sans-serif';
	}

	/**
	 * Return the Google Fonts stylesheet URL for a selector, or ''.
	 *
	 * @param string $value   Selector value.
	 * @param string $weights Weights, default '400;600;700'.
	 * @return string
	 */
	public static function email_resolve_google_font_url( $value, $weights = '400;600;700' ) {
		if ( 0 !== strpos( (string) $value, 'google:' ) ) {
			return '';
		}
		$family = trim( substr( (string) $value, 7 ) );
		if ( '' === $family ) {
			return '';
		}
		return 'https://fonts.googleapis.com/css2?family=' . str_replace( ' ', '+', $family ) . ':wght@' . $weights . '&display=swap';
	}

	/**
	 * Sanitise a single Email Design option using the schema type.
	 *
	 * @param mixed $value Raw value from $_POST.
	 * @return mixed
	 */
	public static function sanitize_email_design_value( $value ) {
		$option = '';
		if ( isset( $GLOBALS['wp_current_filter'] ) && is_array( $GLOBALS['wp_current_filter'] ) ) {
			foreach ( $GLOBALS['wp_current_filter'] as $filter ) {
				if ( 0 === strpos( (string) $filter, 'sanitize_option_' ) ) {
					$option = substr( (string) $filter, strlen( 'sanitize_option_' ) );
					break;
				}
			}
		}

		$schema = self::email_design_schema();
		if ( '' === $option || ! isset( $schema[ $option ] ) ) {
			if ( is_scalar( $value ) ) {
				return sanitize_text_field( (string) $value );
			}
			return '';
		}

		$type = $schema[ $option ]['type'];

		switch ( $type ) {
			case 'integer':
				return absint( $value );
			case 'string':
			default:
				$value = (string) $value;
				if ( false !== strpos( $option, 'content' ) || false !== strpos( $option, 'footer_text' ) || false !== strpos( $option, 'intro' ) ) {
					return sanitize_textarea_field( $value );
				}
				if ( 0 === strpos( $option, 'kdna_events_email_color_' ) || in_array( $option, array( 'kdna_events_email_virtual_button_bg', 'kdna_events_email_virtual_button_text' ), true ) ) {
					$value = trim( $value );
					if ( preg_match( '/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value ) ) {
						return strtoupper( $value );
					}
					return $schema[ $option ]['default'];
				}
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Render the sectioned list of Email Design controls.
	 *
	 * @param array  $d                Resolved options.
	 * @param int    $logo_id          Current logo attachment.
	 * @param string $logo_url         Preview URL.
	 * @param int    $default_img_id   Current default header image attachment.
	 * @param string $default_img_url  Preview URL.
	 * @param array  $fonts            Font selector options.
	 * @return void
	 */
	protected static function render_email_design_controls( $d, $logo_id, $logo_url, $default_img_id, $default_img_url, $fonts ) {
		$aligns = array(
			'left'   => __( 'Left', 'kdna-events' ),
			'center' => __( 'Centre', 'kdna-events' ),
		);
		?>
		<div class="kdna-events-email-design-section">
			<h2><?php esc_html_e( 'Brand', 'kdna-events' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Logo', 'kdna-events' ); ?></th>
					<td>
						<div class="kdna-events-email-image-field" data-kdna-events-email-image>
							<input type="hidden" name="kdna_events_email_logo_id" value="<?php echo esc_attr( (string) $logo_id ); ?>" data-kdna-events-email-image-input data-kdna-preview-key="logo_id" />
							<div class="kdna-events-email-image-field__preview" data-kdna-events-email-image-preview>
								<?php if ( $logo_url ) : ?><img src="<?php echo esc_url( $logo_url ); ?>" alt="" /><?php endif; ?>
							</div>
							<p>
								<button type="button" class="button" data-kdna-events-email-image-select><?php echo $logo_url ? esc_html__( 'Change logo', 'kdna-events' ) : esc_html__( 'Select logo', 'kdna-events' ); ?></button>
								<button type="button" class="button-link-delete" data-kdna-events-email-image-remove <?php echo $logo_url ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'kdna-events' ); ?></button>
							</p>
							<p class="description"><?php esc_html_e( 'PNG or JPG, up to 400px wide works best. Sits above the white content card in every email.', 'kdna-events' ); ?></p>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_logo_width"><?php esc_html_e( 'Logo display width', 'kdna-events' ); ?></label></th>
					<td>
						<input type="number" min="40" max="400" step="1" id="kdna_events_email_logo_width" name="kdna_events_email_logo_width" value="<?php echo esc_attr( (string) $d['kdna_events_email_logo_width'] ); ?>" data-kdna-preview-key="logo_width" /> <?php esc_html_e( 'px', 'kdna-events' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_logo_align"><?php esc_html_e( 'Logo alignment', 'kdna-events' ); ?></label></th>
					<td>
						<select id="kdna_events_email_logo_align" name="kdna_events_email_logo_align" data-kdna-preview-key="logo_align">
							<?php foreach ( $aligns as $k => $label ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $d['kdna_events_email_logo_align'], $k ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default event header image', 'kdna-events' ); ?></th>
					<td>
						<div class="kdna-events-email-image-field" data-kdna-events-email-image>
							<input type="hidden" name="kdna_events_email_default_header_image" value="<?php echo esc_attr( (string) $default_img_id ); ?>" data-kdna-events-email-image-input data-kdna-preview-key="default_header_image" />
							<div class="kdna-events-email-image-field__preview" data-kdna-events-email-image-preview>
								<?php if ( $default_img_url ) : ?><img src="<?php echo esc_url( $default_img_url ); ?>" alt="" /><?php endif; ?>
							</div>
							<p>
								<button type="button" class="button" data-kdna-events-email-image-select><?php echo $default_img_url ? esc_html__( 'Change image', 'kdna-events' ) : esc_html__( 'Select image', 'kdna-events' ); ?></button>
								<button type="button" class="button-link-delete" data-kdna-events-email-image-remove <?php echo $default_img_url ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'kdna-events' ); ?></button>
							</p>
							<p class="description"><?php esc_html_e( 'Used when an event has no Email Header Image set.', 'kdna-events' ); ?></p>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_header_image_max_h"><?php esc_html_e( 'Header image max height', 'kdna-events' ); ?></label></th>
					<td>
						<input type="number" min="80" max="600" step="1" id="kdna_events_email_header_image_max_h" name="kdna_events_email_header_image_max_h" value="<?php echo esc_attr( (string) $d['kdna_events_email_header_image_max_h'] ); ?>" data-kdna-preview-key="header_image_max_h" /> <?php esc_html_e( 'px', 'kdna-events' ); ?>
					</td>
				</tr>
				</tbody>
			</table>
		</div>

		<?php self::render_email_design_controls_colours( $d ); ?>
		<?php self::render_email_design_controls_typography( $d, $fonts ); ?>
		<?php self::render_email_design_controls_layout( $d ); ?>
		<?php self::render_email_design_controls_virtual_button( $d ); ?>
		<?php self::render_email_design_controls_content( $d ); ?>
		<?php
	}

	/**
	 * Render the Colours section of the Email Design tab.
	 *
	 * @param array $d Resolved options.
	 * @return void
	 */
	protected static function render_email_design_controls_colours( $d ) {
		$rows = array(
			'kdna_events_email_color_primary'    => __( 'Primary brand colour', 'kdna-events' ),
			'kdna_events_email_color_accent'     => __( 'Accent colour', 'kdna-events' ),
			'kdna_events_email_color_page_bg'    => __( 'Page background', 'kdna-events' ),
			'kdna_events_email_color_content_bg' => __( 'Content card background', 'kdna-events' ),
			'kdna_events_email_color_heading'    => __( 'Heading text', 'kdna-events' ),
			'kdna_events_email_color_body'       => __( 'Body text', 'kdna-events' ),
			'kdna_events_email_color_muted'      => __( 'Muted text (labels, footer)', 'kdna-events' ),
			'kdna_events_email_color_divider'    => __( 'Divider', 'kdna-events' ),
			'kdna_events_email_color_button_bg'  => __( 'Button background', 'kdna-events' ),
			'kdna_events_email_color_button_text' => __( 'Button text', 'kdna-events' ),
		);
		?>
		<div class="kdna-events-email-design-section">
			<h2><?php esc_html_e( 'Colours', 'kdna-events' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<?php foreach ( $rows as $key => $label ) :
						$preview_key = substr( $key, strlen( 'kdna_events_email_color_' ) );
						$current     = (string) $d[ $key ];
						?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td>
								<input type="text" class="kdna-events-color-picker" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $current ); ?>" data-kdna-preview-key="color_<?php echo esc_attr( $preview_key ); ?>" data-default-color="<?php echo esc_attr( $current ); ?>" />
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the Typography section of the Email Design tab.
	 *
	 * @param array $d     Resolved options.
	 * @param array $fonts Font selector options.
	 * @return void
	 */
	protected static function render_email_design_controls_typography( $d, $fonts ) {
		?>
		<div class="kdna-events-email-design-section">
			<h2><?php esc_html_e( 'Typography', 'kdna-events' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><label for="kdna_events_email_heading_font"><?php esc_html_e( 'Heading font', 'kdna-events' ); ?></label></th>
					<td>
						<select id="kdna_events_email_heading_font" name="kdna_events_email_heading_font" data-kdna-preview-key="heading_font">
							<?php foreach ( $fonts as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $d['kdna_events_email_heading_font'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="text" class="regular-text" id="kdna_events_email_heading_font_custom" name="kdna_events_email_heading_font_custom" value="<?php echo esc_attr( (string) $d['kdna_events_email_heading_font_custom'] ); ?>" placeholder="'MyBrand', Helvetica, Arial, sans-serif" data-kdna-preview-key="heading_font_custom" style="margin-top:4px;" />
						<p class="description"><?php esc_html_e( 'Google fonts load in web clients; Outlook Desktop safely falls back to Arial or Helvetica. Custom stack kicks in when the selector is set to Custom.', 'kdna-events' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_heading_font_size"><?php esc_html_e( 'Heading size', 'kdna-events' ); ?></label></th>
					<td><input type="number" min="14" max="48" step="1" id="kdna_events_email_heading_font_size" name="kdna_events_email_heading_font_size" value="<?php echo esc_attr( (string) $d['kdna_events_email_heading_font_size'] ); ?>" data-kdna-preview-key="heading_font_size" /> <?php esc_html_e( 'px', 'kdna-events' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_heading_font_weight"><?php esc_html_e( 'Heading weight', 'kdna-events' ); ?></label></th>
					<td>
						<select id="kdna_events_email_heading_font_weight" name="kdna_events_email_heading_font_weight" data-kdna-preview-key="heading_font_weight">
							<?php foreach ( array( 400, 600, 700 ) as $w ) : ?>
								<option value="<?php echo esc_attr( (string) $w ); ?>" <?php selected( (int) $d['kdna_events_email_heading_font_weight'], $w ); ?>><?php echo esc_html( (string) $w ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_body_font"><?php esc_html_e( 'Body font', 'kdna-events' ); ?></label></th>
					<td>
						<select id="kdna_events_email_body_font" name="kdna_events_email_body_font" data-kdna-preview-key="body_font">
							<?php foreach ( $fonts as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $d['kdna_events_email_body_font'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="text" class="regular-text" name="kdna_events_email_body_font_custom" value="<?php echo esc_attr( (string) $d['kdna_events_email_body_font_custom'] ); ?>" placeholder="'MyBrand', Helvetica, Arial, sans-serif" data-kdna-preview-key="body_font_custom" style="margin-top:4px;" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_body_font_size"><?php esc_html_e( 'Body size', 'kdna-events' ); ?></label></th>
					<td><input type="number" min="12" max="22" step="1" id="kdna_events_email_body_font_size" name="kdna_events_email_body_font_size" value="<?php echo esc_attr( (string) $d['kdna_events_email_body_font_size'] ); ?>" data-kdna-preview-key="body_font_size" /> <?php esc_html_e( 'px', 'kdna-events' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_body_line_height"><?php esc_html_e( 'Body line height', 'kdna-events' ); ?></label></th>
					<td><input type="text" id="kdna_events_email_body_line_height" name="kdna_events_email_body_line_height" value="<?php echo esc_attr( (string) $d['kdna_events_email_body_line_height'] ); ?>" data-kdna-preview-key="body_line_height" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_monospace_font"><?php esc_html_e( 'Monospace / ticket code stack', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="regular-text" id="kdna_events_email_monospace_font" name="kdna_events_email_monospace_font" value="<?php echo esc_attr( (string) $d['kdna_events_email_monospace_font'] ); ?>" data-kdna-preview-key="monospace_font" /></td>
				</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the Layout section of the Email Design tab.
	 *
	 * @param array $d Resolved options.
	 * @return void
	 */
	protected static function render_email_design_controls_layout( $d ) {
		?>
		<div class="kdna-events-email-design-section">
			<h2><?php esc_html_e( 'Layout', 'kdna-events' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><label for="kdna_events_email_content_max_width"><?php esc_html_e( 'Content max width', 'kdna-events' ); ?></label></th>
					<td><input type="number" min="480" max="720" step="10" id="kdna_events_email_content_max_width" name="kdna_events_email_content_max_width" value="<?php echo esc_attr( (string) $d['kdna_events_email_content_max_width'] ); ?>" data-kdna-preview-key="content_max_width" /> <?php esc_html_e( 'px', 'kdna-events' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_content_padding_y"><?php esc_html_e( 'Content padding, top / bottom', 'kdna-events' ); ?></label></th>
					<td><input type="number" min="0" max="80" step="1" id="kdna_events_email_content_padding_y" name="kdna_events_email_content_padding_y" value="<?php echo esc_attr( (string) $d['kdna_events_email_content_padding_y'] ); ?>" data-kdna-preview-key="content_padding_y" /> <?php esc_html_e( 'px', 'kdna-events' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_content_padding_x"><?php esc_html_e( 'Content padding, sides', 'kdna-events' ); ?></label></th>
					<td><input type="number" min="0" max="60" step="1" id="kdna_events_email_content_padding_x" name="kdna_events_email_content_padding_x" value="<?php echo esc_attr( (string) $d['kdna_events_email_content_padding_x'] ); ?>" data-kdna-preview-key="content_padding_x" /> <?php esc_html_e( 'px', 'kdna-events' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_card_border_radius"><?php esc_html_e( 'Content card radius', 'kdna-events' ); ?></label></th>
					<td><input type="number" min="0" max="40" step="1" id="kdna_events_email_card_border_radius" name="kdna_events_email_card_border_radius" value="<?php echo esc_attr( (string) $d['kdna_events_email_card_border_radius'] ); ?>" data-kdna-preview-key="card_border_radius" /> <?php esc_html_e( 'px', 'kdna-events' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_button_border_radius"><?php esc_html_e( 'Button radius', 'kdna-events' ); ?></label></th>
					<td><input type="number" min="0" max="40" step="1" id="kdna_events_email_button_border_radius" name="kdna_events_email_button_border_radius" value="<?php echo esc_attr( (string) $d['kdna_events_email_button_border_radius'] ); ?>" data-kdna-preview-key="button_border_radius" /> <?php esc_html_e( 'px', 'kdna-events' ); ?></td>
				</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the Virtual Event Button section of the Email Design tab.
	 *
	 * @param array $d Resolved options.
	 * @return void
	 */
	protected static function render_email_design_controls_virtual_button( $d ) {
		?>
		<div class="kdna-events-email-design-section">
			<h2><?php esc_html_e( 'Virtual Event Button', 'kdna-events' ); ?></h2>
			<p class="description" style="max-width:64em;">
				<?php esc_html_e( 'Shown in the booking confirmation email only when the event type is Virtual or Hybrid and a Virtual URL is set on the event. The button links to that URL.', 'kdna-events' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><label for="kdna_events_email_virtual_button_label"><?php esc_html_e( 'Button label', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="regular-text" id="kdna_events_email_virtual_button_label" name="kdna_events_email_virtual_button_label" value="<?php echo esc_attr( (string) $d['kdna_events_email_virtual_button_label'] ); ?>" data-kdna-preview-key="virtual_button_label" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_virtual_button_bg"><?php esc_html_e( 'Background colour', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="kdna-events-color-picker" id="kdna_events_email_virtual_button_bg" name="kdna_events_email_virtual_button_bg" value="<?php echo esc_attr( (string) $d['kdna_events_email_virtual_button_bg'] ); ?>" data-kdna-preview-key="virtual_button_bg" data-default-color="<?php echo esc_attr( (string) $d['kdna_events_email_virtual_button_bg'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_virtual_button_text"><?php esc_html_e( 'Text colour', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="kdna-events-color-picker" id="kdna_events_email_virtual_button_text" name="kdna_events_email_virtual_button_text" value="<?php echo esc_attr( (string) $d['kdna_events_email_virtual_button_text'] ); ?>" data-kdna-preview-key="virtual_button_text" data-default-color="<?php echo esc_attr( (string) $d['kdna_events_email_virtual_button_text'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_virtual_button_radius"><?php esc_html_e( 'Border radius', 'kdna-events' ); ?></label></th>
					<td><input type="number" min="0" max="40" step="1" id="kdna_events_email_virtual_button_radius" name="kdna_events_email_virtual_button_radius" value="<?php echo esc_attr( (string) $d['kdna_events_email_virtual_button_radius'] ); ?>" data-kdna-preview-key="virtual_button_radius" /> <?php esc_html_e( 'px', 'kdna-events' ); ?></td>
				</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the Content Strings and Footer sections of the Email Design tab.
	 *
	 * @param array $d Resolved options.
	 * @return void
	 */
	protected static function render_email_design_controls_content( $d ) {
		?>
		<div class="kdna-events-email-design-section">
			<h2><?php esc_html_e( 'Customer Email Content', 'kdna-events' ); ?></h2>
			<p class="description" style="max-width:64em;">
				<?php esc_html_e( 'Defaults used when an event does not set its own override. Supports merge tags: {event_title}, {attendee_name}, {order_ref}, {event_date}, {event_time}, {event_type}, {event_location}, {organiser_name}, {purchaser_name}, {ticket_code}, {quantity}, {total}.', 'kdna-events' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><label for="kdna_events_email_subject_default"><?php esc_html_e( 'Subject line', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="large-text" id="kdna_events_email_subject_default" name="kdna_events_email_subject_default" value="<?php echo esc_attr( (string) $d['kdna_events_email_subject_default'] ); ?>" data-kdna-preview-key="subject_default" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_heading_default"><?php esc_html_e( 'Heading', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="large-text" id="kdna_events_email_heading_default" name="kdna_events_email_heading_default" value="<?php echo esc_attr( (string) $d['kdna_events_email_heading_default'] ); ?>" data-kdna-preview-key="heading_default" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_content_1_default"><?php esc_html_e( 'Content 1', 'kdna-events' ); ?></label></th>
					<td><textarea class="large-text" rows="3" id="kdna_events_email_content_1_default" name="kdna_events_email_content_1_default" data-kdna-preview-key="content_1_default"><?php echo esc_textarea( (string) $d['kdna_events_email_content_1_default'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_content_2_default"><?php esc_html_e( 'Content 2', 'kdna-events' ); ?></label></th>
					<td><textarea class="large-text" rows="3" id="kdna_events_email_content_2_default" name="kdna_events_email_content_2_default" data-kdna-preview-key="content_2_default"><?php echo esc_textarea( (string) $d['kdna_events_email_content_2_default'] ); ?></textarea></td>
				</tr>
				</tbody>
			</table>
		</div>

		<div class="kdna-events-email-design-section">
			<h2><?php esc_html_e( 'Admin Email Content', 'kdna-events' ); ?></h2>
			<p class="description" style="max-width:64em;">
				<?php esc_html_e( 'Internal notifications to the admin and optional organiser. Share the same branding as the customer email, differ only in layout and content structure.', 'kdna-events' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><label for="kdna_events_email_admin_subject"><?php esc_html_e( 'Subject line', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="large-text" id="kdna_events_email_admin_subject" name="kdna_events_email_admin_subject" value="<?php echo esc_attr( (string) $d['kdna_events_email_admin_subject'] ); ?>" data-kdna-preview-key="admin_subject" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_admin_heading"><?php esc_html_e( 'Heading', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="large-text" id="kdna_events_email_admin_heading" name="kdna_events_email_admin_heading" value="<?php echo esc_attr( (string) $d['kdna_events_email_admin_heading'] ); ?>" data-kdna-preview-key="admin_heading" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_admin_intro"><?php esc_html_e( 'Intro paragraph', 'kdna-events' ); ?></label></th>
					<td><textarea class="large-text" rows="2" id="kdna_events_email_admin_intro" name="kdna_events_email_admin_intro" data-kdna-preview-key="admin_intro"><?php echo esc_textarea( (string) $d['kdna_events_email_admin_intro'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_admin_summary_heading"><?php esc_html_e( 'Booking summary section heading', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="large-text" id="kdna_events_email_admin_summary_heading" name="kdna_events_email_admin_summary_heading" value="<?php echo esc_attr( (string) $d['kdna_events_email_admin_summary_heading'] ); ?>" data-kdna-preview-key="admin_summary_heading" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_admin_event_heading"><?php esc_html_e( 'Event section heading', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="large-text" id="kdna_events_email_admin_event_heading" name="kdna_events_email_admin_event_heading" value="<?php echo esc_attr( (string) $d['kdna_events_email_admin_event_heading'] ); ?>" data-kdna-preview-key="admin_event_heading" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_admin_attendees_heading"><?php esc_html_e( 'Attendees section heading', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="large-text" id="kdna_events_email_admin_attendees_heading" name="kdna_events_email_admin_attendees_heading" value="<?php echo esc_attr( (string) $d['kdna_events_email_admin_attendees_heading'] ); ?>" data-kdna-preview-key="admin_attendees_heading" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_admin_footer_note"><?php esc_html_e( 'Footer note', 'kdna-events' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="2" id="kdna_events_email_admin_footer_note" name="kdna_events_email_admin_footer_note" data-kdna-preview-key="admin_footer_note"><?php echo esc_textarea( (string) $d['kdna_events_email_admin_footer_note'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Optional. Shown ABOVE the shared footer. Useful for per-client internal instructions (e.g. check the dashboard).', 'kdna-events' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Compact header', 'kdna-events' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="kdna_events_email_admin_header_compact" value="1" <?php checked( ! empty( $d['kdna_events_email_admin_header_compact'] ) ); ?> data-kdna-preview-key="admin_header_compact" />
							<?php esc_html_e( 'Render the admin email with a tighter logo block and less top padding.', 'kdna-events' ); ?>
						</label>
					</td>
				</tr>
				</tbody>
			</table>
		</div>

		<div class="kdna-events-email-design-section">
			<h2><?php esc_html_e( 'Footer', 'kdna-events' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><label for="kdna_events_email_footer_business_name"><?php esc_html_e( 'Business name', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="regular-text" id="kdna_events_email_footer_business_name" name="kdna_events_email_footer_business_name" value="<?php echo esc_attr( (string) $d['kdna_events_email_footer_business_name'] ); ?>" placeholder="<?php echo esc_attr( (string) get_bloginfo( 'name' ) ); ?>" data-kdna-preview-key="footer_business_name" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_events_email_footer_text"><?php esc_html_e( 'Footer text', 'kdna-events' ); ?></label></th>
					<td><textarea class="large-text" rows="3" id="kdna_events_email_footer_text" name="kdna_events_email_footer_text" data-kdna-preview-key="footer_text"><?php echo esc_textarea( (string) $d['kdna_events_email_footer_text'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Shown below the white content card. Individual events can override this under the event meta box.', 'kdna-events' ); ?></p>
					</td>
				</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the live preview panel and test-send area.
	 *
	 * @return void
	 */
	protected static function render_email_design_preview_panel() {
		?>
		<div class="kdna-events-email-design-preview" data-kdna-events-email-preview>
			<div class="kdna-events-email-design-preview__tabs">
				<button type="button" class="kdna-events-email-design-preview__tab is-active" data-target="booking_confirmation"><?php esc_html_e( 'Booking Confirmation', 'kdna-events' ); ?></button>
				<button type="button" class="kdna-events-email-design-preview__tab" data-target="admin_notification"><?php esc_html_e( 'Admin Notification', 'kdna-events' ); ?></button>
				<button type="button" class="button button-secondary" style="margin-left:auto;" data-kdna-events-email-preview-refresh><?php esc_html_e( 'Refresh preview', 'kdna-events' ); ?></button>
			</div>
			<div class="kdna-events-email-design-preview__toggles" role="group">
				<span class="kdna-events-email-design-preview__toggles-label"><?php esc_html_e( 'Mode:', 'kdna-events' ); ?></span>
				<button type="button" class="kdna-events-email-design-preview__toggle is-active" data-preview-mode="light"><?php esc_html_e( 'Light', 'kdna-events' ); ?></button>
				<button type="button" class="kdna-events-email-design-preview__toggle" data-preview-mode="dark"><?php esc_html_e( 'Dark', 'kdna-events' ); ?></button>
				<span class="kdna-events-email-design-preview__toggles-label" style="margin-left:14px;"><?php esc_html_e( 'Device:', 'kdna-events' ); ?></span>
				<button type="button" class="kdna-events-email-design-preview__toggle is-active" data-preview-device="desktop"><?php esc_html_e( 'Desktop', 'kdna-events' ); ?></button>
				<button type="button" class="kdna-events-email-design-preview__toggle" data-preview-device="mobile"><?php esc_html_e( 'Mobile', 'kdna-events' ); ?></button>
			</div>
			<div class="kdna-events-email-design-preview__viewport" data-kdna-events-email-preview-viewport>
				<iframe class="kdna-events-email-design-preview__frame" data-kdna-events-email-preview-frame title="<?php esc_attr_e( 'Email preview', 'kdna-events' ); ?>" srcdoc="&lt;p style=&quot;font:14px sans-serif;color:#555;padding:2em;&quot;&gt;<?php echo esc_attr__( 'Loading preview...', 'kdna-events' ); ?>&lt;/p&gt;"></iframe>
			</div>
			<div class="kdna-events-email-design-preview__send">
				<label for="kdna-events-email-preview-test-to" class="screen-reader-text"><?php esc_html_e( 'Send test to email address', 'kdna-events' ); ?></label>
				<input type="email" id="kdna-events-email-preview-test-to" placeholder="<?php esc_attr_e( 'you@example.com', 'kdna-events' ); ?>" />
				<button type="button" class="button button-primary" data-kdna-events-email-preview-send><?php esc_html_e( 'Send test to inbox', 'kdna-events' ); ?></button>
				<span class="kdna-events-email-design-preview__status" role="status" aria-live="polite"></span>
			</div>
			<p class="description" style="margin-top:8px;">
				<?php esc_html_e( 'Preview uses dummy event and attendee data. Save your settings before sending a real test so the sent copy matches what you see.', 'kdna-events' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Emit the client-side JS that drives the live preview + test send.
	 *
	 * @return void
	 */
	protected static function render_email_design_preview_script() {
		$cfg = array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'previewNonce' => wp_create_nonce( 'kdna_events_preview_email' ),
			'testNonce'    => wp_create_nonce( 'kdna_events_preview_test_send' ),
		);
		?>
		<script>
		window.kdnaEventsEmailDesign = <?php echo wp_json_encode( $cfg ); ?>;
		(function () {
			var cfg = window.kdnaEventsEmailDesign || {};
			if (!cfg.ajaxUrl) { return; }

			var root = document.querySelector('[data-kdna-events-email-preview]');
			if (!root) { return; }

			var frame = root.querySelector('[data-kdna-events-email-preview-frame]');
			var viewport = root.querySelector('[data-kdna-events-email-preview-viewport]');
			var status = root.querySelector('.kdna-events-email-design-preview__status');
			var refresh = root.querySelector('[data-kdna-events-email-preview-refresh]');
			var sendBtn = root.querySelector('[data-kdna-events-email-preview-send]');
			var toInput = root.querySelector('#kdna-events-email-preview-test-to');
			var tabs = root.querySelectorAll('.kdna-events-email-design-preview__tab');
			var modeToggles = root.querySelectorAll('[data-preview-mode]');
			var deviceToggles = root.querySelectorAll('[data-preview-device]');
			var form = root.closest('form');

			var currentTemplate = 'booking_confirmation';
			var currentMode = 'light';
			var currentDevice = 'desktop';
			var debounceTimer = null;

			function applyDevice() {
				if (!viewport) { return; }
				if ('mobile' === currentDevice) {
					viewport.style.maxWidth = '400px';
					viewport.style.margin = '0 auto';
				} else {
					viewport.style.maxWidth = '';
					viewport.style.margin = '';
				}
			}

			function collectPayload() {
				var fd = new FormData();
				fd.append('action', 'kdna_events_preview_email');
				fd.append('nonce', cfg.previewNonce);
				fd.append('template', currentTemplate);
				fd.append('preview_mode', currentMode);
				if (form) {
					var controls = form.querySelectorAll('[name^="kdna_events_email_"], [name="kdna_events_email_logo_id"], [name="kdna_events_email_default_header_image"]');
					controls.forEach(function (el) {
						if (el.type === 'checkbox') {
							if (el.checked) { fd.append(el.name, el.value || '1'); }
						} else {
							fd.append(el.name, el.value);
						}
					});
				}
				return fd;
			}

			function setStatus(text, isError) {
				if (!status) { return; }
				status.textContent = text || '';
				status.style.color = isError ? '#b91c1c' : '#059669';
			}

			function renderPreview() {
				setStatus('');
				fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: collectPayload() })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						if (res && res.success && res.data && res.data.html) {
							frame.setAttribute('srcdoc', res.data.html);
						} else {
							setStatus(res && res.data && res.data.message ? res.data.message : 'Preview failed.', true);
						}
					})
					.catch(function () { setStatus('Network error rendering preview.', true); });
			}

			function scheduleRender() {
				if (debounceTimer) { clearTimeout(debounceTimer); }
				debounceTimer = setTimeout(renderPreview, 300);
			}

			// React to any control change inside the form, including wp.media picks.
			if (form) {
				form.addEventListener('input', scheduleRender);
				form.addEventListener('change', scheduleRender);
				form.addEventListener('kdna:email-image-change', scheduleRender);
			}
			if (refresh) { refresh.addEventListener('click', renderPreview); }

			tabs.forEach(function (tab) {
				tab.addEventListener('click', function () {
					tabs.forEach(function (t) { t.classList.remove('is-active'); });
					tab.classList.add('is-active');
					currentTemplate = tab.getAttribute('data-target') || 'booking_confirmation';
					renderPreview();
				});
			});

			modeToggles.forEach(function (btn) {
				btn.addEventListener('click', function () {
					modeToggles.forEach(function (b) { b.classList.remove('is-active'); });
					btn.classList.add('is-active');
					currentMode = btn.getAttribute('data-preview-mode') || 'light';
					renderPreview();
				});
			});

			deviceToggles.forEach(function (btn) {
				btn.addEventListener('click', function () {
					deviceToggles.forEach(function (b) { b.classList.remove('is-active'); });
					btn.classList.add('is-active');
					currentDevice = btn.getAttribute('data-preview-device') || 'desktop';
					applyDevice();
				});
			});

			// Initial render.
			applyDevice();
			renderPreview();

			if (sendBtn) {
				sendBtn.addEventListener('click', function () {
					var to = (toInput && toInput.value || '').trim();
					if (!to) { setStatus('Enter an email address first.', true); return; }
					setStatus('Sending...', false);
					status.style.color = '#4b5563';
					var body = new FormData();
					body.append('action', 'kdna_events_preview_test_send');
					body.append('nonce', cfg.testNonce);
					body.append('to', to);
					body.append('template', currentTemplate);
					body.append('preview_mode', currentMode);
					fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
						.then(function (r) { return r.json(); })
						.then(function (res) {
							if (res && res.success) {
								setStatus(res.data && res.data.message ? res.data.message : 'Sent.', false);
							} else {
								setStatus(res && res.data && res.data.message ? res.data.message : 'Send failed.', true);
							}
						})
						.catch(function () { setStatus('Network error sending test.', true); });
				});
			}
		})();
		</script>
		<?php
	}
}
