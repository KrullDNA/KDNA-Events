<?php
/**
 * KDNA Events uninstall routine.
 *
 * Removes every plugin option and drops the custom tables. Runs only
 * when the user chooses to delete the plugin from the WordPress admin.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$option_keys = array(
	'kdna_events_db_version',
	'kdna_events_stripe_publishable_key',
	'kdna_events_stripe_secret_key',
	'kdna_events_stripe_webhook_secret',
	'kdna_events_stripe_test_mode',
	'kdna_events_default_currency',
	'kdna_events_default_max_per_order',
	'kdna_events_google_maps_api_key',
	'kdna_events_template_checkout',
	'kdna_events_template_success',
	'kdna_events_template_my_tickets',
	'kdna_events_email_from_name',
	'kdna_events_email_from_address',
	'kdna_events_admin_notification_email',
	'kdna_events_notify_organiser',
	'kdna_events_booking_email_body',
);

foreach ( $option_keys as $option_key ) {
	delete_option( $option_key );
	delete_site_option( $option_key );
}

// Drop any cached tickets-sold transients.
$transient_rows = $wpdb->get_col(
	"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_kdna\_events\_sold\_%' OR option_name LIKE '\_transient\_timeout\_kdna\_events\_sold\_%'"
);
if ( is_array( $transient_rows ) ) {
	foreach ( $transient_rows as $row ) {
		delete_option( $row );
	}
}

// Drop custom tables.
$orders_table  = $wpdb->prefix . 'kdna_events_orders';
$tickets_table = $wpdb->prefix . 'kdna_events_tickets';

$wpdb->query( "DROP TABLE IF EXISTS {$tickets_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$orders_table}" );  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
