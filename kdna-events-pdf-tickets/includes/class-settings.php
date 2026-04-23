<?php
/**
 * Settings page for the PDF Tickets add-on.
 *
 * Registers a submenu under the core Events menu, renders the
 * control sections per Section 5 of the brief, exposes the live
 * preview AJAX endpoint + the Download sample serving endpoint.
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page controller.
 */
class KDNA_Events_PDF_Settings {

	const OPTION_GROUP  = 'kdna_events_pdf';
	const PAGE_SLUG     = 'kdna-events-pdf-settings';
	const PARENT_SLUG   = 'kdna-events';
	const PREVIEW_AJAX  = 'kdna_events_pdf_preview';
	const SAMPLE_AJAX   = 'kdna_events_pdf_sample_download';

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
	 * Register menu, settings and assets.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::PREVIEW_AJAX, array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_' . self::SAMPLE_AJAX, array( $this, 'ajax_sample' ) );
		add_filter( 'upload_mimes', array( $this, 'allow_font_uploads' ) );
	}

	/**
	 * Allow TTF / OTF font uploads in the WordPress Media Library so
	 * admins can drop a Google Fonts family in and paste the URL
	 * into the PDF settings page.
	 *
	 * @param array $mimes
	 * @return array
	 */
	public function allow_font_uploads( $mimes ) {
		$mimes['ttf'] = 'font/ttf';
		$mimes['otf'] = 'font/otf';
		return $mimes;
	}

	/**
	 * Add the submenu under the core Events menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'PDF Tickets', 'kdna-events-pdf-tickets' ),
			__( 'PDF Tickets', 'kdna-events-pdf-tickets' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Curated font dropdown, mirrors the core Email Design options.
	 *
	 * The labels are identical to the core email font picker so the
	 * two settings tabs feel like one connected experience.
	 *
	 * @return array<string,string>
	 */
	public static function font_options() {
		if ( class_exists( 'KDNA_Events_Settings' ) && method_exists( 'KDNA_Events_Settings', 'email_font_options' ) ) {
			$core = KDNA_Events_Settings::email_font_options();
			if ( is_array( $core ) && ! empty( $core ) ) {
				return $core;
			}
		}
		// Fallback list if core Brief A is not on this install.
		return array(
			'google:Inter'            => __( 'Inter (Google, recommended)', 'kdna-events-pdf-tickets' ),
			'google:Roboto'           => __( 'Roboto (Google)', 'kdna-events-pdf-tickets' ),
			'google:Poppins'          => __( 'Poppins (Google)', 'kdna-events-pdf-tickets' ),
			'google:Montserrat'       => __( 'Montserrat (Google)', 'kdna-events-pdf-tickets' ),
			'google:Open Sans'        => __( 'Open Sans (Google)', 'kdna-events-pdf-tickets' ),
			'google:Lato'             => __( 'Lato (Google)', 'kdna-events-pdf-tickets' ),
			'google:Nunito'           => __( 'Nunito (Google)', 'kdna-events-pdf-tickets' ),
			'google:Work Sans'        => __( 'Work Sans (Google)', 'kdna-events-pdf-tickets' ),
			'google:DM Sans'          => __( 'DM Sans (Google)', 'kdna-events-pdf-tickets' ),
			'google:Manrope'          => __( 'Manrope (Google)', 'kdna-events-pdf-tickets' ),
			'google:Playfair Display' => __( 'Playfair Display (Google, serif)', 'kdna-events-pdf-tickets' ),
			'google:Merriweather'     => __( 'Merriweather (Google, serif)', 'kdna-events-pdf-tickets' ),
			'system:arial'            => __( 'Arial (system)', 'kdna-events-pdf-tickets' ),
			'system:helvetica'        => __( 'Helvetica (system)', 'kdna-events-pdf-tickets' ),
			'system:georgia'          => __( 'Georgia (serif, system)', 'kdna-events-pdf-tickets' ),
			'system:verdana'          => __( 'Verdana (system)', 'kdna-events-pdf-tickets' ),
			'system:tahoma'           => __( 'Tahoma (system)', 'kdna-events-pdf-tickets' ),
			'system:trebuchet'        => __( 'Trebuchet MS (system)', 'kdna-events-pdf-tickets' ),
			'system:times'            => __( 'Times New Roman (serif, system)', 'kdna-events-pdf-tickets' ),
		);
	}

	/**
	 * Resolve a font dropdown value to a Dompdf-safe font name.
	 *
	 * Dompdf only renders fonts registered in its font cache. The
	 * defaults that ship are Helvetica, Times and Courier. Google
	 * fonts and most system fonts are not available unless the TTF
	 * is pre-registered. We therefore collapse every choice to one
	 * of those three built-ins so the PDF always renders, and call
	 * out the fallback behaviour in the field help text.
	 *
	 * @param string $value Dropdown value (e.g. 'google:Inter').
	 * @return string
	 */
	public static function resolve_pdf_font( $value ) {
		$value = (string) $value;
		// Serif Google / system fonts collapse to Times.
		$serif_tokens = array( 'google:Playfair Display', 'google:Merriweather', 'system:georgia', 'system:times', 'times' );
		if ( in_array( $value, $serif_tokens, true ) ) {
			return 'times';
		}
		// Monospace.
		if ( 'courier' === $value ) {
			return 'courier';
		}
		// Everything else is sans-serif and falls back to Helvetica.
		return 'helvetica';
	}

	/**
	 * Option schema used for sanitiser dispatch + defaults.
	 *
	 * @return array<string,array{type:string,default:mixed}>
	 */
	public static function schema() {
		return array(
			// Inheritance toggles.
			'kdna_events_pdf_inherit_logo_id'              => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_pdf_inherit_header_bg'            => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_pdf_inherit_default_event_image' => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_pdf_inherit_color_primary'        => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_pdf_inherit_color_accent'         => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_pdf_inherit_business_name'        => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_pdf_inherit_support_email'        => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_pdf_inherit_support_phone'        => array( 'type' => 'boolean', 'default' => true ),

			// Brand overrides.
			'kdna_events_pdf_logo_id'              => array( 'type' => 'integer', 'default' => 0 ),
			'kdna_events_pdf_logo_width'           => array( 'type' => 'integer', 'default' => 140 ),
			'kdna_events_pdf_logo_align'           => array( 'type' => 'string',  'default' => 'center' ),
			'kdna_events_pdf_header_bg'            => array( 'type' => 'string',  'default' => '#2E75B6' ),
			'kdna_events_pdf_header_height'        => array( 'type' => 'integer', 'default' => 100 ),
			'kdna_events_pdf_default_event_image' => array( 'type' => 'integer', 'default' => 0 ),

			// Colours.
			'kdna_events_pdf_color_primary'        => array( 'type' => 'string',  'default' => '#2E75B6' ),
			'kdna_events_pdf_color_accent'         => array( 'type' => 'string',  'default' => '#F07759' ),
			'kdna_events_pdf_page_bg'              => array( 'type' => 'string',  'default' => '#FFFFFF' ),
			'kdna_events_pdf_heading_color'        => array( 'type' => 'string',  'default' => '#1A1A1A' ),
			'kdna_events_pdf_body_color'           => array( 'type' => 'string',  'default' => '#333333' ),
			'kdna_events_pdf_muted_color'          => array( 'type' => 'string',  'default' => '#888888' ),
			'kdna_events_pdf_divider_color'        => array( 'type' => 'string',  'default' => '#E5E5E5' ),

			// Typography.
			'kdna_events_pdf_heading_font'         => array( 'type' => 'string',  'default' => 'google:Inter' ),
			'kdna_events_pdf_heading_font_url'     => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_pdf_body_font_url'        => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_pdf_heading_size'         => array( 'type' => 'integer', 'default' => 20 ),
			'kdna_events_pdf_body_font'            => array( 'type' => 'string',  'default' => 'google:Inter' ),
			'kdna_events_pdf_body_size'            => array( 'type' => 'integer', 'default' => 11 ),
			'kdna_events_pdf_code_font'            => array( 'type' => 'string',  'default' => 'courier' ),
			'kdna_events_pdf_code_size'            => array( 'type' => 'integer', 'default' => 36 ),

			// Barcode.
			'kdna_events_pdf_barcode_type'         => array( 'type' => 'string',  'default' => 'code128' ),
			'kdna_events_pdf_barcode_width'        => array( 'type' => 'integer', 'default' => 80 ),
			'kdna_events_pdf_barcode_height'       => array( 'type' => 'integer', 'default' => 20 ),
			'kdna_events_pdf_barcode_show_text'    => array( 'type' => 'boolean', 'default' => true ),

			// Layout.
			'kdna_events_pdf_page_size'            => array( 'type' => 'string',  'default' => 'A4' ),
			'kdna_events_pdf_page_orientation'     => array( 'type' => 'string',  'default' => 'portrait' ),
			'kdna_events_pdf_page_margin'          => array( 'type' => 'integer', 'default' => 15 ),
			'kdna_events_pdf_tickets_per_order'    => array( 'type' => 'string',  'default' => 'combined' ),

			// Footer.
			'kdna_events_pdf_show_footer'          => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_pdf_business_name'        => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_pdf_website_url'          => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_pdf_support_email'        => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_pdf_support_phone'        => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_pdf_show_timestamp'       => array( 'type' => 'boolean', 'default' => false ),

			// Terms + button label.
			'kdna_events_pdf_terms_text'           => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_pdf_button_label'         => array( 'type' => 'string',  'default' => 'Download Ticket PDF' ),
		);
	}

	/**
	 * Register every option with the Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		foreach ( self::schema() as $name => $def ) {
			register_setting(
				self::OPTION_GROUP,
				$name,
				array(
					'type'              => 'string' === $def['type'] ? 'string' : $def['type'],
					'sanitize_callback' => array( $this, 'sanitize_value' ),
					'default'           => $def['default'],
				)
			);
		}
	}

	/**
	 * Sanitiser dispatcher keyed off the schema.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public function sanitize_value( $value ) {
		$option = '';
		if ( isset( $GLOBALS['wp_current_filter'] ) && is_array( $GLOBALS['wp_current_filter'] ) ) {
			foreach ( $GLOBALS['wp_current_filter'] as $filter ) {
				if ( 0 === strpos( (string) $filter, 'sanitize_option_' ) ) {
					$option = substr( (string) $filter, strlen( 'sanitize_option_' ) );
					break;
				}
			}
		}
		$schema = self::schema();
		if ( '' === $option || ! isset( $schema[ $option ] ) ) {
			return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		}
		$type = $schema[ $option ]['type'];
		switch ( $type ) {
			case 'boolean':
				return ! empty( $value );
			case 'integer':
				return max( 0, absint( $value ) );
			case 'string':
			default:
				$value = (string) $value;
				if ( 0 === strpos( $option, 'kdna_events_pdf_color_' )
					|| in_array( $option, array( 'kdna_events_pdf_header_bg', 'kdna_events_pdf_page_bg', 'kdna_events_pdf_heading_color', 'kdna_events_pdf_body_color', 'kdna_events_pdf_muted_color', 'kdna_events_pdf_divider_color' ), true ) ) {
					$value = trim( $value );
					if ( preg_match( '/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value ) ) {
						return strtoupper( $value );
					}
					return $schema[ $option ]['default'];
				}
				if ( 'kdna_events_pdf_support_email' === $option ) {
					return sanitize_email( $value );
				}
				if ( 'kdna_events_pdf_website_url' === $option ) {
					return esc_url_raw( $value );
				}
				if ( 'kdna_events_pdf_terms_text' === $option ) {
					return sanitize_textarea_field( $value );
				}
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Enqueue admin assets on the settings page only.
	 *
	 * @param string $hook
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script(
			'wp-color-picker',
			'window.kdnaEventsPdf = ' . wp_json_encode(
				array(
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'previewNonce' => wp_create_nonce( self::PREVIEW_AJAX ),
					'sampleNonce'  => wp_create_nonce( self::SAMPLE_AJAX ),
					'previewAction' => self::PREVIEW_AJAX,
					'sampleAction' => self::SAMPLE_AJAX,
				)
			) . ';'
		);
	}

	/**
	 * AJAX preview: render sample PDF, return as base64 data URI.
	 *
	 * @return void
	 */
	public function ajax_preview() {
		check_ajax_referer( self::PREVIEW_AJAX, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kdna-events-pdf-tickets' ) ), 403 );
		}
		// Overlay unsaved form values onto live options for the preview render.
		$schema = self::schema();
		foreach ( $schema as $name => $def ) {
			if ( ! isset( $_POST[ $name ] ) ) {
				continue;
			}
			$raw = wp_unslash( $_POST[ $name ] );
			if ( 'boolean' === $def['type'] ) {
				update_option( $name, ! empty( $raw ), false );
				continue;
			}
			if ( 'integer' === $def['type'] ) {
				update_option( $name, absint( $raw ), false );
				continue;
			}
			update_option( $name, sanitize_textarea_field( (string) $raw ), false );
		}

		$generator = new KDNA_Events_PDF_Generator();
		$pdf       = $generator->generate_sample();
		if ( '' === $pdf ) {
			wp_send_json_error( array( 'message' => __( 'Preview render failed.', 'kdna-events-pdf-tickets' ) ), 500 );
		}
		wp_send_json_success( array( 'data_uri' => 'data:application/pdf;base64,' . base64_encode( $pdf ) ) );
	}

	/**
	 * AJAX sample download: stream a sample PDF as a direct file.
	 *
	 * @return void
	 */
	public function ajax_sample() {
		check_ajax_referer( self::SAMPLE_AJAX, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'kdna-events-pdf-tickets' ), 403 );
		}
		$generator = new KDNA_Events_PDF_Generator();
		$pdf       = $generator->generate_sample();
		if ( '' === $pdf ) {
			wp_die( esc_html__( 'Sample render failed.', 'kdna-events-pdf-tickets' ), 500 );
		}
		if ( ! headers_sent() ) {
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="kdna-events-ticket-sample.pdf"' );
			header( 'Content-Length: ' . strlen( $pdf ) );
		}
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kdna-events-pdf-tickets' ) );
		}
		$o = array();
		foreach ( self::schema() as $name => $def ) {
			$value = get_option( $name, $def['default'] );
			if ( '' === $value || null === $value ) {
				$value = $def['default'];
			}
			$o[ $name ] = $value;
		}
		?>
		<div class="wrap kdna-events-pdf-settings">
			<h1><?php esc_html_e( 'PDF Tickets', 'kdna-events-pdf-tickets' ); ?></h1>
			<?php settings_errors(); ?>
			<div class="kdna-events-pdf-grid">
				<form method="post" action="options.php" class="kdna-events-pdf-form">
					<?php settings_fields( self::OPTION_GROUP ); ?>
					<?php $this->render_sections( $o ); ?>
					<?php submit_button(); ?>
				</form>
				<?php $this->render_preview_panel(); ?>
			</div>
			<?php $this->render_preview_script(); ?>
		</div>
		<?php
	}

	/**
	 * Inline JS for the preview panel + media picker + colour picker.
	 *
	 * @return void
	 */
	protected function render_preview_script() {
		$cfg = array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'previewNonce'  => wp_create_nonce( self::PREVIEW_AJAX ),
			'sampleNonce'   => wp_create_nonce( self::SAMPLE_AJAX ),
			'previewAction' => self::PREVIEW_AJAX,
			'sampleAction'  => self::SAMPLE_AJAX,
		);
		?>
		<style>
			.kdna-events-pdf-grid { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap: 20px; align-items: flex-start; }
			@media (max-width: 1100px) { .kdna-events-pdf-grid { grid-template-columns: 1fr; } }
			.kdna-events-pdf-preview-panel { position: sticky; top: 40px; border: 1px solid #dcdcde; border-radius: 6px; padding: 12px; background: #fff; }
			.kdna-events-pdf-preview-panel__bar { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; }
			.kdna-events-pdf-preview-panel__bar strong { flex: 1; }
			.kdna-events-pdf-preview-panel__frame { width: 100%; height: 760px; border: 1px solid #dcdcde; border-radius: 4px; background: #f4f4f4; }
		</style>
		<script>
		window.kdnaEventsPdf = <?php echo wp_json_encode( $cfg ); ?>;
		function kdnaEventsPdfBoot() {
			var cfg = window.kdnaEventsPdf;
			if (!cfg || !cfg.ajaxUrl) { return; }
			var $ = window.jQuery;
			if ($ && $.fn && $.fn.wpColorPicker) {
				$('.kdna-events-pdf-color').wpColorPicker({ change: schedule });
			}
			var panel = document.querySelector('[data-pdf-preview]');
			if (!panel) { return; }
			var frame = panel.querySelector('[data-pdf-preview-frame]');
			var refresh = panel.querySelector('[data-pdf-preview-refresh]');
			var sample = panel.querySelector('[data-pdf-sample-download]');
			var form = document.querySelector('.kdna-events-pdf-form');
			var timer = null;

			function collect() {
				var fd = new FormData();
				fd.append('action', cfg.previewAction);
				fd.append('nonce', cfg.previewNonce);
				if (form) {
					form.querySelectorAll('[name^="kdna_events_pdf_"]').forEach(function (el) {
						if (el.type === 'checkbox') {
							if (el.checked) { fd.append(el.name, el.value || '1'); }
						} else {
							fd.append(el.name, el.value);
						}
					});
				}
				return fd;
			}
			function render() {
				fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: collect() })
					.then(function (r) { return r.json(); })
					.then(function (res) { if (res && res.success && res.data && res.data.data_uri) { frame.src = res.data.data_uri; } });
			}
			function schedule() {
				if (timer) { clearTimeout(timer); }
				timer = setTimeout(render, 350);
			}
			if (form) {
				form.addEventListener('input', schedule);
				form.addEventListener('change', schedule);
				form.addEventListener('kdna:pdf-media-change', schedule);
			}
			if (refresh) { refresh.addEventListener('click', render); }
			if (sample) {
				sample.addEventListener('click', function () {
					var url = cfg.ajaxUrl + '?action=' + encodeURIComponent(cfg.sampleAction) + '&nonce=' + encodeURIComponent(cfg.sampleNonce);
					window.open(url, '_blank');
				});
			}

			// Media picker wiring.
			document.querySelectorAll('[data-pdf-media]').forEach(function (root) {
				var input = root.querySelector('[data-pdf-media-input]');
				var preview = root.querySelector('[data-pdf-media-preview]');
				var pick = root.querySelector('[data-pdf-media-select]');
				var remove = root.querySelector('[data-pdf-media-remove]');
				var frame;
				pick.addEventListener('click', function (e) {
					e.preventDefault();
					if (!window.wp || !window.wp.media) { return; }
					if (!frame) {
						frame = window.wp.media({ title: pick.textContent, button: { text: 'Use this' }, library: { type: ['image/jpeg', 'image/png'] }, multiple: false });
						frame.on('select', function () {
							var a = frame.state().get('selection').first().toJSON();
							input.value = a.id;
							preview.innerHTML = '<img src="' + (a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url) + '" alt="" style="max-width:180px;" />';
							remove.removeAttribute('hidden');
							form.dispatchEvent(new CustomEvent('kdna:pdf-media-change', { bubbles: true }));
						});
					}
					frame.open();
				});
				remove.addEventListener('click', function (e) {
					e.preventDefault();
					input.value = '';
					preview.innerHTML = '';
					remove.setAttribute('hidden', 'hidden');
					form.dispatchEvent(new CustomEvent('kdna:pdf-media-change', { bubbles: true }));
				});
			});

			render();
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', kdnaEventsPdfBoot);
		} else {
			kdnaEventsPdfBoot();
		}
		</script>
		<?php
	}
}
