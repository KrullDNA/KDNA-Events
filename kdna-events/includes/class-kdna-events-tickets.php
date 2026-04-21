<?php
/**
 * Tickets CRUD.
 *
 * Owns the wp_kdna_events_tickets table. Every status-changing
 * operation invalidates the kdna_events_sold_{event_id} transient so
 * the calculated tickets-sold count stays in sync for capacity checks.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ticket CRUD helpers.
 */
class KDNA_Events_Tickets {

	/**
	 * Insert a single ticket row.
	 *
	 * @param int   $order_id      Owning order.
	 * @param int   $event_id      Event post ID.
	 * @param array $attendee_data Attendee payload: name, email, phone, custom.
	 * @return int  Inserted ticket ID, 0 on failure.
	 */
	public static function create_ticket( $order_id, $event_id, $attendee_data ) {
		global $wpdb;

		$order_id = (int) $order_id;
		$event_id = (int) $event_id;
		if ( ! $order_id || ! $event_id ) {
			return 0;
		}

		$table = KDNA_Events_DB::tickets_table();
		$code  = kdna_events_generate_ticket_code();
		$now   = current_time( 'mysql' );

		$name   = isset( $attendee_data['name'] ) ? sanitize_text_field( (string) $attendee_data['name'] ) : '';
		$email  = isset( $attendee_data['email'] ) ? sanitize_email( (string) $attendee_data['email'] ) : '';
		$phone  = isset( $attendee_data['phone'] ) ? sanitize_text_field( (string) $attendee_data['phone'] ) : '';
		$custom = isset( $attendee_data['custom'] ) && is_array( $attendee_data['custom'] ) ? $attendee_data['custom'] : array();

		$inserted = $wpdb->insert(
			$table,
			array(
				'ticket_code'     => $code,
				'order_id'        => $order_id,
				'event_id'        => $event_id,
				'attendee_name'   => $name,
				'attendee_email'  => $email,
				'attendee_phone'  => $phone,
				'attendee_fields' => wp_json_encode( $custom ),
				'status'          => 'valid',
				'created_at'      => $now,
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		$ticket_id = (int) $wpdb->insert_id;
		kdna_events_invalidate_sold_count( $event_id );
		return $ticket_id;
	}

	/**
	 * Return every ticket row attached to an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array<int,object>
	 */
	public static function get_tickets_for_order( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;
		if ( ! $order_id ) {
			return array();
		}

		$table = KDNA_Events_DB::tickets_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d ORDER BY ticket_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Return every ticket row for an event, filtered by status.
	 *
	 * @param int         $event_id Event ID.
	 * @param string|null $status   Optional status filter. Null returns all.
	 * @return array<int,object>
	 */
	public static function get_tickets_for_event( $event_id, $status = null ) {
		global $wpdb;

		$event_id = (int) $event_id;
		if ( ! $event_id ) {
			return array();
		}

		$table = KDNA_Events_DB::tickets_table();
		if ( null === $status ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE event_id = %d ORDER BY ticket_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$event_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE event_id = %d AND status = %s ORDER BY ticket_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$event_id,
					(string) $status
				)
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Fetch a single ticket by its public code.
	 *
	 * @param string $code Ticket code.
	 * @return object|null
	 */
	public static function get_ticket_by_code( $code ) {
		global $wpdb;

		$code = (string) $code;
		if ( '' === $code ) {
			return null;
		}

		$table = KDNA_Events_DB::tickets_table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ticket_code = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$code
			)
		);
	}

	/**
	 * Update a ticket's status and invalidate the sold-count cache.
	 *
	 * @param int    $ticket_id Ticket ID.
	 * @param string $status    New status: valid, checked_in, cancelled.
	 * @return bool
	 */
	public static function update_status( $ticket_id, $status ) {
		global $wpdb;

		$ticket_id = (int) $ticket_id;
		if ( ! $ticket_id ) {
			return false;
		}

		$allowed = array( 'valid', 'checked_in', 'cancelled' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		$table  = KDNA_Events_DB::tickets_table();
		$ticket = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT event_id FROM {$table} WHERE ticket_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$ticket_id
			)
		);
		if ( ! $ticket ) {
			return false;
		}

		$extra = array();
		$fmt   = array();
		if ( 'checked_in' === $status ) {
			$extra['checked_in_at'] = current_time( 'mysql' );
			$fmt[]                  = '%s';
		}

		$data    = array_merge( array( 'status' => $status ), $extra );
		$formats = array_merge( array( '%s' ), $fmt );

		$result = $wpdb->update( $table, $data, array( 'ticket_id' => $ticket_id ), $formats, array( '%d' ) );
		if ( false === $result ) {
			return false;
		}

		kdna_events_invalidate_sold_count( (int) $ticket->event_id );
		return true;
	}

	/**
	 * Cancel a ticket. Wrapper over update_status.
	 *
	 * @param int $ticket_id Ticket ID.
	 * @return bool
	 */
	public static function cancel_ticket( $ticket_id ) {
		return self::update_status( $ticket_id, 'cancelled' );
	}
}
