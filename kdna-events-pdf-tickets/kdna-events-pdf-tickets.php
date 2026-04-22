<?php
/**
 * Plugin Name:       KDNA Events PDF Tickets
 * Plugin URI:        https://krulldna.com/
 * Description:       Adds branded PDF ticket attachments with Code 128 barcodes to the KDNA Events plugin.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            KDNA
 * Author URI:        https://krulldna.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kdna-events-pdf-tickets
 * Domain Path:       /languages
 * Requires Plugins:  kdna-events
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KDNA_EVENTS_PDF_VERSION', '1.0.0' );
define( 'KDNA_EVENTS_PDF_PLUGIN_FILE', __FILE__ );
define( 'KDNA_EVENTS_PDF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KDNA_EVENTS_PDF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KDNA_EVENTS_PDF_MIN_CORE', '1.1.0' );

/**
 * Load the Composer autoloader, guarded against conflicts with any
 * other plugin that may already have loaded Dompdf.
 */
function kdna_events_pdf_autoload() {
	$autoload = KDNA_EVENTS_PDF_PLUGIN_DIR . 'vendor/autoload.php';
	if ( file_exists( $autoload ) && ! class_exists( '\\Dompdf\\Dompdf' ) ) {
		require_once $autoload;
	} elseif ( file_exists( $autoload ) ) {
		// Core or another plugin already loaded Dompdf. Still pull in
		// the barcode generator if it is not yet loaded.
		if ( ! class_exists( '\\Picqer\\Barcode\\BarcodeGenerator' ) ) {
			require_once $autoload;
		}
	}
}

/**
 * Admin notice when core KDNA Events is not active.
 *
 * @return void
 */
function kdna_events_pdf_missing_core_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$install_url = add_query_arg(
		array( 's' => 'kdna-events', 'tab' => 'search', 'type' => 'term' ),
		self_admin_url( 'plugin-install.php' )
	);
	printf(
		'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
		esc_html__( 'KDNA Events PDF Tickets:', 'kdna-events-pdf-tickets' ),
		esc_html__( 'The core KDNA Events plugin is not active. This add-on cannot run without it.', 'kdna-events-pdf-tickets' ),
		esc_url( $install_url ),
		esc_html__( 'Install KDNA Events', 'kdna-events-pdf-tickets' )
	);
}

/**
 * Admin notice when core KDNA Events is present but outdated.
 *
 * @return void
 */
function kdna_events_pdf_outdated_core_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$update_url = self_admin_url( 'plugins.php' );
	printf(
		'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
		esc_html__( 'KDNA Events PDF Tickets:', 'kdna-events-pdf-tickets' ),
		sprintf(
			/* translators: %s: minimum required core version */
			esc_html__( 'Requires the core KDNA Events plugin version %s or higher. Please update core first.', 'kdna-events-pdf-tickets' ),
			esc_html( KDNA_EVENTS_PDF_MIN_CORE )
		),
		esc_url( $update_url ),
		esc_html__( 'Open Plugins screen', 'kdna-events-pdf-tickets' )
	);
}

/**
 * Late boot: verify core is active and current, then load the main
 * plugin class. Uses priority 5 so the add-on hooks are in place
 * before core finishes booting at priority 15.
 *
 * @return void
 */
function kdna_events_pdf_bootstrap() {
	if ( ! defined( 'KDNA_EVENTS_VERSION' ) ) {
		add_action( 'admin_notices', 'kdna_events_pdf_missing_core_notice' );
		return;
	}
	if ( version_compare( KDNA_EVENTS_VERSION, KDNA_EVENTS_PDF_MIN_CORE, '<' ) ) {
		add_action( 'admin_notices', 'kdna_events_pdf_outdated_core_notice' );
		return;
	}

	kdna_events_pdf_autoload();

	require_once KDNA_EVENTS_PDF_PLUGIN_DIR . 'includes/helpers.php';
	require_once KDNA_EVENTS_PDF_PLUGIN_DIR . 'includes/class-barcode.php';
	require_once KDNA_EVENTS_PDF_PLUGIN_DIR . 'includes/class-pdf-generator.php';
	require_once KDNA_EVENTS_PDF_PLUGIN_DIR . 'includes/class-settings.php';
	require_once KDNA_EVENTS_PDF_PLUGIN_DIR . 'includes/class-email-integration.php';
	require_once KDNA_EVENTS_PDF_PLUGIN_DIR . 'includes/class-widget-integration.php';
	require_once KDNA_EVENTS_PDF_PLUGIN_DIR . 'includes/class-rest-endpoint.php';
	require_once KDNA_EVENTS_PDF_PLUGIN_DIR . 'includes/class-temp-cleanup.php';
	require_once KDNA_EVENTS_PDF_PLUGIN_DIR . 'includes/class-plugin.php';

	KDNA_Events_PDF_Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'kdna_events_pdf_bootstrap', 5 );

/**
 * Activation handler. Creates the temp directory, schedules cron,
 * and sets defaults. Must not blow up even if core is missing,
 * because the plugin can be activated before core is.
 *
 * @return void
 */
function kdna_events_pdf_activate() {
	$upload = wp_upload_dir();
	if ( empty( $upload['error'] ) ) {
		$dir = trailingslashit( $upload['basedir'] ) . 'kdna-events-pdf-tickets-tmp';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		@file_put_contents( $dir . '/.htaccess', "Order deny,allow\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,Generic.PHP.NoSilencedErrors.Discouraged
		@file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,Generic.PHP.NoSilencedErrors.Discouraged
	}
	if ( ! wp_next_scheduled( 'kdna_events_pdf_cleanup_tmp' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'kdna_events_pdf_cleanup_tmp' );
	}
}
register_activation_hook( __FILE__, 'kdna_events_pdf_activate' );

/**
 * Deactivation handler. Clears the cleanup cron; leaves temp files
 * in place so an accidental toggle does not destroy any in-flight
 * downloads.
 *
 * @return void
 */
function kdna_events_pdf_deactivate() {
	$ts = wp_next_scheduled( 'kdna_events_pdf_cleanup_tmp' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'kdna_events_pdf_cleanup_tmp' );
	}
	wp_clear_scheduled_hook( 'kdna_events_pdf_cleanup_tmp' );
}
register_deactivation_hook( __FILE__, 'kdna_events_pdf_deactivate' );

/**
 * Load translations.
 *
 * @return void
 */
function kdna_events_pdf_load_textdomain() {
	load_plugin_textdomain( 'kdna-events-pdf-tickets', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'kdna_events_pdf_load_textdomain' );
