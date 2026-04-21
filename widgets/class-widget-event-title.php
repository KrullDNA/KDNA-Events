<?php
/**
 * Event Title widget.
 *
 * Displays the current event's post title. Supports HTML tag selection,
 * optional link to the single event, alignment, and the full typography
 * styling surface.
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
 * Elementor widget rendering the event title.
 */
class KDNA_Events_Widget_Event_Title extends KDNA_Events_Widget_Base {

	/**
	 * Machine name used by Elementor.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-event-title';
	}

	/**
	 * Visible widget title in the editor.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Event Title', 'kdna-events' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-post-title';
	}

	/**
	 * Register content and style controls.
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
			'html_tag',
			array(
				'label'   => __( 'HTML tag', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'h1',
				'options' => array(
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'div'  => 'div',
					'span' => 'span',
				),
			)
		);

		$this->add_control(
			'link_to_single',
			array(
				'label'        => __( 'Link to event', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'kdna-events' ),
				'label_off'    => __( 'No', 'kdna-events' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'Alignment', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => __( 'Left', 'kdna-events' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Centre', 'kdna-events' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Right', 'kdna-events' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-title' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Title', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-title, {{WRAPPER}} .kdna-events-event-title__link' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'hover_color',
			array(
				'label'     => __( 'Link hover colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-title__link:hover' => 'color: {{VALUE}};',
				),
				'condition' => array(
					'link_to_single' => 'yes',
				),
			)
		);

		$this->register_typography_control( 'typography', __( 'Typography', 'kdna-events' ), '.kdna-events-event-title' );

		$this->add_group_control(
			\Elementor\Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'text_shadow',
				'selector' => '{{WRAPPER}} .kdna-events-event-title',
			)
		);

		$this->register_spacing_controls( 'wrapper', '.kdna-events-event-title' );

		$this->end_controls_section();
	}

	/**
	 * Render the widget on the front end.
	 *
	 * @return void
	 */
	protected function render() {
		$event_id = $this->get_event_id();
		if ( ! $event_id ) {
			$this->render_editor_placeholder( __( 'Create an event to preview the title.', 'kdna-events' ) );
			return;
		}

		$settings = $this->get_settings_for_display();
		$title    = get_the_title( $event_id );

		if ( '' === $title ) {
			$this->render_editor_placeholder( __( 'Event title is empty.', 'kdna-events' ) );
			return;
		}

		$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span' );
		$tag          = isset( $settings['html_tag'] ) && in_array( $settings['html_tag'], $allowed_tags, true ) ? $settings['html_tag'] : 'h1';
		$link         = isset( $settings['link_to_single'] ) && 'yes' === $settings['link_to_single'];

		printf( '<%1$s class="kdna-events-event-title">', esc_attr( $tag ) );
		if ( $link ) {
			printf(
				'<a class="kdna-events-event-title__link" href="%1$s">%2$s</a>',
				esc_url( get_permalink( $event_id ) ),
				esc_html( $title )
			);
		} else {
			echo esc_html( $title );
		}
		printf( '</%1$s>', esc_attr( $tag ) );
	}
}
