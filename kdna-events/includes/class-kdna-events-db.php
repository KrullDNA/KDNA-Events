<?php
/**
 * Database schema and migration handling for KDNA Events.
 *
 * Manages custom tables for orders and tickets, exposes static getters for
 * their fully prefixed names, and bumps the stored db version on schema
 * changes so future migrations can run cleanly.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles creation and versioning of the custom tables.
 */
class KDNA_Events_DB {

	/**
	 * Current database schema version.
	 *
	 * Bump when the schema changes so dbDelta runs on the next load.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Option key where the installed db version is stored.
	 */
	const DB_VERSION_OPTION = 'kdna_events_db_version';

	/**
	 * Return the fully prefixed orders table name.
	 *
	 * @return string
	 */
	public static function orders_table() {
		global $wpdb;
		return $wpdb->prefix . 'kdna_events_orders';
	}

	/**
	 * Return the fully prefixed tickets table name.
	 *
	 * @return string
	 */
	public static function tickets_table() {
		global $wpdb;
		return $wpdb->prefix . 'kdna_events_tickets';
	}

	/**
	 * Install or upgrade custom tables.
	 *
	 * Runs on plugin activation and on admin load when the stored version
	 * is behind the class constant.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$orders_table    = self::orders_table();
		$tickets_table   = self::tickets_table();

		$orders_sql = "CREATE TABLE {$orders_table} (
			order_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_reference varchar(32) NOT NULL,
			event_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			purchaser_name varchar(255) NOT NULL DEFAULT '',
			purchaser_email varchar(255) NOT NULL DEFAULT '',
			purchaser_phone varchar(64) NOT NULL DEFAULT '',
			quantity int(10) unsigned NOT NULL DEFAULT 0,
			subtotal decimal(12,2) NOT NULL DEFAULT 0.00,
			total decimal(12,2) NOT NULL DEFAULT 0.00,
			currency char(3) NOT NULL DEFAULT 'AUD',
			stripe_session_id varchar(255) DEFAULT NULL,
			stripe_payment_intent varchar(255) DEFAULT NULL,
			status varchar(32) NOT NULL DEFAULT 'pending',
			meta longtext NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (order_id),
			UNIQUE KEY order_reference (order_reference),
			KEY event_id (event_id),
			KEY user_id (user_id),
			KEY purchaser_email (purchaser_email),
			KEY status (status)
		) {$charset_collate};";

		$tickets_sql = "CREATE TABLE {$tickets_table} (
			ticket_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ticket_code varchar(32) NOT NULL,
			order_id bigint(20) unsigned NOT NULL,
			event_id bigint(20) unsigned NOT NULL,
			attendee_name varchar(255) NOT NULL DEFAULT '',
			attendee_email varchar(255) NOT NULL DEFAULT '',
			attendee_phone varchar(64) NOT NULL DEFAULT '',
			attendee_fields longtext NULL,
			status varchar(32) NOT NULL DEFAULT 'valid',
			checked_in_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (ticket_id),
			UNIQUE KEY ticket_code (ticket_code),
			KEY order_id (order_id),
			KEY event_id (event_id),
			KEY attendee_email (attendee_email),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $orders_sql );
		dbDelta( $tickets_sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Run install when the installed version is stale.
	 *
	 * Hooked on admin_init so upgrades pick up automatically after a plugin
	 * update without requiring a manual reactivation.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = (string) get_option( self::DB_VERSION_OPTION, '' );

		if ( self::DB_VERSION !== $installed ) {
			self::install();
		}
	}
}
