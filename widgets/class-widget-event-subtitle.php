<?php
/**
 * Event Subtitle widget.
 *
 * Displays the standalone one-line subtitle from _kdna_event_subtitle.
 * Hides itself on the front end when empty, with an editor-only
 * 'show even if empty' switcher so authors still see a styling handle.
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
 * Elementor widget rendering the event subtitle.
 */
class KDNA_Events_Widget_Event_Subtitle extends KDNA_Events_Widget_Base {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-event-subtitle';
	}

	/**
	 * Visible title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Event Subtitle', 'kdna-events' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-heading';
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
			'html_tag',
			array(
				'label'   => __( 'HTML tag', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'p',
				'options' => array(
					'p'    => 'p',
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
					'{{WRAPPER}} .kdna-events-event-subtitle' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'show_if_empty',
			array(
				'label'        => __( 'Show even if empty (editor only)', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Renders a placeholder in the Elementor editor. Front-end output stays hidden when the subtitle is empty.', 'kdna-events' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Subtitle', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => __( 'Colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-subtitle' => 'color: {{VALUE}};',
				),
			)
		);

		$this->register_typography_control( 'typography', __( 'Typography', 'kdna-events' ), '.kdna-events-event-subtitle' );

		$this->register_spacing_controls( 'wrapper', '.kdna-events-event-subtitle' );

		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render() {
		$event_id = $this->get_event_id();
		$settings = $this->get_settings_for_display();

		$subtitle = '';
		if ( $event_id ) {
			$subtitle = (string) get_post_meta( $event_id, '_kdna_event_subtitle', true );
		}

		$show_if_empty_editor = isset( $settings['show_if_empty'] ) && 'yes' === $settings['show_if_empty'];

		if ( '' === $subtitle ) {
			if ( $this->is_editor_mode() && $show_if_empty_editor ) {
				$subtitle = __( 'Event subtitle preview', 'kdna-events' );
			} else {
				return;
			}
		}

		$allowed_tags = array( 'p', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span' );
		$tag          = isset( $settings['html_tag'] ) && in_array( $settings['html_tag'], $allowed_tags, true ) ? $settings['html_tag'] : 'p';

		printf(
			'<%1$s class="kdna-events-event-subtitle">%2$s</%1$s>',
			esc_attr( $tag ),
			esc_html( $subtitle )
		);
	}
}
