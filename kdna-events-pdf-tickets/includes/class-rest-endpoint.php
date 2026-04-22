<?php
/**
 * REST endpoint for authenticated PDF ticket downloads.
 *
 * Namespace kdna-events-pdf/v1 (separate from core's kdna-events/v1).
 * Auth: the logged-in user owns the ticket (user_id or purchaser
 * email match) OR the request carries a valid signed token (?t=...).
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ticket download REST controller.
 */
class KDNA_Events_PDF_REST {

	const NAMESPACE_SLUG = 'kdna-events-pdf/v1';

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
	 * Wire up the REST route.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the download route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_SLUG,
			'/ticket/(?P<ticket_code>[A-Za-z0-9]+)\.pdf',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'download' ),
				'args'                => array(
					'ticket_code' => array( 'type' => 'string' ),
					't'           => array( 'type' => 'string' ),
				),
			)
		);
	}

	/**
	 * REST callback: stream the ticket PDF to an authorised caller.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function download( $request ) {
		$code  = sanitize_text_field( (string) $request->get_param( 'ticket_code' ) );
		$token = sanitize_text_field( (string) $request->get_param( 't' ) );

		$ticket = $this->fetch_ticket_by_code( $code );
		if ( ! $ticket ) {
			return new WP_Error( 'kdna_events_pdf_not_found', __( 'Ticket not found.', 'kdna-events-pdf-tickets' ), array( 'status' => 404 ) );
		}

		if ( ! $this->can_download( $ticket, $token ) ) {
			return new WP_Error( 'kdna_events_pdf_forbidden', __( 'Access denied.', 'kdna-events-pdf-tickets' ), array( 'status' => 403 ) );
		}

		$generator = new KDNA_Events_PDF_Generator();
		$mode      = (string) get_option( 'kdna_events_pdf_tickets_per_order', 'combined' );

		if ( 'combined' === $mode ) {
			$pdf = $generator->generate_order_tickets( (int) $ticket->order_id );
		} else {
			$pdf = $generator->generate_ticket( (int) $ticket->ticket_id );
		}

		if ( '' === $pdf ) {
			return new WP_Error( 'kdna_events_pdf_failed', __( 'PDF render failed.', 'kdna-events-pdf-tickets' ), array( 'status' => 500 ) );
		}

		$filename = 'ticket-' . sanitize_title( $ticket->ticket_code ) . '.pdf';
		if ( ! headers_sent() ) {
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Content-Length: ' . strlen( $pdf ) );
			header( 'Cache-Control: private, max-age=0, must-revalidate' );
		}
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Auth decision: owner, token or admin.
	 *
	 * @param object $ticket Ticket row.
	 * @param string $token  Optional signed token.
	 * @return bool
	 */
	protected function can_download( $ticket, $token ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( '' !== $token && kdna_events_pdf_verify_token( (string) $ticket->ticket_code, $token ) ) {
			return true;
		}
		if ( ! class_exists( 'KDNA_Events_Orders' ) ) {
			return false;
		}
		$order = KDNA_Events_Orders::get_order( (int) $ticket->order_id );
		if ( ! $order ) {
			return false;
		}
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return false;
		}
		if ( (int) $user->ID === (int) ( $order->user_id ?? 0 ) ) {
			return true;
		}
		if ( strtolower( (string) $user->user_email ) === strtolower( (string) ( $order->purchaser_email ?? '' ) ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Look up a ticket by its code.
	 *
	 * @param string $code Ticket code.
	 * @return object|null
	 */
	protected function fetch_ticket_by_code( $code ) {
		global $wpdb;
		if ( ! class_exists( 'KDNA_Events_DB' ) ) {
			return null;
		}
		$table = KDNA_Events_DB::tickets_table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ticket_code = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(string) $code
			)
		);
	}
}
