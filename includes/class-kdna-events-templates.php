<?php
/**
 * Template and front-end integration for KDNA Events.
 *
 * Registers the Elementor widget category at file load time, adds body
 * classes on the assigned Checkout, Success and My Tickets pages, and
 * registers plus conditionally enqueues the global front-end bundle with
 * a localised kdnaEvents JS object.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the permalink of an assigned template page by slug.
 *
 * Accepted slugs: 'checkout', 'success', 'my_tickets' (or its alias
 * 'my-tickets'). Returns an empty string when no page is assigned.
 *
 * @param string $slug Page slug to look up.
 * @return string
 */
function kdna_events_get_page_url( $slug ) {
	$slug = strtolower( str_replace( '-', '_', (string) $slug ) );
	$map  = array(
		'checkout'   => 'kdna_events_template_checkout',
		'success'    => 'kdna_events_template_success',
		'my_tickets' => 'kdna_events_template_my_tickets',
	);

	if ( ! isset( $map[ $slug ] ) ) {
		return '';
	}

	$page_id = (int) get_option( $map[ $slug ], 0 );
	if ( ! $page_id ) {
		return '';
	}

	$url = get_permalink( $page_id );
	return $url ? $url : '';
}

/**
 * Registers the widget category, body classes and front-end enqueue.
 */
class KDNA_Events_Templates {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'body_class', array( __CLASS__, 'add_body_classes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend' ) );
	}

	/**
	 * Register the KDNA Widgets Elementor category.
	 *
	 * Called from the elementor/elements/categories_registered hook that
	 * is registered at file load time via self::register_widget_category_hook.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elements manager.
	 * @return void
	 */
	public static function register_widget_category( $elements_manager ) {
		if ( ! is_object( $elements_manager ) || ! method_exists( $elements_manager, 'add_category' ) ) {
			return;
		}

		$elements_manager->add_category(
			'kdna-widgets',
			array(
				'title' => __( 'KDNA Widgets', 'kdna-events' ),
				'icon'  => 'eicon-calendar',
			)
		);
	}

	/**
	 * Add context-specific body classes on assigned template pages.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public static function add_body_classes( $classes ) {
		if ( ! is_page() ) {
			return $classes;
		}

		$page_id = (int) get_queried_object_id();
		if ( ! $page_id ) {
			return $classes;
		}

		if ( $page_id === (int) get_option( 'kdna_events_template_checkout', 0 ) ) {
			$classes[] = 'kdna-events-checkout';
		}
		if ( $page_id === (int) get_option( 'kdna_events_template_success', 0 ) ) {
			$classes[] = 'kdna-events-success';
		}
		if ( $page_id === (int) get_option( 'kdna_events_template_my_tickets', 0 ) ) {
			$classes[] = 'kdna-events-my-tickets';
		}

		return $classes;
	}

	/**
	 * Determine whether the front-end bundle should load on this request.
	 *
	 * Scopes enqueuing to event single, event archive, event category
	 * archive, and the three assigned template pages.
	 *
	 * @return bool
	 */
	protected static function should_enqueue_frontend() {
		if ( is_singular( KDNA_Events_CPT::POST_TYPE ) ) {
			return true;
		}
		if ( is_post_type_archive( KDNA_Events_CPT::POST_TYPE ) ) {
			return true;
		}
		if ( is_tax( KDNA_Events_CPT::TAXONOMY ) ) {
			return true;
		}

		if ( is_page() ) {
			$page_id  = (int) get_queried_object_id();
			$assigned = array(
				(int) get_option( 'kdna_events_template_checkout', 0 ),
				(int) get_option( 'kdna_events_template_success', 0 ),
				(int) get_option( 'kdna_events_template_my_tickets', 0 ),
			);
			if ( in_array( $page_id, $assigned, true ) && $page_id > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Register and conditionally enqueue the global front-end bundle.
	 *
	 * Registers the stub JS and CSS so later stages can hard-enqueue
	 * them from widget-level render methods, and enqueues them directly
	 * when the current request is an event or assigned template page.
	 *
	 * @return void
	 */
	public static function enqueue_frontend() {
		wp_register_style(
			'kdna-events-frontend',
			KDNA_EVENTS_URL . 'assets/css/kdna-events-frontend.css',
			array(),
			KDNA_EVENTS_VERSION
		);

		wp_register_script(
			'kdna-events-frontend',
			KDNA_EVENTS_URL . 'assets/js/kdna-events-frontend.js',
			array( 'jquery' ),
			KDNA_EVENTS_VERSION,
			true
		);

		wp_register_script(
			'kdna-events-maps',
			KDNA_EVENTS_URL . 'assets/js/kdna-events-maps.js',
			array( 'jquery', 'kdna-events-frontend' ),
			KDNA_EVENTS_VERSION,
			true
		);

		wp_register_script(
			'kdna-events-checkout',
			KDNA_EVENTS_URL . 'assets/js/kdna-events-checkout.js',
			array( 'kdna-events-frontend' ),
			KDNA_EVENTS_VERSION,
			true
		);

		$data = array(
			'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
			'nonce'              => wp_create_nonce( 'kdna_events_frontend' ),
			'checkoutUrl'        => kdna_events_get_page_url( 'checkout' ),
			'successUrl'         => kdna_events_get_page_url( 'success' ),
			'myTicketsUrl'       => kdna_events_get_page_url( 'my_tickets' ),
			'currency'           => (string) get_option( 'kdna_events_default_currency', 'AUD' ),
			'defaultMaxPerOrder' => (int) get_option( 'kdna_events_default_max_per_order', 10 ),
			'maps'               => array(
				'apiKey' => (string) get_option( 'kdna_events_google_maps_api_key', '' ),
			),
		);

		wp_localize_script( 'kdna-events-frontend', 'kdnaEvents', $data );

		if ( ! self::should_enqueue_frontend() ) {
			return;
		}

		wp_enqueue_style( 'kdna-events-frontend' );
		wp_enqueue_script( 'kdna-events-frontend' );
	}
}

/**
 * Elementor widget category registration at file load time.
 *
 * Registered outside the class per Section 2: 'Register all Elementor
 * hooks at file load time. Never wrap hook registrations inside
 * elementor/loaded, because that action may have already fired.'
 */
add_action(
	'elementor/elements/categories_registered',
	array( 'KDNA_Events_Templates', 'register_widget_category' )
);
