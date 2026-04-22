<?php
/**
 * Database schema and migration for KDNA Events tax invoices.
 *
 * Single table wp_kdna_events_invoices, one row per paid order with
 * immutable snapshots of the tax rate, tax label and business
 * identity at time of issue. Regenerating the PDF always re-reads
 * the snapshot so historical invoices stay ATO-compliant even after
 * the admin edits business or tax settings later.
 *
 * @package KDNA_Events
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Invoices table installer.
 */
class KDNA_Events_Invoices_DB {

	/**
	 * Schema version. Bump when the column layout changes so dbDelta
	 * applies the delta on the next admin load via maybe_upgrade.
	 */
	const DB_VERSION = '1.2.0';

	/**
	 * Option key where the installed invoices schema version lives.
	 */
	const DB_VERSION_OPTION = 'kdna_events_invoices_db_version';

	/**
	 * Fully prefixed invoices table name.
	 *
	 * @return string
	 */
	public static function invoices_table() {
		global $wpdb;
		return $wpdb->prefix . 'kdna_events_invoices';
	}

	/**
	 * Run dbDelta to create or upgrade the invoices table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::invoices_table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			invoice_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			invoice_number varchar(64) NOT NULL,
			sequence_number bigint(20) unsigned NOT NULL,
			order_id bigint(20) unsigned NOT NULL,
			issued_at datetime NOT NULL,
			tax_rate_applied decimal(5,2) NOT NULL DEFAULT 0,
			tax_label_applied varchar(32) NOT NULL DEFAULT '',
			subtotal_ex_tax decimal(12,2) NOT NULL DEFAULT 0,
			tax_amount decimal(12,2) NOT NULL DEFAULT 0,
			total_inc_tax decimal(12,2) NOT NULL DEFAULT 0,
			currency char(3) NOT NULL DEFAULT '',
			business_snapshot longtext NULL,
			status varchar(32) NOT NULL DEFAULT 'issued',
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (invoice_id),
			UNIQUE KEY invoice_number (invoice_number),
			UNIQUE KEY sequence_number (sequence_number),
			UNIQUE KEY order_id (order_id),
			KEY status (status),
			KEY issued_at (issued_at)
		) {$charset};";

		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Admin-load guard that re-runs install when the stored version is
	 * behind the class constant.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = (string) get_option( self::DB_VERSION_OPTION, '' );
		if ( $installed !== self::DB_VERSION ) {
			self::install();
		}
	}
}
