<?php
/**
 * Orders CRUD and order finalisation.
 *
 * Owns the wp_kdna_events_orders table. finalise_order() is the single
 * entry point for turning a pending row into a paid/free order with
 * real tickets. It is idempotent so Stripe webhook retries and the
 * Success page polling loop both converge on the same final state.
 *
 * Atomic ordering inside finalise_order:
 *   1. Flip the order status (paid or free) so a concurrent webhook
 *      retry sees the work is in progress and short-circuits.
 *   2. Create one ticket per attendee (each create invalidates the
 *      tickets-sold transient).
 *   3. Invalidate the tickets-sold transient again for safety.
 *   4. Send confirmation emails in a try/catch, because SMTP problems
 *      must not leave the buyer without tickets.
 *   5. Fire kdna_events_ticket_created per ticket, so CRM integrations
 *      pick up the finished row set.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order CRUD + finalise_order.
 */
class KDNA_Events_Orders {

	/**
	 * Fetch an order row by ID.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null
	 */
	public static function get_order( $order_id ) {
		global $wpdb;
		$order_id = (int) $order_id;
		if ( ! $order_id ) {
			return null;
		}
		$table = KDNA_Events_DB::orders_table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id
			)
		);
	}

	/**
	 * Fetch an order row by the public reference code.
	 *
	 * @param string $reference Order reference (KDNA-EV-YYYY-XXXXXX).
	 * @return object|null
	 */
	public static function get_order_by_reference( $reference ) {
		global $wpdb;
		$reference = (string) $reference;
		if ( '' === $reference ) {
			return null;
		}
		$table = KDNA_Events_DB::orders_table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_reference = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$reference
			)
		);
	}

	/**
	 * Fetch an order by its Stripe session or payment intent ID.
	 *
	 * Used by the defensive payment_intent.succeeded webhook path.
	 *
	 * @param string $session_id Stripe checkout.session ID or payment_intent ID.
	 * @return object|null
	 */
	public static function get_order_by_stripe_reference( $session_id ) {
		global $wpdb;
		$session_id = (string) $session_id;
		if ( '' === $session_id ) {
			return null;
		}
		$table = KDNA_Events_DB::orders_table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE stripe_session_id = %s OR stripe_payment_intent = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$session_id,
				$session_id
			)
		);
	}

	/**
	 * Update fields on an order row.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $data     Column => value pairs.
	 * @return bool
	 */
	public static function update_order( $order_id, $data ) {
		global $wpdb;
		$order_id = (int) $order_id;
		if ( ! $order_id || empty( $data ) || ! is_array( $data ) ) {
			return false;
		}
		$data['updated_at'] = current_time( 'mysql' );
		$table              = KDNA_Events_DB::orders_table();
		$result             = $wpdb->update( $table, $data, array( 'order_id' => $order_id ) );
		return false !== $result;
	}

	/**
	 * Return the attendees payload stored on the pending order.
	 *
	 * @param object $order Order row.
	 * @return array<int,array>
	 */
	public static function get_order_attendees( $order ) {
		if ( ! is_object( $order ) || empty( $order->meta ) ) {
			return array();
		}
		$decoded = json_decode( (string) $order->meta, true );
		if ( ! is_array( $decoded ) || empty( $decoded['attendees'] ) || ! is_array( $decoded['attendees'] ) ) {
			return array();
		}
		return $decoded['attendees'];
	}

	/**
	 * Finalise a pending order.
	 *
	 * Idempotent: returns true immediately when the order is already
	 * in a finalised state. See class docblock for the atomic ordering.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public static function finalise_order( $order_id ) {
		$order = self::get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		// Idempotency short-circuit. Stripe retries and success page polling
		// both hit this method; only the first call does real work.
		if ( in_array( $order->status, array( 'paid', 'free' ), true ) ) {
			return true;
		}

		$new_status = 'pending_free' === $order->status ? 'free' : 'paid';

		self::update_order(
			(int) $order->order_id,
			array(
				'status' => $new_status,
			)
		);

		$attendees = self::get_order_attendees( $order );
		$ticket_ids = array();
		foreach ( $attendees as $attendee ) {
			$ticket_id = KDNA_Events_Tickets::create_ticket(
				(int) $order->order_id,
				(int) $order->event_id,
				is_array( $attendee ) ? $attendee : array()
			);
			if ( $ticket_id ) {
				$ticket_ids[] = $ticket_id;
			}
		}

		kdna_events_invalidate_sold_count( (int) $order->event_id );

		try {
			if ( class_exists( 'KDNA_Events_Emails' ) ) {
				KDNA_Events_Emails::send_booking_confirmation( (int) $order->order_id );
				KDNA_Events_Emails::send_admin_notification( (int) $order->order_id );
			} else {
				self::send_confirmation_emails_fallback( (int) $order->order_id );
			}
		} catch ( Exception $e ) {
			// Email failures must not prevent ticket creation. Log to debug.log
			// so site owners can diagnose SMTP issues after the fact.
			if ( function_exists( 'error_log' ) ) {
				error_log( 'KDNA Events: email send failed for order ' . $order->order_reference . ' - ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		foreach ( $ticket_ids as $ticket_id ) {
			/**
			 * Fires after a ticket has been created and emails have attempted to send.
			 *
			 * Stage 10 CRM integrations listen on this to push attendee data
			 * into external systems.
			 *
			 * @param int    $ticket_id Ticket ID.
			 * @param int    $order_id  Order ID.
			 * @param int    $event_id  Event ID.
			 */
			do_action( 'kdna_events_ticket_created', (int) $ticket_id, (int) $order->order_id, (int) $order->event_id );
		}

		return true;
	}

	/**
	 * Plain-text fallback used only when KDNA_Events_Emails is missing.
	 *
	 * Stage 9 replaces the primary pathway with templated HTML emails
	 * via the emails class. This fallback keeps an end-to-end path for
	 * extraordinary cases where the emails file is unavailable at
	 * runtime (for example, a partial deployment).
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	protected static function send_confirmation_emails_fallback( $order_id ) {
		$order = self::get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$tickets     = KDNA_Events_Tickets::get_tickets_for_order( $order_id );
		$event_title = get_the_title( (int) $order->event_id );

		$from_name    = (string) get_option( 'kdna_events_email_from_name', '' );
		$from_address = (string) get_option( 'kdna_events_email_from_address', '' );
		$headers      = array();
		if ( '' !== $from_name && '' !== $from_address ) {
			$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_address );
		}

		$lines = array();
		$lines[] = sprintf( /* translators: %s: purchaser name */ __( 'Hi %s,', 'kdna-events' ), $order->purchaser_name );
		$lines[] = '';
		$lines[] = sprintf( /* translators: 1: event title, 2: order reference */ __( 'Thanks for booking %1$s. Your booking reference is %2$s.', 'kdna-events' ), $event_title, $order->order_reference );
		$lines[] = '';
		$lines[] = __( 'Your tickets:', 'kdna-events' );
		foreach ( $tickets as $ticket ) {
			$lines[] = sprintf( '%s  -  %s', $ticket->attendee_name, $ticket->ticket_code );
		}

		$body = implode( "\r\n", $lines );

		wp_mail(
			$order->purchaser_email,
			sprintf( /* translators: %s: order reference */ __( 'Your booking %s is confirmed', 'kdna-events' ), $order->order_reference ),
			$body,
			$headers
		);

		$admin_email = (string) get_option( 'kdna_events_admin_notification_email', '' );
		$notify_org  = (bool) get_option( 'kdna_events_notify_organiser', false );

		if ( '' !== $admin_email ) {
			wp_mail(
				$admin_email,
				sprintf( /* translators: %s: order reference */ __( 'New KDNA Events booking: %s', 'kdna-events' ), $order->order_reference ),
				$body,
				$headers
			);
		}

		if ( $notify_org ) {
			$organiser_email = (string) get_post_meta( (int) $order->event_id, '_kdna_event_organiser_email', true );
			if ( '' !== $organiser_email && $organiser_email !== $admin_email ) {
				wp_mail(
					$organiser_email,
					sprintf( /* translators: %s: order reference */ __( 'New KDNA Events booking: %s', 'kdna-events' ), $order->order_reference ),
					$body,
					$headers
				);
			}
		}
	}
}
