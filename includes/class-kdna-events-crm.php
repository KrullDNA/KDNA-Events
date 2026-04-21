<?php
/**
 * KDNA Events CRM integration framework.
 *
 * Pluggable framework for pushing ticket data to external CRMs. Ships
 * with no concrete adapter: a HubSpot, Mailchimp or ActiveCampaign
 * integration is built as a separate add-on plugin that registers an
 * instance of KDNA_Events_CRM_Integration.
 *
 * ## Building an integration
 *
 * Extend the abstract and register on kdna_events_register_crm_integrations:
 *
 *     class My_HubSpot_Integration extends KDNA_Events_CRM_Integration {
 *
 *         public function get_id() {
 *             return 'hubspot';
 *         }
 *
 *         public function get_name() {
 *             return 'HubSpot';
 *         }
 *
 *         public function get_description() {
 *             return 'Sync attendees to HubSpot as contacts in a specific list.';
 *         }
 *
 *         public function get_settings_fields() {
 *             return array(
 *                 array( 'key' => 'api_key', 'label' => 'Private app token', 'type' => 'password' ),
 *                 array( 'key' => 'list_id', 'label' => 'Target list ID',    'type' => 'text' ),
 *             );
 *         }
 *
 *         public function test_connection() {
 *             $key = $this->get_setting( 'api_key' );
 *             if ( ! $key ) {
 *                 return new WP_Error( 'missing_key', 'Token required.' );
 *             }
 *             // Hit a lightweight HubSpot endpoint here...
 *             return true;
 *         }
 *
 *         public function sync_ticket( array $payload ) {
 *             // POST to HubSpot. Return true on success or WP_Error on failure.
 *             return true;
 *         }
 *     }
 *
 *     add_action( 'kdna_events_register_crm_integrations', function ( $registry ) {
 *         $registry->register( new My_HubSpot_Integration() );
 *     } );
 *
 * ## Hooks
 *
 *   - kdna_events_register_crm_integrations : action, passed the registry.
 *   - kdna_events_ticket_created             : listened to internally; fires per ticket.
 *   - kdna_events_crm_sync_data              : filter, modify the payload before sync.
 *   - kdna_events_crm_integrations           : filter, modify the registered list.
 *   - kdna_events_after_crm_sync             : action, fires after each sync call.
 *
 * ## Options (Settings → CRM)
 *
 *   - kdna_events_crm_master_enabled  boolean   master pause switch.
 *   - kdna_events_crm_enabled         assoc     { integration_id => '1' } when enabled.
 *   - kdna_events_crm_settings        nested    { integration_id => { key => value } }.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract every CRM integration implements.
 */
abstract class KDNA_Events_CRM_Integration {

	/**
	 * Machine identifier for the integration. Lowercase, a-z0-9-_.
	 *
	 * @return string
	 */
	abstract public function get_id();

	/**
	 * Human readable integration name.
	 *
	 * @return string
	 */
	abstract public function get_name();

	/**
	 * Short description shown on the Settings, CRM tab.
	 *
	 * @return string
	 */
	abstract public function get_description();

	/**
	 * Return settings fields accepted by the integration.
	 *
	 * Each row is an associative array:
	 *   key         string required, option key stored per integration
	 *   label       string visible label
	 *   type        string text, password, email, url, textarea, select, checkbox
	 *   description string optional helper text
	 *   options     array  required for select, { value => label }
	 *   default     mixed  optional default value
	 *
	 * @return array<int,array<string,mixed>>
	 */
	abstract public function get_settings_fields();

	/**
	 * Test connectivity and credentials. Returns true on success, WP_Error on failure.
	 *
	 * @return true|WP_Error
	 */
	abstract public function test_connection();

	/**
	 * Push a ticket payload to the remote CRM.
	 *
	 * Payload shape is documented in Section 7 of the project brief and
	 * is built by KDNA_Events_CRM_Bridge::build_payload. Return true on
	 * success or WP_Error on failure; exceptions are caught upstream so
	 * one broken integration cannot take the others down.
	 *
	 * @param array $payload Ticket payload.
	 * @return true|WP_Error
	 */
	abstract public function sync_ticket( array $payload );

	/**
	 * Read a single stored setting for this integration.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Optional default.
	 * @return mixed
	 */
	public function get_setting( $key, $default = '' ) {
		$all  = (array) get_option( 'kdna_events_crm_settings', array() );
		$mine = isset( $all[ $this->get_id() ] ) && is_array( $all[ $this->get_id() ] ) ? $all[ $this->get_id() ] : array();
		if ( array_key_exists( $key, $mine ) ) {
			return $mine[ $key ];
		}
		return $default;
	}

	/**
	 * Whether this integration is enabled in Settings.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$enabled = (array) get_option( 'kdna_events_crm_enabled', array() );
		return ! empty( $enabled[ $this->get_id() ] );
	}
}

/**
 * Registry of CRM integrations.
 */
final class KDNA_Events_CRM_Registry {

	/**
	 * Registered integrations keyed by id.
	 *
	 * @var array<string,KDNA_Events_CRM_Integration>
	 */
	protected $integrations = array();

	/**
	 * Register an integration instance.
	 *
	 * @param KDNA_Events_CRM_Integration $integration Integration.
	 * @return void
	 */
	public function register( KDNA_Events_CRM_Integration $integration ) {
		$id = sanitize_key( (string) $integration->get_id() );
		if ( '' === $id ) {
			return;
		}
		$this->integrations[ $id ] = $integration;
	}

	/**
	 * Return every registered integration, after the filter hook.
	 *
	 * @return array<string,KDNA_Events_CRM_Integration>
	 */
	public function get_all() {
		/**
		 * Filter the list of registered CRM integrations.
		 *
		 * Add-ons can use this to reorder, drop or replace entries.
		 *
		 * @param array<string,KDNA_Events_CRM_Integration> $integrations
		 */
		$filtered = apply_filters( 'kdna_events_crm_integrations', $this->integrations );
		return is_array( $filtered ) ? $filtered : array();
	}

	/**
	 * Fetch a single integration by id.
	 *
	 * @param string $id Integration id.
	 * @return KDNA_Events_CRM_Integration|null
	 */
	public function get( $id ) {
		$all = $this->get_all();
		$id  = sanitize_key( (string) $id );
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Whether the id corresponds to an enabled integration.
	 *
	 * @param string $id Integration id.
	 * @return bool
	 */
	public function is_enabled( $id ) {
		$enabled = (array) get_option( 'kdna_events_crm_enabled', array() );
		return ! empty( $enabled[ sanitize_key( (string) $id ) ] );
	}
}

/**
 * CRM bootstrap, bridge and admin actions.
 */
class KDNA_Events_CRM {

	/**
	 * Rolling-log transient key.
	 */
	const LOG_TRANSIENT = 'kdna_events_crm_sync_log';

	/**
	 * Maximum retained log entries.
	 */
	const LOG_MAX_ENTRIES = 200;

	/**
	 * Shared registry instance.
	 *
	 * @var KDNA_Events_CRM_Registry|null
	 */
	protected static $registry = null;

	/**
	 * Wire hooks on plugins_loaded priority 20.
	 *
	 * @return void
	 */
	public static function bootstrap() {
		add_action( 'plugins_loaded', array( __CLASS__, 'init_registry' ), 20 );
		add_action( 'kdna_events_ticket_created', array( __CLASS__, 'on_ticket_created' ), 10, 3 );
		add_action( 'wp_ajax_kdna_events_crm_test', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_kdna_events_crm_clear_log', array( __CLASS__, 'ajax_clear_log' ) );
	}

	/**
	 * Create the registry and fire the add-on registration action.
	 *
	 * @return void
	 */
	public static function init_registry() {
		self::$registry = new KDNA_Events_CRM_Registry();

		/**
		 * Action fired so add-ons can register integrations.
		 *
		 * @param KDNA_Events_CRM_Registry $registry Registry instance.
		 */
		do_action( 'kdna_events_register_crm_integrations', self::$registry );
	}

	/**
	 * Return the registry, lazy-initialising if bootstrap has not run yet.
	 *
	 * @return KDNA_Events_CRM_Registry
	 */
	public static function registry() {
		if ( ! self::$registry instanceof KDNA_Events_CRM_Registry ) {
			self::init_registry();
		}
		return self::$registry;
	}

	/**
	 * Whether the master CRM switch is on.
	 *
	 * @return bool
	 */
	public static function master_enabled() {
		$value = get_option( 'kdna_events_crm_master_enabled', true );
		if ( '' === $value ) {
			return true;
		}
		return (bool) $value;
	}

	/**
	 * Ticket-created listener. Builds the payload and fans out to enabled integrations.
	 *
	 * @param int $ticket_id Ticket ID.
	 * @param int $order_id  Order ID.
	 * @param int $event_id  Event ID.
	 * @return void
	 */
	public static function on_ticket_created( $ticket_id, $order_id, $event_id ) {
		if ( ! self::master_enabled() ) {
			return;
		}

		$registry = self::registry();
		if ( empty( $registry->get_all() ) ) {
			return;
		}

		$payload = self::build_payload( (int) $ticket_id, (int) $order_id, (int) $event_id );
		if ( empty( $payload ) ) {
			return;
		}

		/**
		 * Filter the payload passed to every CRM integration.
		 *
		 * @param array $payload   Payload.
		 * @param int   $ticket_id Ticket ID.
		 * @param int   $order_id  Order ID.
		 */
		$payload = apply_filters( 'kdna_events_crm_sync_data', $payload, (int) $ticket_id, (int) $order_id );

		foreach ( $registry->get_all() as $id => $integration ) {
			if ( ! $registry->is_enabled( $id ) ) {
				continue;
			}

			// Per-integration try/catch so one broken adapter never bubbles up.
			try {
				$result = $integration->sync_ticket( $payload );
			} catch ( Throwable $e ) {
				$result = new WP_Error( 'exception', $e->getMessage() );
			}

			self::log_sync_result( $id, $payload, $result );

			/**
			 * Fires after a sync attempt.
			 *
			 * @param string        $id          Integration id.
			 * @param array         $payload     Payload sent.
			 * @param true|WP_Error $result      Result returned by the integration.
			 * @param int           $ticket_id   Ticket ID.
			 */
			do_action( 'kdna_events_after_crm_sync', $id, $payload, $result, (int) $ticket_id );
		}
	}

	/**
	 * Build the Section 7 payload for a ticket.
	 *
	 * @param int $ticket_id Ticket ID.
	 * @param int $order_id  Order ID.
	 * @param int $event_id  Event ID.
	 * @return array
	 */
	public static function build_payload( $ticket_id, $order_id, $event_id ) {
		$ticket = class_exists( 'KDNA_Events_Tickets' ) ? self::fetch_ticket_row( $ticket_id ) : null;
		$order  = class_exists( 'KDNA_Events_Orders' ) ? KDNA_Events_Orders::get_order( $order_id ) : null;

		if ( ! $ticket || ! $order ) {
			return array();
		}

		$event_id        = (int) $event_id;
		$event_location  = kdna_events_get_event_location( $event_id );
		$attendee_custom = array();
		if ( ! empty( $ticket->attendee_fields ) ) {
			$decoded = json_decode( (string) $ticket->attendee_fields, true );
			if ( is_array( $decoded ) ) {
				$attendee_custom = $decoded;
			}
		}

		$price    = (float) get_post_meta( $event_id, '_kdna_event_price', true );
		$currency = strtoupper( (string) get_post_meta( $event_id, '_kdna_event_currency', true ) );
		if ( '' === $currency ) {
			$currency = strtoupper( (string) ( $order->currency ?? get_option( 'kdna_events_default_currency', 'AUD' ) ) );
		}

		return array(
			'ticket_code'     => (string) $ticket->ticket_code,
			'order_reference' => (string) $order->order_reference,
			'event'           => array(
				'id'        => $event_id,
				'title'     => get_the_title( $event_id ),
				'subtitle'  => (string) get_post_meta( $event_id, '_kdna_event_subtitle', true ),
				'start'     => (string) get_post_meta( $event_id, '_kdna_event_start', true ),
				'end'       => (string) get_post_meta( $event_id, '_kdna_event_end', true ),
				'type'      => (string) get_post_meta( $event_id, '_kdna_event_type', true ),
				'location'  => array(
					'name'    => (string) $event_location['name'],
					'address' => (string) $event_location['address'],
					'lat'     => (float) $event_location['lat'],
					'lng'     => (float) $event_location['lng'],
				),
				'price'     => $price,
				'currency'  => $currency,
				'organiser' => ( function () use ( $event_id ) {
					$organiser = kdna_events_get_event_organiser( $event_id );
					return array(
						'name'  => (string) $organiser['name'],
						'email' => (string) $organiser['email'],
					);
				} )(),
			),
			'attendee'        => array(
				'name'          => (string) $ticket->attendee_name,
				'email'         => (string) $ticket->attendee_email,
				'phone'         => (string) $ticket->attendee_phone,
				'custom_fields' => $attendee_custom,
			),
			'purchaser'       => array(
				'name'  => (string) $order->purchaser_name,
				'email' => (string) $order->purchaser_email,
			),
			'is_free'         => in_array( (string) $order->status, array( 'free', 'pending_free' ), true ) || $price <= 0,
			'total'           => (float) $order->total,
		);
	}

	/**
	 * Fetch a single ticket row by id.
	 *
	 * @param int $ticket_id Ticket ID.
	 * @return object|null
	 */
	protected static function fetch_ticket_row( $ticket_id ) {
		global $wpdb;
		$table = KDNA_Events_DB::tickets_table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ticket_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $ticket_id
			)
		);
	}

	/**
	 * Append to the rolling log, trimmed to the last LOG_MAX_ENTRIES.
	 *
	 * @param string        $id      Integration id.
	 * @param array         $payload Payload sent.
	 * @param true|WP_Error $result  Result.
	 * @return void
	 */
	public static function log_sync_result( $id, $payload, $result ) {
		$log = get_transient( self::LOG_TRANSIENT );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$is_error = is_wp_error( $result );

		$log[] = array(
			'timestamp'   => current_time( 'mysql' ),
			'integration' => (string) $id,
			'ticket_code' => isset( $payload['ticket_code'] ) ? (string) $payload['ticket_code'] : '',
			'status'      => $is_error ? 'error' : 'success',
			'message'     => $is_error ? $result->get_error_message() : __( 'Synced', 'kdna-events' ),
		);

		if ( count( $log ) > self::LOG_MAX_ENTRIES ) {
			$log = array_slice( $log, -self::LOG_MAX_ENTRIES );
		}

		set_transient( self::LOG_TRANSIENT, $log, MONTH_IN_SECONDS );
	}

	/**
	 * Return the current rolling log newest-first.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function get_log() {
		$log = get_transient( self::LOG_TRANSIENT );
		if ( ! is_array( $log ) ) {
			return array();
		}
		return array_reverse( $log );
	}

	/**
	 * Clear the rolling log.
	 *
	 * @return void
	 */
	public static function clear_log() {
		delete_transient( self::LOG_TRANSIENT );
	}

	/**
	 * AJAX handler for the per-integration Test Connection button.
	 *
	 * @return void
	 */
	public static function ajax_test_connection() {
		check_ajax_referer( 'kdna_events_crm_test', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kdna-events' ) ), 403 );
		}

		$id          = isset( $_POST['integration_id'] ) ? sanitize_key( wp_unslash( $_POST['integration_id'] ) ) : '';
		$integration = self::registry()->get( $id );

		if ( ! $integration ) {
			wp_send_json_error( array( 'message' => __( 'Integration not found.', 'kdna-events' ) ), 404 );
		}

		try {
			$result = $integration->test_connection();
		} catch ( Throwable $e ) {
			$result = new WP_Error( 'exception', $e->getMessage() );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: integration name */
					__( 'Connection to %s OK.', 'kdna-events' ),
					$integration->get_name()
				),
			)
		);
	}

	/**
	 * AJAX handler for the Clear Log button.
	 *
	 * @return void
	 */
	public static function ajax_clear_log() {
		check_ajax_referer( 'kdna_events_crm_test', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kdna-events' ) ), 403 );
		}

		self::clear_log();
		wp_send_json_success( array( 'message' => __( 'Log cleared.', 'kdna-events' ) ) );
	}
}
