<?php
/**
 * Uninstall routine for the KDNA Events PDF Tickets add-on.
 *
 * Fires only when the user clicks Delete on the plugin row. Removes
 * every add-on option and the temp directory + its contents. Never
 * touches core plugin data.
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete every option that starts with kdna_events_pdf_.
$rows = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->esc_like( 'kdna_events_pdf_' ) . '%'
	)
);
if ( is_array( $rows ) ) {
	foreach ( $rows as $name ) {
		delete_option( $name );
	}
}

// Remove the temp directory and its contents.
$upload = wp_upload_dir();
if ( empty( $upload['error'] ) ) {
	$dir = trailingslashit( $upload['basedir'] ) . 'kdna-events-pdf-tickets-tmp';
	if ( is_dir( $dir ) ) {
		$it = @opendir( $dir ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		if ( $it ) {
			while ( false !== ( $entry = readdir( $it ) ) ) {
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}
				@unlink( $dir . '/' . $entry ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink,Generic.PHP.NoSilencedErrors.Discouraged
			}
			closedir( $it );
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir,Generic.PHP.NoSilencedErrors.Discouraged
	}
}

// Clear any scheduled crons this add-on registered.
wp_clear_scheduled_hook( 'kdna_events_pdf_cleanup_tmp' );
wp_clear_scheduled_hook( 'kdna_events_pdf_unlink_tmp' );
