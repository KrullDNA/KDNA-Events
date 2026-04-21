<?php
/**
 * Event Image widget.
 *
 * Renders the event featured image with configurable size, link target,
 * aspect, and a small catalogue of hover effects. Supports an overlay
 * colour for building hero-style treatments.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KDNA_Events_Widget_Base' ) ) {
	return;
}

/**
 * Elementor widget rendering the event featured image.
 */
class KDNA_Events_Widget_Event_Image extends KDNA_Events_Widget_Base {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-event-image';
	}

	/**
	 * Visible title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Event Image', 'kdna-events' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-image';
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Image_Size::get_type(),
			array(
				'name'    => 'image_size',
				'label'   => __( 'Image size', 'kdna-events' ),
				'default' => 'large',
			)
		);

		$this->add_control(
			'link_target',
			array(
				'label'   => __( 'Link to', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'none',
				'options' => array(
					'none'   => __( 'None', 'kdna-events' ),
					'single' => __( 'Event single', 'kdna-events' ),
					'media'  => __( 'Media file', 'kdna-events' ),
				),
			)
		);

		$this->add_control(
			'alt_fallback',
			array(
				'label'       => __( 'Alt fallback', 'kdna-events' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'Used when the attachment has no alt text.', 'kdna-events' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_size_style',
			array(
				'label' => __( 'Size and fit', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'image_width',
			array(
				'label'      => __( 'Width', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( '%', 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 1600,
					),
					'%'  => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default'    => array(
					'unit' => '%',
					'size' => 100,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-image, {{WRAPPER}} .kdna-events-event-image__img' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'image_max_height',
			array(
				'label'      => __( 'Max height', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vh' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 1200,
					),
					'vh' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-image__img' => 'max-height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'object_fit',
			array(
				'label'     => __( 'Object fit', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'cover',
				'options'   => array(
					'cover'      => __( 'Cover', 'kdna-events' ),
					'contain'    => __( 'Contain', 'kdna-events' ),
					'fill'       => __( 'Fill', 'kdna-events' ),
					'none'       => __( 'None', 'kdna-events' ),
					'scale-down' => __( 'Scale down', 'kdna-events' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-image__img' => 'object-fit: {{VALUE}};',
				),
			)
		);

		$this->register_spacing_controls( 'wrapper', '.kdna-events-event-image' );

		$this->end_controls_section();

		$this->start_controls_section(
			'section_appearance_style',
			array(
				'label' => __( 'Appearance', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'image_border',
				'selector' => '{{WRAPPER}} .kdna-events-event-image',
			)
		);

		$this->add_control(
			'border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-image, {{WRAPPER}} .kdna-events-event-image__img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'image_box_shadow',
				'selector' => '{{WRAPPER}} .kdna-events-event-image',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_hover_style',
			array(
				'label' => __( 'Hover', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'hover_effect',
			array(
				'label'   => __( 'Hover effect', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'none',
				'options' => array(
					'none'     => __( 'None', 'kdna-events' ),
					'zoom'     => __( 'Zoom', 'kdna-events' ),
					'brighten' => __( 'Brighten', 'kdna-events' ),
					'darken'   => __( 'Darken', 'kdna-events' ),
					'lift'     => __( 'Lift', 'kdna-events' ),
				),
			)
		);

		$this->register_hover_transition_control( 'hover_transition', '.kdna-events-event-image, .kdna-events-event-image__img', 300 );

		$this->end_controls_section();

		$this->start_controls_section(
			'section_overlay_style',
			array(
				'label' => __( 'Overlay', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'overlay_color',
			array(
				'label'     => __( 'Overlay colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-image__overlay' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'overlay_opacity',
			array(
				'label'      => __( 'Overlay opacity', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( '%' ),
				'range'      => array(
					'%' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default'    => array(
					'unit' => '%',
					'size' => 0,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-image__overlay' => 'opacity: calc({{SIZE}} / 100);',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render() {
		$event_id = $this->get_event_id();
		if ( ! $event_id ) {
			$this->render_editor_placeholder( __( 'Create an event to preview the image.', 'kdna-events' ) );
			return;
		}

		if ( ! has_post_thumbnail( $event_id ) ) {
			$this->render_editor_placeholder( __( 'No featured image set for this event.', 'kdna-events' ) );
			return;
		}

		$settings   = $this->get_settings_for_display();
		$image_size = isset( $settings['image_size_size'] ) ? (string) $settings['image_size_size'] : 'large';
		$effect     = $settings['hover_effect'] ?? 'none';
		$alt_fall   = (string) ( $settings['alt_fallback'] ?? '' );
		$link_mode  = (string) ( $settings['link_target'] ?? 'none' );

		$attachment_id = (int) get_post_thumbnail_id( $event_id );
		$alt           = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( '' === $alt ) {
			$alt = $alt_fall;
		}
		if ( '' === $alt ) {
			$alt = get_the_title( $event_id );
		}

		$classes = array( 'kdna-events-event-image', 'kdna-events-event-image--hover-' . preg_replace( '/[^a-z]/', '', (string) $effect ) );

		$image_html = wp_get_attachment_image(
			$attachment_id,
			$image_size,
			false,
			array(
				'class' => 'kdna-events-event-image__img',
				'alt'   => $alt,
			)
		);

		$href   = '';
		if ( 'single' === $link_mode ) {
			$href = get_permalink( $event_id );
		} elseif ( 'media' === $link_mode ) {
			$href = wp_get_attachment_url( $attachment_id );
		}
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php if ( '' !== $href ) : ?>
				<a class="kdna-events-event-image__link" href="<?php echo esc_url( $href ); ?>"<?php echo 'media' === $link_mode ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
					<?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="kdna-events-event-image__overlay" aria-hidden="true"></span>
				</a>
			<?php else : ?>
				<?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="kdna-events-event-image__overlay" aria-hidden="true"></span>
			<?php endif; ?>
		</div>
		<?php
	}
}
