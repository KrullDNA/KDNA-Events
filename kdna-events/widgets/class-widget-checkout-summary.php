<?php
/**
 * Checkout Event Summary widget.
 *
 * Shows the event image, title, subtitle, start datetime and location
 * on the checkout page so the buyer knows what they are booking.
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
 * Elementor widget rendering the checkout event summary.
 */
class KDNA_Events_Widget_Checkout_Summary extends KDNA_Events_Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-checkout-summary';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Checkout Event Summary', 'kdna-events' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-info-box';
	}

	/**
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'kdna-events-frontend', 'kdna-events-checkout' );
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

		$toggles = array(
			'show_image'    => array( __( 'Image', 'kdna-events' ), 'yes' ),
			'show_title'    => array( __( 'Title', 'kdna-events' ), 'yes' ),
			'show_subtitle' => array( __( 'Subtitle', 'kdna-events' ), 'yes' ),
			'show_date'     => array( __( 'Start date and time', 'kdna-events' ), 'yes' ),
			'show_location' => array( __( 'Location', 'kdna-events' ), 'yes' ),
		);
		foreach ( $toggles as $key => $info ) {
			$this->add_control(
				$key,
				array(
					'label'        => $info[0],
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => $info[1],
				)
			);
		}

		$this->add_group_control(
			\Elementor\Group_Control_Image_Size::get_type(),
			array(
				'name'      => 'image_size',
				'default'   => 'medium_large',
				'condition' => array( 'show_image' => 'yes' ),
			)
		);

		$this->add_control(
			'title_tag',
			array(
				'label'   => __( 'Title HTML tag', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'h2',
				'options' => array(
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'div'  => 'div',
					'span' => 'span',
				),
			)
		);

		$this->add_control(
			'date_format',
			array(
				'label'   => __( 'Date format', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'j F Y, g:i a',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_layout',
			array(
				'label' => __( 'Layout', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'side-by-side',
				'options' => array(
					'stacked'      => __( 'Stacked', 'kdna-events' ),
					'side-by-side' => __( 'Side by side', 'kdna-events' ),
				),
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'      => __( 'Gap', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 64,
					),
					'em' => array(
						'min'  => 0,
						'max'  => 4,
						'step' => 0.1,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 20,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-checkout-summary' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// Card style.
		$this->start_controls_section(
			'section_style_card',
			array(
				'label' => __( 'Card', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'card_background',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-summary' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .kdna-events-checkout-summary',
			)
		);

		$this->add_control(
			'card_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-checkout-summary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
				),
			)
		);

		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-checkout-summary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// Image style.
		$this->start_controls_section(
			'section_style_image',
			array(
				'label'     => __( 'Image', 'kdna-events' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_image' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'image_width',
			array(
				'label'      => __( 'Width', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array(
						'min' => 80,
						'max' => 600,
					),
					'%'  => array(
						'min' => 20,
						'max' => 100,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 240,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-checkout-summary__image' => 'width: {{SIZE}}{{UNIT}}; flex: 0 0 {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'image_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-checkout-summary__image, {{WRAPPER}} .kdna-events-checkout-summary__image img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$text_elements = array(
			'title'    => array( __( 'Title', 'kdna-events' ), '.kdna-events-checkout-summary__title' ),
			'subtitle' => array( __( 'Subtitle', 'kdna-events' ), '.kdna-events-checkout-summary__subtitle' ),
			'date'     => array( __( 'Date', 'kdna-events' ), '.kdna-events-checkout-summary__date' ),
			'location' => array( __( 'Location', 'kdna-events' ), '.kdna-events-checkout-summary__location' ),
		);

		foreach ( $text_elements as $key => $info ) {
			$this->start_controls_section(
				'section_style_' . $key,
				array(
					'label' => $info[0],
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_control(
				$key . '_color',
				array(
					'label'     => __( 'Colour', 'kdna-events' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} ' . $info[1] => 'color: {{VALUE}};',
					),
				)
			);
			$this->register_typography_control( $key . '_typography', __( 'Typography', 'kdna-events' ), $info[1] );
			$this->add_responsive_control(
				$key . '_spacing',
				array(
					'label'      => __( 'Bottom spacing', 'kdna-events' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 48,
						),
						'em' => array(
							'min'  => 0,
							'max'  => 3,
							'step' => 0.1,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}} ' . $info[1] => 'margin-bottom: {{SIZE}}{{UNIT}};',
					),
				)
			);
			$this->end_controls_section();
		}
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render() {
		$event_id = $this->get_checkout_event_id();
		if ( ! $event_id ) {
			$this->render_editor_placeholder( __( 'Open the checkout page with ?event_id=X to preview this widget.', 'kdna-events' ) );
			return;
		}

		$settings = $this->get_settings_for_display();

		$layout    = 'side-by-side' === ( $settings['layout'] ?? 'side-by-side' ) ? 'side-by-side' : 'stacked';
		$title_tag = (string) ( $settings['title_tag'] ?? 'h2' );
		if ( ! in_array( $title_tag, array( 'h1', 'h2', 'h3', 'h4', 'div', 'span' ), true ) ) {
			$title_tag = 'h2';
		}

		$show_image    = 'yes' === ( $settings['show_image'] ?? 'yes' );
		$show_title    = 'yes' === ( $settings['show_title'] ?? 'yes' );
		$show_subtitle = 'yes' === ( $settings['show_subtitle'] ?? 'yes' );
		$show_date     = 'yes' === ( $settings['show_date'] ?? 'yes' );
		$show_location = 'yes' === ( $settings['show_location'] ?? 'yes' );

		$start_raw  = (string) get_post_meta( $event_id, '_kdna_event_start', true );
		$subtitle   = (string) get_post_meta( $event_id, '_kdna_event_subtitle', true );
		$location   = kdna_events_get_event_location( $event_id );
		$image_size = isset( $settings['image_size_size'] ) ? (string) $settings['image_size_size'] : 'medium_large';
		$date_fmt   = (string) ( $settings['date_format'] ?? 'j F Y, g:i a' );
		?>
		<div class="kdna-events-checkout-summary kdna-events-checkout-summary--<?php echo esc_attr( $layout ); ?>" data-event-id="<?php echo esc_attr( (string) $event_id ); ?>">
			<?php if ( $show_image && has_post_thumbnail( $event_id ) ) : ?>
				<div class="kdna-events-checkout-summary__image">
					<?php echo get_the_post_thumbnail( $event_id, $image_size, array( 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
			<div class="kdna-events-checkout-summary__body">
				<?php if ( $show_title ) : ?>
					<<?php echo esc_attr( $title_tag ); ?> class="kdna-events-checkout-summary__title">
						<?php echo esc_html( get_the_title( $event_id ) ); ?>
					</<?php echo esc_attr( $title_tag ); ?>>
				<?php endif; ?>

				<?php if ( $show_subtitle && '' !== $subtitle ) : ?>
					<p class="kdna-events-checkout-summary__subtitle"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>

				<?php if ( $show_date && '' !== $start_raw ) : ?>
					<div class="kdna-events-checkout-summary__date">
						<?php echo esc_html( kdna_events_format_datetime( $start_raw, $date_fmt, $event_id ) ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $show_location && ( '' !== $location['name'] || '' !== $location['address'] ) ) : ?>
					<div class="kdna-events-checkout-summary__location">
						<?php
						$bits = array_filter( array( $location['name'], $location['address'] ), static function ( $p ) { return '' !== $p; } );
						echo esc_html( implode( ', ', $bits ) );
						?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
