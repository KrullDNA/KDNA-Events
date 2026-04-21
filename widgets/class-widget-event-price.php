<?php
/**
 * Event Price widget.
 *
 * Displays the event price. When the event is free, shows the
 * configurable 'Free' label instead. Supports a 'from' prefix,
 * currency symbol toggle with before/after positioning, and an
 * optional badge visual treatment.
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
 * Elementor widget rendering the event price.
 */
class KDNA_Events_Widget_Event_Price extends KDNA_Events_Widget_Base {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-event-price';
	}

	/**
	 * Visible title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Event Price', 'kdna-events' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-price-list';
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

		$this->add_control(
			'free_label',
			array(
				'label'   => __( 'Free label', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Free', 'kdna-events' ),
			)
		);

		$this->add_control(
			'show_currency',
			array(
				'label'        => __( 'Show currency symbol', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'currency_position',
			array(
				'label'     => __( 'Currency position', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'before',
				'options'   => array(
					'before' => __( 'Before amount', 'kdna-events' ),
					'after'  => __( 'After amount', 'kdna-events' ),
				),
				'condition' => array( 'show_currency' => 'yes' ),
			)
		);

		$this->add_control(
			'from_prefix',
			array(
				'label'       => __( 'From prefix', 'kdna-events' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'Optional text shown before the price, e.g. "From".', 'kdna-events' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_price_style',
			array(
				'label' => __( 'Price', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'price_color',
			array(
				'label'     => __( 'Amount colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-price__amount' => 'color: {{VALUE}};',
				),
			)
		);

		$this->register_typography_control( 'amount_typography', __( 'Amount typography', 'kdna-events' ), '.kdna-events-event-price__amount' );

		$this->add_control(
			'currency_color',
			array(
				'label'     => __( 'Currency colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-price__currency' => 'color: {{VALUE}};',
				),
				'condition' => array( 'show_currency' => 'yes' ),
			)
		);

		$this->register_typography_control( 'currency_typography', __( 'Currency typography', 'kdna-events' ), '.kdna-events-event-price__currency' );

		$this->register_spacing_controls( 'wrapper', '.kdna-events-event-price' );

		$this->end_controls_section();

		$this->start_controls_section(
			'section_badge_style',
			array(
				'label' => __( 'Badge', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'badge_mode',
			array(
				'label'        => __( 'Badge mode', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Wraps the price in a styled badge.', 'kdna-events' ),
			)
		);

		$this->add_control(
			'badge_background',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-price--badge' => 'background-color: {{VALUE}};',
				),
				'condition' => array( 'badge_mode' => 'yes' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'      => 'badge_border',
				'selector'  => '{{WRAPPER}} .kdna-events-event-price--badge',
				'condition' => array( 'badge_mode' => 'yes' ),
			)
		);

		$this->add_control(
			'badge_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-price--badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array( 'badge_mode' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'badge_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-price--badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array( 'badge_mode' => 'yes' ),
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
			$this->render_editor_placeholder( __( 'Create an event to preview the price.', 'kdna-events' ) );
			return;
		}

		$settings = $this->get_settings_for_display();
		$is_free  = kdna_events_is_free( $event_id );

		$classes = array( 'kdna-events-event-price' );
		if ( 'yes' === ( $settings['badge_mode'] ?? '' ) ) {
			$classes[] = 'kdna-events-event-price--badge';
		}
		if ( $is_free ) {
			$classes[] = 'kdna-events-event-price--free';
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';

		if ( $is_free ) {
			printf(
				'<span class="kdna-events-event-price__free">%s</span>',
				esc_html( (string) ( $settings['free_label'] ?? __( 'Free', 'kdna-events' ) ) )
			);
		} else {
			$price    = (float) get_post_meta( $event_id, '_kdna_event_price', true );
			$currency = strtoupper( (string) get_post_meta( $event_id, '_kdna_event_currency', true ) );
			if ( '' === $currency ) {
				$currency = (string) get_option( 'kdna_events_default_currency', 'AUD' );
			}

			$symbol       = $this->currency_symbol( $currency );
			$show_currency = 'yes' === ( $settings['show_currency'] ?? 'yes' );
			$position     = $settings['currency_position'] ?? 'before';
			$from_prefix  = trim( (string) ( $settings['from_prefix'] ?? '' ) );

			if ( '' !== $from_prefix ) {
				printf( '<span class="kdna-events-event-price__from">%s</span> ', esc_html( $from_prefix ) );
			}

			if ( $show_currency && 'before' === $position ) {
				printf( '<span class="kdna-events-event-price__currency">%s</span>', esc_html( $symbol ) );
			}

			printf( '<span class="kdna-events-event-price__amount">%s</span>', esc_html( number_format_i18n( $price, 2 ) ) );

			if ( $show_currency && 'after' === $position ) {
				printf( '<span class="kdna-events-event-price__currency">%s</span>', esc_html( $symbol ) );
			}
		}

		echo '</div>';
	}

	/**
	 * Return a currency symbol for a three-letter code.
	 *
	 * Mirrors the lightweight map in helpers.php so the widget does not
	 * need the price formatter to build a combined string.
	 *
	 * @param string $currency 3-letter code.
	 * @return string
	 */
	protected function currency_symbol( $currency ) {
		$map = array(
			'AUD' => '$',
			'USD' => '$',
			'NZD' => '$',
			'CAD' => '$',
			'GBP' => '£',
			'EUR' => '€',
		);
		return isset( $map[ $currency ] ) ? $map[ $currency ] : ( $currency . ' ' );
	}
}
