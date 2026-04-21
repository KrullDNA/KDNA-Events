<?php
/**
 * Stripe integration + webhook handling.
 *
 * Creates Stripe Checkout Sessions for paid orders, validates incoming
 * webhooks with the signing secret and fires KDNA_Events_Orders::finalise_order.
 * Also registers the Success page race-mitigation REST endpoint
 * /kdna-events/v1/confirm-order so the front end can poll for
 * 'paid' status when the buyer returns from Stripe before our webhook
 * lands.
 *
 * Webhook policy:
 *   200 on success or when the order is already finalised.
 *   400 when the signature fails to validate.
 *   500 on unexpected processing errors.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stripe checkout + webhook handler.
 */
class KDNA_Events_Stripe {

	/**
	 * Wire REST routes at rest_api_init.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	/**
	 * Whether the Stripe SDK is available.
	 *
	 * Allows the plugin to gracefully degrade when composer install
	 * has not been run yet on a fresh clone.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( '\\Stripe\\Stripe' );
	}

	/**
	 * Load the Stripe SDK from vendor/ if present.
	 *
	 * @return bool
	 */
	protected static function require_sdk() {
		if ( self::is_available() ) {
			return true;
		}
		$autoload = KDNA_EVENTS_PATH . 'vendor/autoload.php';
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		}
		return self::is_available();
	}

	/**
	 * Return the configured Stripe secret key.
	 *
	 * The plugin stores one secret key field and one publishable key
	 * field. The admin switches the values when toggling test mode,
	 * so kdna_events_stripe_test_mode acts as a presentation hint
	 * rather than a routing switch.
	 *
	 * @return string
	 */
	protected static function get_secret_key() {
		return (string) get_option( 'kdna_events_stripe_secret_key', '' );
	}

	/**
	 * Return the webhook signing secret.
	 *
	 * @return string
	 */
	protected static function get_webhook_secret() {
		return (string) get_option( 'kdna_events_stripe_webhook_secret', '' );
	}

	/**
	 * Create a Stripe Checkout Session for a pending order.
	 *
	 * Saves session_id and payment_intent on the order and returns
	 * the hosted URL.
	 *
	 * @param int $order_id Order ID.
	 * @return string|WP_Error Hosted URL or error.
	 */
	public static function create_checkout_session( $order_id ) {
		if ( ! self::require_sdk() ) {
			return new WP_Error( 'kdna_events_stripe_missing', __( 'Stripe PHP library is not installed. Run composer install.', 'kdna-events' ) );
		}

		$secret = self::get_secret_key();
		if ( '' === $secret ) {
			return new WP_Error( 'kdna_events_stripe_key', __( 'Stripe secret key is not set in Settings.', 'kdna-events' ) );
		}

		$order = KDNA_Events_Orders::get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'kdna_events_stripe_order', __( 'Order not found.', 'kdna-events' ) );
		}

		\Stripe\Stripe::setApiKey( $secret );
		\Stripe\Stripe::setAppInfo( 'KDNA Events', KDNA_EVENTS_VERSION, 'https://krulldna.com/' );

		$event_title = (string) get_the_title( (int) $order->event_id );
		if ( '' === $event_title ) {
			$event_title = __( 'Event ticket', 'kdna-events' );
		}

		// Stripe requires unit_amount in the smallest currency unit.
		// We intentionally compute per-ticket amount rather than sending
		// a subtotal so Stripe's own line-item breakdown shows quantity
		// cleanly on the hosted checkout page.
		$per_ticket_minor = (int) round( ( (float) $order->total / max( 1, (int) $order->quantity ) ) * 100 );
		if ( $per_ticket_minor < 1 ) {
			return new WP_Error( 'kdna_events_stripe_zero', __( 'Paid orders must have a non-zero price.', 'kdna-events' ) );
		}

		$success_page = kdna_events_get_page_url( 'success' );
		$checkout_page = kdna_events_get_page_url( 'checkout' );

		if ( '' === $success_page ) {
			return new WP_Error( 'kdna_events_stripe_success_missing', __( 'No Success page assigned. See Settings, Pages tab.', 'kdna-events' ) );
		}

		$success_url = add_query_arg(
			array(
				'order_ref'  => rawurlencode( $order->order_reference ),
				'session_id' => '{CHECKOUT_SESSION_ID}',
			),
			$success_page
		);
		// Stripe replaces the literal {CHECKOUT_SESSION_ID} placeholder
		// itself, so the rawurlencode above must not touch the braces.
		$success_url = str_replace( '%7BCHECKOUT_SESSION_ID%7D', '{CHECKOUT_SESSION_ID}', $success_url );

		$cancel_url = '' !== $checkout_page
			? add_query_arg(
				array(
					'event_id'  => (int) $order->event_id,
					'cancelled' => 1,
				),
				$checkout_page
			)
			: home_url();

		$session_args = array(
			'mode'        => 'payment',
			'line_items'  => array(
				array(
					'price_data' => array(
						'currency'     => strtolower( (string) $order->currency ),
						'product_data' => array(
							'name' => $event_title,
						),
						'unit_amount'  => $per_ticket_minor,
					),
					'quantity'   => (int) $order->quantity,
				),
			),
			'customer_email' => (string) $order->purchaser_email,
			'success_url'    => $success_url,
			'cancel_url'     => $cancel_url,
			'metadata'       => array(
				'order_id'        => (string) $order->order_id,
				'order_reference' => (string) $order->order_reference,
			),
		);

		try {
			$session = \Stripe\Checkout\Session::create( $session_args );
		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			return new WP_Error( 'kdna_events_stripe_api', $e->getMessage() );
		} catch ( Exception $e ) {
			return new WP_Error( 'kdna_events_stripe_exception', $e->getMessage() );
		}

		KDNA_Events_Orders::update_order(
			(int) $order->order_id,
			array(
				'stripe_session_id'     => isset( $session->id ) ? (string) $session->id : '',
				'stripe_payment_intent' => isset( $session->payment_intent ) ? (string) $session->payment_intent : '',
			)
		);

		return isset( $session->url ) ? (string) $session->url : '';
	}

	/**
	 * Register the webhook and order-confirm REST routes at file load
	 * via init action (registered in KDNA_Events_Stripe::init).
	 *
	 * @return void
	 */
	public static function register_rest_routes() {
		register_rest_route(
			'kdna-events/v1',
			'/stripe-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'kdna-events/v1',
			'/confirm-order',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_confirm_order' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ref' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Handle an incoming Stripe webhook.
	 *
	 * Validates the signature with the signing secret from settings,
	 * branches on the event type and delegates to finalise_order which
	 * is already idempotent. Returns the HTTP status codes documented
	 * in the brief.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function handle_webhook( $request ) {
		if ( ! self::require_sdk() ) {
			return new WP_REST_Response( array( 'error' => 'stripe_sdk_missing' ), 500 );
		}

		$secret = self::get_webhook_secret();
		if ( '' === $secret ) {
			return new WP_REST_Response( array( 'error' => 'webhook_secret_not_set' ), 500 );
		}

		$payload   = $request->get_body();
		$signature = $request->get_header( 'stripe-signature' );

		try {
			$event = \Stripe\Webhook::constructEvent( (string) $payload, (string) $signature, $secret );
		} catch ( \Stripe\Exception\SignatureVerificationException $e ) {
			return new WP_REST_Response( array( 'error' => 'invalid_signature' ), 400 );
		} catch ( Exception $e ) {
			return new WP_REST_Response( array( 'error' => 'invalid_payload' ), 400 );
		}

		$type = isset( $event->type ) ? (string) $event->type : '';

		try {
			switch ( $type ) {
				case 'checkout.session.completed':
					$session  = isset( $event->data->object ) ? $event->data->object : null;
					$order_id = 0;
					if ( $session ) {
						if ( isset( $session->metadata->order_id ) ) {
							$order_id = (int) $session->metadata->order_id;
						}
						if ( ! $order_id && isset( $session->id ) ) {
							$order = KDNA_Events_Orders::get_order_by_stripe_reference( (string) $session->id );
							if ( $order ) {
								$order_id = (int) $order->order_id;
							}
						}
					}
					if ( $order_id ) {
						KDNA_Events_Orders::finalise_order( $order_id );
					}
					break;

				case 'payment_intent.succeeded':
					// Defensive: some integrations only emit this event.
					$intent = isset( $event->data->object ) ? $event->data->object : null;
					if ( $intent && isset( $intent->id ) ) {
						$order = KDNA_Events_Orders::get_order_by_stripe_reference( (string) $intent->id );
						if ( $order ) {
							KDNA_Events_Orders::finalise_order( (int) $order->order_id );
						}
					}
					break;
			}
		} catch ( Exception $e ) {
			return new WP_REST_Response( array( 'error' => 'processing_error', 'message' => $e->getMessage() ), 500 );
		}

		return new WP_REST_Response( array( 'received' => true, 'type' => $type ), 200 );
	}

	/**
	 * Success page race-mitigation endpoint.
	 *
	 * The Success widget polls this up to 5 times at 500ms when the
	 * URL carries session_id, so if the webhook is still in flight the
	 * buyer sees a loading state rather than a 'pending' confirmation.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function rest_confirm_order( $request ) {
		$ref = sanitize_text_field( (string) $request->get_param( 'ref' ) );
		if ( '' === $ref ) {
			return new WP_REST_Response( array( 'status' => 'invalid_ref' ), 400 );
		}

		$order = KDNA_Events_Orders::get_order_by_reference( $ref );
		if ( ! $order ) {
			return new WP_REST_Response( array( 'status' => 'not_found' ), 404 );
		}

		$finalised = in_array( (string) $order->status, array( 'paid', 'free' ), true );

		return new WP_REST_Response(
			array(
				'status'    => (string) $order->status,
				'finalised' => $finalised,
			),
			200
		);
	}
}
