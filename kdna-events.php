<?php
/**
 * Plugin Name:       KDNA Events
 * Plugin URI:        https://krulldna.com/
 * Description:       Events management and ticketing for WordPress, delivered entirely as Elementor widgets. Paid events via Stripe, free events supported, pluggable CRM framework.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            KDNA
 * Author URI:        https://krulldna.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kdna-events
 * Domain Path:       /languages
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'KDNA_EVENTS_VERSION', '1.0.0' );
define( 'KDNA_EVENTS_FILE', __FILE__ );
define( 'KDNA_EVENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'KDNA_EVENTS_URL', plugin_dir_url( __FILE__ ) );
define( 'KDNA_EVENTS_BASENAME', plugin_basename( __FILE__ ) );
define( 'KDNA_EVENTS_MIN_ELEMENTOR_VERSION', '3.5.0' );
define( 'KDNA_EVENTS_MIN_PHP_VERSION', '7.4' );

/**
 * Bail out and show an admin notice when Elementor is missing.
 *
 * Elementor is a hard runtime dependency for every front-end element in
 * this plugin. If the plugin was ever activated alongside Elementor but
 * Elementor is later deactivated, we deactivate ourselves too and show a
 * friendly notice, keeping the admin clean.
 *
 * @return void
 */
function kdna_events_missing_elementor_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$message = esc_html__( 'KDNA Events requires Elementor to be installed and active. The plugin has been deactivated.', 'kdna-events' );
	printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );

	if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['activate'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}

/**
 * Run once on plugin activation.
 *
 * Creates the custom tables and stores the installed DB version.
 *
 * @return void
 */
function kdna_events_activate() {
	require_once KDNA_EVENTS_PATH . 'includes/class-kdna-events-db.php';
	KDNA_Events_DB::install();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'kdna_events_activate' );

/**
 * Run once on plugin deactivation.
 *
 * We do not drop tables or delete options here. That happens in
 * uninstall.php when the user chooses to delete the plugin.
 *
 * @return void
 */
function kdna_events_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'kdna_events_deactivate' );

/**
 * Load plugin translations.
 *
 * @return void
 */
function kdna_events_load_textdomain() {
	load_plugin_textdomain( 'kdna-events', false, dirname( KDNA_EVENTS_BASENAME ) . '/languages' );
}
add_action( 'plugins_loaded', 'kdna_events_load_textdomain' );

/**
 * Bootstrap the plugin after all other plugins are loaded.
 *
 * Confirms Elementor is present, loads every class file, and wires up
 * the hook registrations. If Elementor is missing the plugin is
 * self-deactivated and an admin notice is shown.
 *
 * @return void
 */
function kdna_events_bootstrap() {
	if ( ! did_action( 'elementor/loaded' ) && ! class_exists( '\Elementor\Plugin' ) ) {
		add_action( 'admin_notices', 'kdna_events_missing_elementor_notice' );
		deactivate_plugins( KDNA_EVENTS_BASENAME );
		return;
	}

	require_once KDNA_EVENTS_PATH . 'includes/helpers.php';
	require_once KDNA_EVENTS_PATH . 'includes/class-kdna-events-db.php';
	require_once KDNA_EVENTS_PATH . 'includes/class-kdna-events-cpt.php';
	require_once KDNA_EVENTS_PATH . 'includes/class-kdna-events-admin.php';
	require_once KDNA_EVENTS_PATH . 'includes/class-kdna-events-settings.php';
	require_once KDNA_EVENTS_PATH . 'includes/class-kdna-events-templates.php';
	require_once KDNA_EVENTS_PATH . 'includes/class-kdna-events-grid.php';
	require_once KDNA_EVENTS_PATH . 'includes/class-kdna-events-checkout.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-base.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-event-title.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-event-subtitle.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-event-datetime.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-event-price.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-event-type-badge.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-event-description.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-event-image.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-event-organiser.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-event-location.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-event-grid.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-event-filter.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-event-register-button.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-checkout-summary.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-checkout-quantity.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-checkout-attendees.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-checkout-order-summary.php';
	require_once KDNA_EVENTS_PATH . 'widgets/class-widget-checkout-pay-button.php';

	KDNA_Events_CPT::init();
	KDNA_Events_Templates::init();
	KDNA_Events_Grid::init();
	KDNA_Events_Checkout::init();

	if ( is_admin() ) {
		KDNA_Events_Admin::init();
		KDNA_Events_Settings::init();
		add_action( 'admin_init', array( 'KDNA_Events_DB', 'maybe_upgrade' ) );
	}
}

/**
 * Register every concrete KDNA Events widget with Elementor.
 *
 * Hooked into kdna_events_register_widgets which fires inside the
 * file-load-registered elementor/widgets/register action.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
 * @return void
 */
function kdna_events_register_stage3_widgets( $widgets_manager ) {
	if ( ! is_object( $widgets_manager ) || ! method_exists( $widgets_manager, 'register' ) ) {
		return;
	}

	$classes = array(
		'KDNA_Events_Widget_Event_Title',
		'KDNA_Events_Widget_Event_Subtitle',
		'KDNA_Events_Widget_Event_Datetime',
		'KDNA_Events_Widget_Event_Price',
		'KDNA_Events_Widget_Event_Type_Badge',
		'KDNA_Events_Widget_Event_Description',
		'KDNA_Events_Widget_Event_Image',
		'KDNA_Events_Widget_Event_Organiser',
		'KDNA_Events_Widget_Event_Location',
		'KDNA_Events_Widget_Event_Grid',
		'KDNA_Events_Widget_Event_Filter',
		'KDNA_Events_Widget_Event_Register_Button',
		'KDNA_Events_Widget_Checkout_Summary',
		'KDNA_Events_Widget_Checkout_Quantity',
		'KDNA_Events_Widget_Checkout_Attendees',
		'KDNA_Events_Widget_Checkout_Order_Summary',
		'KDNA_Events_Widget_Checkout_Pay_Button',
	);

	foreach ( $classes as $class ) {
		if ( class_exists( $class ) ) {
			$widgets_manager->register( new $class() );
		}
	}
}
add_action( 'kdna_events_register_widgets', 'kdna_events_register_stage3_widgets' );
add_action( 'plugins_loaded', 'kdna_events_bootstrap', 15 );
