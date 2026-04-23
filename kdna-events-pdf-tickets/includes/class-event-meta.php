<?php
/**
 * Event meta box owned by the PDF Tickets add-on.
 *
 * Adds a 'Ticket Terms / Fine Print' textarea to the kdna_event
 * edit screen. The field lives on core's post type but is owned
 * entirely by the add-on (registered via register_post_meta with
 * the add-on's key, saved via its own meta box callback). No core
 * files are modified.
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event Ticket Terms meta box.
 */
class KDNA_Events_PDF_Event_Meta {

	const META_KEY     = '_kdna_events_pdf_ticket_terms';
	const NONCE_ACTION = 'kdna_events_pdf_event_meta';
	const NONCE_NAME   = 'kdna_events_pdf_event_meta_nonce';

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
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_kdna_event', array( $this, 'save_meta' ), 10, 2 );
	}

	/**
	 * Register the post meta with sane defaults so REST can read /
	 * write it.
	 *
	 * @return void
	 */
	public function register_meta() {
		register_post_meta(
			'kdna_event',
			self::META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					unset( $allowed, $meta_key );
					return current_user_can( 'edit_post', (int) $post_id );
				},
			)
		);
	}

	/**
	 * Register the meta box on the event edit screen.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		add_meta_box(
			'kdna_events_pdf_ticket_terms',
			__( 'PDF Ticket Terms / Fine Print', 'kdna-events-pdf-tickets' ),
			array( $this, 'render_meta_box' ),
			'kdna_event',
			'normal',
			'default'
		);
	}

	/**
	 * Render the meta box.
	 *
	 * @param WP_Post $post Current event post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$value = (string) get_post_meta( $post->ID, self::META_KEY, true );
		?>
		<p class="description" style="margin:0 0 8px;">
			<?php esc_html_e( 'Shown at the bottom of the PDF ticket next to the barcode. Use for entry instructions, terms, or a reminder line. Leave blank to omit the block.', 'kdna-events-pdf-tickets' ); ?>
		</p>
		<textarea name="<?php echo esc_attr( self::META_KEY ); ?>" rows="4" style="width:100%;" placeholder="<?php esc_attr_e( 'Lorem ipsum...', 'kdna-events-pdf-tickets' ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	/**
	 * Persist the meta box value.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta( $post_id, $post ) {
		unset( $post );
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', (int) $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$value = isset( $_POST[ self::META_KEY ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ self::META_KEY ] ) ) : '';
		update_post_meta( $post_id, self::META_KEY, $value );
	}
}
