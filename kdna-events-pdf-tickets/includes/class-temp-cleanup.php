<?php
/**
 * Hourly cleanup of the add-on's temp PDF directory.
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cron sweeper for tmp PDFs.
 */
class KDNA_Events_PDF_Temp_Cleanup {

	/**
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Singleton accessor.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire up the cron action. Idempotent.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'kdna_events_pdf_cleanup_tmp', array( $this, 'sweep' ) );
	}

	/**
	 * Remove files in the temp dir older than 1 hour.
	 *
	 * @return void
	 */
	public function sweep() {
		$dir = kdna_events_pdf_tmp_dir();
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}
		$cutoff = time() - HOUR_IN_SECONDS;
		$iter   = @opendir( $dir ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		if ( ! $iter ) {
			return;
		}
		while ( false !== ( $entry = readdir( $iter ) ) ) {
			if ( '.' === $entry || '..' === $entry || 'index.php' === $entry || '.htaccess' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if ( is_file( $path ) && filemtime( $path ) < $cutoff ) {
				@unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink,Generic.PHP.NoSilencedErrors.Discouraged
			}
		}
		closedir( $iter );
	}
}
