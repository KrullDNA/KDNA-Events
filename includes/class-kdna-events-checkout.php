<?php
/**
 * Checkout AJAX handlers.
 *
 * Owns the server-side re-validation of every request before a pending
 * order is written to the database. Never trusts the client. Client
 * payloads are rebuilt and rechecked against kdna_events_is_registration_open,
 * per-event min/max, remaining capacity and per-event attendee field
 * requirements.
 *
 * Stage 7 handles only the pending-order insert and the free-event
 * success redirect. Stage 8 wires Stripe session creation and ticket
 * generation on top of the same entry point.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout AJAX entry point.
 */
class KDNA_Events_Checkout {

	/**
	 * AJAX action name.
	 */
	const AJAX_ACTION = 'kdna_events_create_order';

	/**
	 * Nonce action shared with kdnaEvents.nonce.
	 */
	const NONCE_ACTION = 'kdna_events_frontend';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_create_order' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_create_order' ) );
	}

	/**
	 * Return the parsed attendee fields config for an event.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<int,array{label:string,key:string,type:string,required:bool}>
	 */
	public static function get_attendee_fields( $event_id ) {
		$raw = get_post_meta( (int) $event_id, '_kdna_event_attendee_fields', true );
		if ( empty( $raw ) ) {
			return array();
		}
		$decoded = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		$out = array();
		foreach ( $decoded as $row ) {
			if ( ! is_array( $row ) || empty( $row['label'] ) ) {
				continue;
			}
			$out[] = array(
				'label'    => sanitize_text_field( (string) $row['label'] ),
				'key'      => isset( $row['key'] ) ? sanitize_key( (string) $row['key'] ) : sanitize_key( (string) $row['label'] ),
				'type'     => isset( $row['type'] ) ? sanitize_key( (string) $row['type'] ) : 'text',
				'required' => ! empty( $row['required'] ),
			);
		}
		return $out;
	}

	/**
	 * Calculate the effective max tickets per order for an event.
	 *
	 * Respects capacity minus sold and falls back to the plugin-wide
	 * default when the event leaves its max blank.
	 *
	 * @param int $event_id Event post ID.
	 * @return int
	 */
	public static function get_effective_max( $event_id ) {
		$event_id = (int) $event_id;
		$event_max = (int) get_post_meta( $event_id, '_kdna_event_max_tickets_per_order', true );
		if ( $event_max < 1 ) {
			$event_max = (int) get_option( 'kdna_events_default_max_per_order', 10 );
			if ( $event_max < 1 ) {
				$event_max = 10;
			}
		}

		$capacity = (int) get_post_meta( $event_id, '_kdna_event_capacity', true );
		if ( $capacity > 0 ) {
			$remaining = max( 0, $capacity - kdna_events_get_tickets_sold( $event_id ) );
			$event_max = min( $event_max, $remaining );
		}
		return max( 0, (int) $event_max );
	}

	/**
	 * Calculate the effective min tickets per order for an event.
	 *
	 * @param int $event_id Event post ID.
	 * @return int
	 */
	public static function get_effective_min( $event_id ) {
		$event_min = (int) get_post_meta( (int) $event_id, '_kdna_event_min_tickets_per_order', true );
		return $event_min < 1 ? 1 : $event_min;
	}

	/**
	 * AJAX: create a pending order.
	 *
	 * Full revalidation flow; responses follow the shape documented in
	 * the project brief:
	 *   { success, order_ref, is_free, stripe_session_url }
	 *
	 * On validation failure, returns a WP_Error-shaped JSON body.
	 *
	 * @return void
	 */
	public static function ajax_create_order() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
		$quantity = isset( $_POST['quantity'] ) ? absint( wp_unslash( $_POST['quantity'] ) ) : 0;

		if ( ! $event_id || 'kdna_event' !== get_post_type( $event_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid event.', 'kdna-events' ) ), 400 );
		}

		if ( ! kdna_events_is_registration_open( $event_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Registration is not open for this event.', 'kdna-events' ) ), 400 );
		}

		$min = self::get_effective_min( $event_id );
		$max = self::get_effective_max( $event_id );

		if ( 0 === $max ) {
			wp_send_json_error( array( 'message' => __( 'This event is sold out.', 'kdna-events' ) ), 400 );
		}

		if ( $quantity < $min || $quantity > $max ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: 1: minimum, 2: maximum */
						__( 'Please choose a quantity between %1$d and %2$d.', 'kdna-events' ),
						$min,
						$max
					),
				),
				400
			);
		}

		$attendees_raw = isset( $_POST['attendees'] ) ? wp_unslash( $_POST['attendees'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $attendees_raw ) ) {
			$decoded       = json_decode( (string) $attendees_raw, true );
			$attendees_raw = is_array( $decoded ) ? $decoded : array();
		}

		$phone_required_global = false;
		if ( isset( $_POST['phone_required'] ) ) {
			$phone_required_global = in_array( (string) wp_unslash( $_POST['phone_required'] ), array( '1', 'yes', 'true' ), true );
		}

		$attendee_fields = self::get_attendee_fields( $event_id );
		$attendees_clean = array();
		$errors          = array();

		for ( $i = 0; $i < $quantity; $i++ ) {
			$row = isset( $attendees_raw[ $i ] ) && is_array( $attendees_raw[ $i ] ) ? $attendees_raw[ $i ] : array();

			$name  = isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '';
			$email = isset( $row['email'] ) ? sanitize_email( (string) $row['email'] ) : '';
			$phone = isset( $row['phone'] ) ? sanitize_text_field( (string) $row['phone'] ) : '';

			if ( '' === $name ) {
				$errors[] = sprintf( /* translators: %d: ticket index */ __( 'Attendee %d: name is required.', 'kdna-events' ), $i + 1 );
			}
			if ( '' === $email || ! is_email( $email ) ) {
				$errors[] = sprintf( /* translators: %d: ticket index */ __( 'Attendee %d: a valid email is required.', 'kdna-events' ), $i + 1 );
			}
			if ( $phone_required_global && '' === $phone ) {
				$errors[] = sprintf( /* translators: %d: ticket index */ __( 'Attendee %d: phone is required.', 'kdna-events' ), $i + 1 );
			}

			$custom_clean = array();
			foreach ( $attendee_fields as $field ) {
				$raw_value = isset( $row['custom'][ $field['key'] ] ) ? (string) $row['custom'][ $field['key'] ] : '';
				$value     = 'email' === $field['type'] ? sanitize_email( $raw_value ) : sanitize_text_field( $raw_value );
				if ( $field['required'] && '' === $value ) {
					$errors[] = sprintf(
						/* translators: 1: ticket index, 2: field label */
						__( 'Attendee %1$d: %2$s is required.', 'kdna-events' ),
						$i + 1,
						$field['label']
					);
				}
				$custom_clean[ $field['key'] ] = $value;
			}

			$attendees_clean[] = array(
				'name'   => $name,
				'email'  => $email,
				'phone'  => $phone,
				'custom' => $custom_clean,
			);
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please fix the highlighted fields.', 'kdna-events' ),
					'errors'  => $errors,
				),
				400
			);
		}

		$purchaser         = $attendees_clean[0];
		$purchaser_name    = isset( $_POST['purchaser_name'] ) ? sanitize_text_field( wp_unslash( $_POST['purchaser_name'] ) ) : $purchaser['name'];
		$purchaser_email   = isset( $_POST['purchaser_email'] ) ? sanitize_email( wp_unslash( $_POST['purchaser_email'] ) ) : $purchaser['email'];
		$purchaser_phone   = isset( $_POST['purchaser_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['purchaser_phone'] ) ) : $purchaser['phone'];

		$price    = (float) get_post_meta( $event_id, '_kdna_event_price', true );
		$currency = strtoupper( (string) get_post_meta( $event_id, '_kdna_event_currency', true ) );
		if ( '' === $currency ) {
			$currency = strtoupper( (string) get_option( 'kdna_events_default_currency', 'AUD' ) );
		}
		$is_free  = kdna_events_is_free( $event_id );
		$subtotal = $is_free ? 0.0 : (float) ( $price * $quantity );
		$total    = $subtotal;

		$reference = kdna_events_generate_order_reference();
		$now       = current_time( 'mysql' );
		$status    = $is_free ? 'pending_free' : 'pending';

		$order_meta = wp_json_encode(
			array(
				'attendees' => $attendees_clean,
			)
		);

		global $wpdb;
		$table = KDNA_Events_DB::orders_table();

		$insert = $wpdb->insert(
			$table,
			array(
				'order_reference'  => $reference,
				'event_id'         => $event_id,
				'user_id'          => get_current_user_id() ?: null,
				'purchaser_name'   => $purchaser_name,
				'purchaser_email'  => $purchaser_email,
				'purchaser_phone'  => $purchaser_phone,
				'quantity'         => $quantity,
				'subtotal'         => $subtotal,
				'total'            => $total,
				'currency'         => $currency,
				'status'           => $status,
				'meta'             => $order_meta,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $insert ) {
			wp_send_json_error( array( 'message' => __( 'Could not create the order. Please try again.', 'kdna-events' ) ), 500 );
		}

		$order_id = (int) $wpdb->insert_id;

		/**
		 * Fires after a pending order has been inserted.
		 *
		 * Stage 8 hooks this to create tickets, send emails and finalise
		 * Stripe sessions. In Stage 7 there are no listeners.
		 *
		 * @param int   $order_id Inserted order ID.
		 * @param array $context  Order details for convenience.
		 */
		do_action(
			'kdna_events_pending_order_created',
			$order_id,
			array(
				'order_reference' => $reference,
				'event_id'        => $event_id,
				'is_free'         => $is_free,
				'quantity'        => $quantity,
				'total'           => $total,
				'attendees'       => $attendees_clean,
			)
		);

		if ( $is_free ) {
			$success_url = kdna_events_get_page_url( 'success' );
			$redirect    = '' === $success_url ? '' : add_query_arg( 'order_ref', rawurlencode( $reference ), $success_url );

			wp_send_json_success(
				array(
					'success'             => true,
					'order_ref'           => $reference,
					'is_free'             => true,
					'stripe_session_url'  => '',
					'redirect_url'        => $redirect,
				)
			);
		}

		// Stage 7 stub: Stripe integration ships in Stage 8.
		wp_send_json_error(
			array(
				'message'   => __( 'Stripe checkout is not yet implemented. Free events work end-to-end today.', 'kdna-events' ),
				'order_ref' => $reference,
				'is_free'   => false,
			),
			501
		);
	}
}
