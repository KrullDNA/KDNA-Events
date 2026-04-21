<?php
/**
 * Custom post type, taxonomy and meta registration for KDNA Events.
 *
 * Registers the kdna_event post type, its hierarchical category taxonomy
 * and every event meta field per Section 4 of the project brief.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Events CPT, taxonomy and meta fields.
 */
class KDNA_Events_CPT {

	/**
	 * CPT slug.
	 */
	const POST_TYPE = 'kdna_event';

	/**
	 * Category taxonomy slug.
	 */
	const TAXONOMY = 'kdna_event_category';

	/**
	 * Wire up the CPT, taxonomy and meta registration on init.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	/**
	 * Register the kdna_event post type.
	 *
	 * Labels are plain (Event, Events) so the sidebar stays clean. Plugin
	 * branding stays in the plugin header and on the settings page.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		$labels = array(
			'name'                  => _x( 'Events', 'Post type general name', 'kdna-events' ),
			'singular_name'         => _x( 'Event', 'Post type singular name', 'kdna-events' ),
			'menu_name'             => _x( 'Events', 'Admin Menu text', 'kdna-events' ),
			'name_admin_bar'        => _x( 'Event', 'Add New on Toolbar', 'kdna-events' ),
			'add_new'               => __( 'Add New', 'kdna-events' ),
			'add_new_item'          => __( 'Add New Event', 'kdna-events' ),
			'new_item'              => __( 'New Event', 'kdna-events' ),
			'edit_item'             => __( 'Edit Event', 'kdna-events' ),
			'view_item'             => __( 'View Event', 'kdna-events' ),
			'all_items'             => __( 'All Events', 'kdna-events' ),
			'search_items'          => __( 'Search Events', 'kdna-events' ),
			'parent_item_colon'     => __( 'Parent Events:', 'kdna-events' ),
			'not_found'             => __( 'No events found.', 'kdna-events' ),
			'not_found_in_trash'    => __( 'No events found in Trash.', 'kdna-events' ),
			'featured_image'        => __( 'Event Featured Image', 'kdna-events' ),
			'set_featured_image'    => __( 'Set featured image', 'kdna-events' ),
			'remove_featured_image' => __( 'Remove featured image', 'kdna-events' ),
			'use_featured_image'    => __( 'Use as featured image', 'kdna-events' ),
			'archives'              => __( 'Event archives', 'kdna-events' ),
			'insert_into_item'      => __( 'Insert into event', 'kdna-events' ),
			'uploaded_to_this_item' => __( 'Uploaded to this event', 'kdna-events' ),
			'filter_items_list'     => __( 'Filter events list', 'kdna-events' ),
			'items_list_navigation' => __( 'Events list navigation', 'kdna-events' ),
			'items_list'            => __( 'Events list', 'kdna-events' ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Events created with KDNA Events.', 'kdna-events' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'kdna-events',
			'show_in_rest'       => true,
			'rest_base'          => 'events',
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => 'events',
				'with_front' => false,
			),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 21,
			'menu_icon'          => 'dashicons-calendar-alt',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'taxonomies'         => array( self::TAXONOMY ),
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register the hierarchical event category taxonomy.
	 *
	 * @return void
	 */
	public static function register_taxonomy() {
		$labels = array(
			'name'              => _x( 'Event Categories', 'taxonomy general name', 'kdna-events' ),
			'singular_name'     => _x( 'Event Category', 'taxonomy singular name', 'kdna-events' ),
			'search_items'      => __( 'Search Event Categories', 'kdna-events' ),
			'all_items'         => __( 'All Event Categories', 'kdna-events' ),
			'parent_item'       => __( 'Parent Event Category', 'kdna-events' ),
			'parent_item_colon' => __( 'Parent Event Category:', 'kdna-events' ),
			'edit_item'         => __( 'Edit Event Category', 'kdna-events' ),
			'update_item'       => __( 'Update Event Category', 'kdna-events' ),
			'add_new_item'      => __( 'Add New Event Category', 'kdna-events' ),
			'new_item_name'     => __( 'New Event Category Name', 'kdna-events' ),
			'menu_name'         => __( 'Categories', 'kdna-events' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array(
				'slug'         => 'event-category',
				'with_front'   => false,
				'hierarchical' => true,
			),
		);

		register_taxonomy( self::TAXONOMY, array( self::POST_TYPE ), $args );
	}

	/**
	 * Shared auth callback for every event meta field.
	 *
	 * Gates REST writes to users who can edit the specific event. For
	 * classic admin saves, nonces in class-kdna-events-admin.php provide
	 * the primary gate.
	 *
	 * @param bool   $allowed  Whether the user can add the meta.
	 * @param string $meta_key Meta key being updated.
	 * @param int    $post_id  Post ID.
	 * @return bool
	 */
	public static function auth_callback( $allowed, $meta_key, $post_id ) {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_post', (int) $post_id );
	}

	/**
	 * Register every event meta field per Section 4 of the brief.
	 *
	 * @return void
	 */
	public static function register_meta() {
		$auth = array( __CLASS__, 'auth_callback' );

		$string_fields = array(
			'_kdna_event_subtitle',
			'_kdna_event_start',
			'_kdna_event_end',
			'_kdna_event_timezone',
			'_kdna_event_registration_opens',
			'_kdna_event_registration_closes',
			'_kdna_event_currency',
			'_kdna_event_type',
			'_kdna_event_virtual_url',
			'_kdna_event_organiser_name',
			'_kdna_event_organiser_email',
		);

		foreach ( $string_fields as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => $auth,
				)
			);
		}

		register_post_meta(
			self::POST_TYPE,
			'_kdna_event_price',
			array(
				'type'              => 'number',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => 0,
				'sanitize_callback' => array( __CLASS__, 'sanitize_float' ),
				'auth_callback'     => $auth,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_kdna_event_ignore_global_attendee_fields',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => $auth,
			)
		);

		$int_fields = array(
			'_kdna_event_capacity',
			'_kdna_event_min_tickets_per_order',
			'_kdna_event_max_tickets_per_order',
		);

		foreach ( $int_fields as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'              => 'integer',
					'single'            => true,
					'show_in_rest'      => true,
					'default'           => 0,
					'sanitize_callback' => 'absint',
					'auth_callback'     => $auth,
				)
			);
		}

		register_post_meta(
			self::POST_TYPE,
			'_kdna_event_location',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_location_json' ),
				'auth_callback'     => $auth,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_kdna_event_attendee_fields',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_attendee_fields_json' ),
				'auth_callback'     => $auth,
			)
		);
	}

	/**
	 * Sanitise a decimal value cast to float.
	 *
	 * @param mixed $value Incoming value.
	 * @return float
	 */
	public static function sanitize_float( $value ) {
		return (float) $value;
	}

	/**
	 * Sanitise the location JSON blob.
	 *
	 * Accepts either an array (from REST) or a JSON string and normalises
	 * to a JSON string with name, address, lat and lng keys.
	 *
	 * @param mixed $value Incoming value.
	 * @return string
	 */
	public static function sanitize_location_json( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$data = is_array( $value ) ? $value : json_decode( (string) $value, true );

		if ( ! is_array( $data ) ) {
			return '';
		}

		$clean = array(
			'name'    => isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '',
			'address' => isset( $data['address'] ) ? sanitize_text_field( (string) $data['address'] ) : '',
			'lat'     => isset( $data['lat'] ) ? (float) $data['lat'] : 0.0,
			'lng'     => isset( $data['lng'] ) ? (float) $data['lng'] : 0.0,
		);

		return wp_json_encode( $clean );
	}

	/**
	 * Sanitise the custom attendee fields JSON blob.
	 *
	 * Accepts an array or JSON string of items each shaped as
	 * { label, key, type, required }. Unknown types fall back to 'text'.
	 *
	 * @param mixed $value Incoming value.
	 * @return string
	 */
	public static function sanitize_attendee_fields_json( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$data = is_array( $value ) ? $value : json_decode( (string) $value, true );

		if ( ! is_array( $data ) ) {
			return '';
		}

		$allowed_types = array( 'text', 'email', 'tel', 'select' );
		$clean         = array();

		foreach ( $data as $row ) {
			if ( ! is_array( $row ) || empty( $row['label'] ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) $row['label'] );
			$key   = isset( $row['key'] ) ? sanitize_key( (string) $row['key'] ) : sanitize_key( $label );
			$type  = isset( $row['type'] ) ? sanitize_key( (string) $row['type'] ) : 'text';

			if ( ! in_array( $type, $allowed_types, true ) ) {
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
}
