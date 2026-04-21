<?php
/**
 * Event Grid widget.
 *
 * Archive-facing widget listing events as styled cards. Uses the
 * shared card partial so AJAX filtering and initial render produce
 * identical markup. Supports columns, gap, pagination, and per-card
 * element toggles with full styling surfaces.
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
 * Elementor widget rendering the events grid.
 */
class KDNA_Events_Widget_Event_Grid extends KDNA_Events_Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-event-grid';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Event Grid', 'kdna-events' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-posts-grid';
	}

	/**
	 * Declare the frontend script as a widget dependency.
	 *
	 * Needed for pagination clicks and Load More to function when the
	 * grid is used on a page that is not in the default enqueue scope.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'kdna-events-frontend' );
	}

	/**
	 * Return a list of event categories for the selector.
	 *
	 * @return array<int,string>
	 */
	protected function category_options() {
		$out   = array( 0 => __( 'All categories', 'kdna-events' ) );
		$terms = get_terms(
			array(
				'taxonomy'   => 'kdna_event_category',
				'hide_empty' => false,
			)
		);
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$out[ (int) $term->term_id ] = $term->name;
			}
		}
		return $out;
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_source',
			array(
				'label' => __( 'Source', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'upcoming_only',
			array(
				'label'        => __( 'Upcoming only', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'include_past',
			array(
				'label'        => __( 'Include past events', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Overrides the upcoming-only filter.', 'kdna-events' ),
			)
		);

		$this->add_control(
			'category',
			array(
				'label'   => __( 'Category', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 0,
				'options' => $this->category_options(),
			)
		);

		$this->add_control(
			'order_by',
			array(
				'label'   => __( 'Order by', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'start',
				'options' => array(
					'start' => __( 'Start date', 'kdna-events' ),
					'title' => __( 'Title', 'kdna-events' ),
					'price' => __( 'Price', 'kdna-events' ),
				),
			)
		);

		$this->add_control(
			'order',
			array(
				'label'   => __( 'Order', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'ASC',
				'options' => array(
					'ASC'  => __( 'Ascending', 'kdna-events' ),
					'DESC' => __( 'Descending', 'kdna-events' ),
				),
			)
		);

		$this->add_control(
			'posts_per_page',
			array(
				'label'   => __( 'Posts per page', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 50,
				'default' => 9,
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

		$this->add_responsive_control(
			'columns',
			array(
				'label'           => __( 'Columns', 'kdna-events' ),
				'type'            => \Elementor\Controls_Manager::NUMBER,
				'min'             => 1,
				'max'             => 6,
				'default'         => 3,
				'tablet_default'  => 2,
				'mobile_default'  => 1,
				'selectors'       => array(
					'{{WRAPPER}} .kdna-events-grid__wrapper' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
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
						'max' => 80,
					),
					'em' => array(
						'min'  => 0,
						'max'  => 5,
						'step' => 0.1,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 24,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-grid__wrapper' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'pagination',
			array(
				'label'   => __( 'Pagination', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'numbered',
				'options' => array(
					'none'      => __( 'None', 'kdna-events' ),
					'numbered'  => __( 'Numbered', 'kdna-events' ),
					'load_more' => __( 'Load more', 'kdna-events' ),
				),
			)
		);

		$this->add_control(
			'load_more_label',
			array(
				'label'     => __( 'Load more label', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Load more', 'kdna-events' ),
				'condition' => array( 'pagination' => 'load_more' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_card_elements',
			array(
				'label' => __( 'Card elements', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$toggles = array(
			'show_image'      => array( __( 'Image', 'kdna-events' ), 'yes' ),
			'show_type_badge' => array( __( 'Type badge', 'kdna-events' ), 'yes' ),
			'show_date'       => array( __( 'Date', 'kdna-events' ), 'yes' ),
			'show_title'      => array( __( 'Title', 'kdna-events' ), 'yes' ),
			'show_location'   => array( __( 'Location', 'kdna-events' ), 'yes' ),
			'show_price'      => array( __( 'Price', 'kdna-events' ), 'yes' ),
			'show_excerpt'    => array( __( 'Excerpt', 'kdna-events' ), 'yes' ),
			'show_button'     => array( __( 'Register button', 'kdna-events' ), 'yes' ),
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

		$this->add_control(
			'title_tag',
			array(
				'label'     => __( 'Title HTML tag', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'h3',
				'options'   => array(
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'div'  => 'div',
					'span' => 'span',
				),
				'condition' => array( 'show_title' => 'yes' ),
			)
		);

		$this->add_control(
			'excerpt_length',
			array(
				'label'     => __( 'Excerpt length (words)', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'min'       => 4,
				'max'       => 120,
				'default'   => 20,
				'condition' => array( 'show_excerpt' => 'yes' ),
			)
		);

		$this->add_control(
			'date_format',
			array(
				'label'     => __( 'Date format', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'j M Y, g:i a',
				'condition' => array( 'show_date' => 'yes' ),
			)
		);

		$this->add_control(
			'button_label',
			array(
				'label'     => __( 'Button label', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'View event', 'kdna-events' ),
				'condition' => array( 'show_button' => 'yes' ),
			)
		);

		$this->add_control(
			'free_label',
			array(
				'label'     => __( 'Free label', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Free', 'kdna-events' ),
				'condition' => array( 'show_price' => 'yes' ),
			)
		);

		$this->end_controls_section();

		$this->register_card_style_controls();
		$this->register_image_style_controls();
		$this->register_text_style_controls();
		$this->register_type_badge_style_controls();
		$this->register_button_style_controls();
		$this->register_pagination_style_controls();
	}

	/**
	 * Register Card appearance controls (background, border, shadow, hover lift).
	 *
	 * @return void
	 */
	protected function register_card_style_controls() {
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
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-grid__card' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .kdna-events-grid__card',
			)
		);

		$this->add_control(
			'card_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'default'    => array(
					'top'      => 12,
					'right'    => 12,
					'bottom'   => 12,
					'left'     => 12,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-grid__card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
				),
			)
		);

		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Body padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 16,
					'right'    => 16,
					'bottom'   => 16,
					'left'     => 16,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-grid__card-body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_shadow',
				'selector' => '{{WRAPPER}} .kdna-events-grid__card',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_hover_shadow',
				'label'    => __( 'Hover shadow', 'kdna-events' ),
				'selector' => '{{WRAPPER}} .kdna-events-grid__card:hover',
			)
		);

		$this->add_control(
			'card_hover_lift',
			array(
				'label'      => __( 'Hover lift', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 24,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 4,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-grid__card:hover' => 'transform: translateY(-{{SIZE}}{{UNIT}});',
				),
			)
		);

		$this->register_hover_transition_control( 'card_transition', '.kdna-events-grid__card', 200 );

		$this->end_controls_section();
	}

	/**
	 * Register Image controls on the card (aspect ratio, radius, hover zoom).
	 *
	 * @return void
	 */
	protected function register_image_style_controls() {
		$this->start_controls_section(
			'section_style_image',
			array(
				'label'     => __( 'Image', 'kdna-events' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_image' => 'yes' ),
			)
		);

		$this->add_control(
			'image_aspect',
			array(
				'label'   => __( 'Aspect ratio', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '4-3',
				'options' => array(
					'1-1'    => __( '1:1 (square)', 'kdna-events' ),
					'4-3'    => __( '4:3', 'kdna-events' ),
					'16-9'   => __( '16:9', 'kdna-events' ),
					'3-4'    => __( '3:4 (portrait)', 'kdna-events' ),
					'custom' => __( 'Custom', 'kdna-events' ),
				),
			)
		);

		$this->add_responsive_control(
			'image_aspect_custom',
			array(
				'label'      => __( 'Custom aspect ratio', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( '%' ),
				'range'      => array(
					'%' => array(
						'min' => 20,
						'max' => 200,
					),
				),
				'default'    => array(
					'unit' => '%',
					'size' => 66,
				),
				'description' => __( 'Height as a percentage of width.', 'kdna-events' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-grid__card-image' => 'aspect-ratio: auto; padding-top: {{SIZE}}{{UNIT}}; height: 0;',
				),
				'condition'  => array( 'image_aspect' => 'custom' ),
			)
		);

		$this->add_control(
			'image_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-grid__card-image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
				),
			)
		);

		$this->add_control(
			'image_hover_zoom',
			array(
				'label'      => __( 'Hover zoom', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( '%' ),
				'range'      => array(
					'%' => array(
						'min' => 100,
						'max' => 140,
					),
				),
				'default'    => array(
					'unit' => '%',
					'size' => 105,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-grid__card:hover .kdna-events-grid__card-image-img' => 'transform: scale(calc({{SIZE}} / 100));',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register per-text-element style controls (date, title, location, price, excerpt).
	 *
	 * @return void
	 */
	protected function register_text_style_controls() {
		$elements = array(
			'date'     => array( __( 'Date', 'kdna-events' ), '.kdna-events-grid__card-date', 'show_date' ),
			'title'    => array( __( 'Title', 'kdna-events' ), '.kdna-events-grid__card-title, {{WRAPPER}} .kdna-events-grid__card-title-link', 'show_title' ),
			'location' => array( __( 'Location', 'kdna-events' ), '.kdna-events-grid__card-location', 'show_location' ),
			'price'    => array( __( 'Price', 'kdna-events' ), '.kdna-events-grid__card-price', 'show_price' ),
			'excerpt'  => array( __( 'Excerpt', 'kdna-events' ), '.kdna-events-grid__card-excerpt', 'show_excerpt' ),
		);

		foreach ( $elements as $key => $info ) {
			$this->start_controls_section(
				'section_style_' . $key,
				array(
					'label'     => $info[0],
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( $info[2] => 'yes' ),
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
							'max' => 64,
						),
						'em' => array(
							'min'  => 0,
							'max'  => 4,
							'step' => 0.1,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}} ' . explode( ',', $info[1] )[0] => 'margin-bottom: {{SIZE}}{{UNIT}};',
					),
				)
			);

			$this->end_controls_section();
		}
	}

	/**
	 * Register Type Badge styling on the card, mirroring the standalone widget.
	 *
	 * @return void
	 */
	protected function register_type_badge_style_controls() {
		$this->start_controls_section(
			'section_style_type_badge',
			array(
				'label'     => __( 'Card type badge', 'kdna-events' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_type_badge' => 'yes' ),
			)
		);

		$types = array(
			'in-person' => array( __( 'In-person', 'kdna-events' ), '#ecfdf5', '#065f46' ),
			'virtual'   => array( __( 'Virtual', 'kdna-events' ), '#eff6ff', '#1e3a8a' ),
			'hybrid'    => array( __( 'Hybrid', 'kdna-events' ), '#fef3c7', '#92400e' ),
		);

		foreach ( $types as $key => $info ) {
			$this->add_control(
				'card_type_' . str_replace( '-', '_', $key ) . '_bg',
				array(
					'label'     => sprintf( /* translators: %s: type label */ __( '%s background', 'kdna-events' ), $info[0] ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'default'   => $info[1],
					'selectors' => array(
						'{{WRAPPER}} .kdna-events-grid__card .kdna-events-event-type-badge--' . $key => 'background-color: {{VALUE}};',
					),
				)
			);
			$this->add_control(
				'card_type_' . str_replace( '-', '_', $key ) . '_fg',
				array(
					'label'     => sprintf( /* translators: %s: type label */ __( '%s text', 'kdna-events' ), $info[0] ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'default'   => $info[2],
					'selectors' => array(
						'{{WRAPPER}} .kdna-events-grid__card .kdna-events-event-type-badge--' . $key => 'color: {{VALUE}};',
					),
				)
			);
		}

		$this->end_controls_section();
	}

	/**
	 * Register full Register Button styling on the card per Section 2.
	 *
	 * @return void
	 */
	protected function register_button_style_controls() {
		$this->start_controls_section(
			'section_style_button',
			array(
				'label'     => __( 'Card register button', 'kdna-events' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_button' => 'yes' ),
			)
		);

		$this->register_typography_control( 'card_button_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-grid__card-button' );

		$this->add_responsive_control(
			'card_button_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 0.55,
					'right'    => 1,
					'bottom'   => 0.55,
					'left'     => 1,
					'unit'     => 'em',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-grid__card-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'card_button_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-grid__card-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->register_hover_transition_control( 'card_button_transition', '.kdna-events-grid__card-button', 200 );

		$this->start_controls_tabs( 'card_button_state' );

		$this->start_controls_tab( 'card_button_normal', array( 'label' => __( 'Normal', 'kdna-events' ) ) );

		$this->add_control(
			'card_button_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-grid__card-button' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'card_button_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-grid__card-button' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'card_button_border',
				'selector' => '{{WRAPPER}} .kdna-events-grid__card-button',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'card_button_hover', array( 'label' => __( 'Hover', 'kdna-events' ) ) );

		$this->add_control(
			'card_button_hover_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-grid__card-button:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'card_button_hover_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-grid__card-button:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'card_button_hover_border',
			array(
				'label'     => __( 'Border colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-grid__card-button:hover' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Register Pagination styling: current / hover / disabled states.
	 *
	 * @return void
	 */
	protected function register_pagination_style_controls() {
		$this->start_controls_section(
			'section_style_pagination',
			array(
				'label'     => __( 'Pagination', 'kdna-events' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'pagination!' => 'none' ),
			)
		);

		$this->register_typography_control( 'pagination_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-grid__page, {{WRAPPER}} .kdna-events-grid__load-more' );

		$this->add_responsive_control(
			'pagination_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 0.4,
					'right'    => 0.75,
					'bottom'   => 0.4,
					'left'     => 0.75,
					'unit'     => 'em',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-grid__page, {{WRAPPER}} .kdna-events-grid__load-more' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'pagination_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-grid__page, {{WRAPPER}} .kdna-events-grid__load-more' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->start_controls_tabs( 'pagination_state' );

		$this->start_controls_tab( 'pagination_normal', array( 'label' => __( 'Normal', 'kdna-events' ) ) );
		$this->add_control(
			'pagination_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-grid__page, {{WRAPPER}} .kdna-events-grid__load-more' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'pagination_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-grid__page, {{WRAPPER}} .kdna-events-grid__load-more' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'pagination_hover', array( 'label' => __( 'Hover', 'kdna-events' ) ) );
		$this->add_control(
			'pagination_hover_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-grid__page:hover, {{WRAPPER}} .kdna-events-grid__load-more:hover' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'pagination_hover_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-grid__page:hover, {{WRAPPER}} .kdna-events-grid__load-more:hover' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'pagination_current', array( 'label' => __( 'Current', 'kdna-events' ) ) );
		$this->add_control(
			'pagination_current_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-grid__page.is-current' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'pagination_current_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-grid__page.is-current' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Render the grid wrapper, initial card set and pagination.
	 *
	 * @return void
	 */
	protected function render() {
		$settings      = $this->get_settings_for_display();
		$grid_settings = KDNA_Events_Grid::sanitize_grid_settings(
			array(
				'posts_per_page'  => (int) ( $settings['posts_per_page'] ?? 9 ),
				'upcoming_only'   => (string) ( $settings['upcoming_only'] ?? 'yes' ),
				'include_past'    => (string) ( $settings['include_past'] ?? '' ),
				'category'        => (int) ( $settings['category'] ?? 0 ),
				'order_by'        => (string) ( $settings['order_by'] ?? 'start' ),
				'order'           => (string) ( $settings['order'] ?? 'ASC' ),
				'pagination'      => (string) ( $settings['pagination'] ?? 'numbered' ),
				'title_tag'       => (string) ( $settings['title_tag'] ?? 'h3' ),
				'excerpt_length'  => (int) ( $settings['excerpt_length'] ?? 20 ),
				'date_format'     => (string) ( $settings['date_format'] ?? 'j M Y, g:i a' ),
				'button_label'    => (string) ( $settings['button_label'] ?? __( 'View event', 'kdna-events' ) ),
				'free_label'      => (string) ( $settings['free_label'] ?? __( 'Free', 'kdna-events' ) ),
				'load_more_label' => (string) ( $settings['load_more_label'] ?? __( 'Load more', 'kdna-events' ) ),
				'elements'        => array(
					'image'      => (string) ( $settings['show_image'] ?? 'yes' ),
					'type_badge' => (string) ( $settings['show_type_badge'] ?? 'yes' ),
					'date'       => (string) ( $settings['show_date'] ?? 'yes' ),
					'title'      => (string) ( $settings['show_title'] ?? 'yes' ),
					'location'   => (string) ( $settings['show_location'] ?? 'yes' ),
					'price'      => (string) ( $settings['show_price'] ?? 'yes' ),
					'excerpt'    => (string) ( $settings['show_excerpt'] ?? 'yes' ),
					'button'     => (string) ( $settings['show_button'] ?? 'yes' ),
				),
			)
		);

		$current_page = max( 1, absint( get_query_var( 'paged' ) ?: ( $_GET['kdna_page'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$query_args = KDNA_Events_Grid::build_query_args(
			$grid_settings,
			array(
				'page' => $current_page,
			)
		);
		$query      = new WP_Query( $query_args );

		$aspect_class = 'kdna-events-grid--aspect-' . sanitize_html_class( (string) ( $settings['image_aspect'] ?? '4-3' ) );

		$data_settings = wp_json_encode( $grid_settings );
		?>
		<div
			class="kdna-events-grid <?php echo esc_attr( $aspect_class ); ?>"
			data-kdna-events-grid="1"
			data-grid-settings="<?php echo esc_attr( (string) $data_settings ); ?>"
		>
			<div class="kdna-events-grid__wrapper">
				<?php
				if ( $query->have_posts() ) {
					echo KDNA_Events_Grid::render_cards_for_query( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						$query,
						array(
							'elements'       => $grid_settings['elements'],
							'title_tag'      => $grid_settings['title_tag'],
							'excerpt_length' => $grid_settings['excerpt_length'],
							'date_format'    => $grid_settings['date_format'],
							'button_label'   => $grid_settings['button_label'],
							'free_label'     => $grid_settings['free_label'],
						)
					);
				} else {
					echo KDNA_Events_Grid::render_empty_state(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>
			<?php
			echo KDNA_Events_Grid::render_pagination( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$query,
				array(
					'type'            => $grid_settings['pagination'],
					'current'         => $current_page,
					'load_more_label' => $grid_settings['load_more_label'],
				)
			);
			?>
		</div>
		<?php
	}
}
