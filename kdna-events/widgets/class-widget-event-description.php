<?php
/**
 * Event Description widget.
 *
 * Renders either the event post content or its excerpt. All text-level
 * styling is scoped to the widget wrapper class so nothing leaks into
 * the host theme.
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
 * Elementor widget rendering the event description.
 */
class KDNA_Events_Widget_Event_Description extends KDNA_Events_Widget_Base {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-event-description';
	}

	/**
	 * Visible title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Event Description', 'kdna-events' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-post-content';
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
			'source',
			array(
				'label'   => __( 'Source', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'content',
				'options' => array(
					'content' => __( 'Post content', 'kdna-events' ),
					'excerpt' => __( 'Excerpt', 'kdna-events' ),
				),
			)
		);

		$this->add_control(
			'show_read_more',
			array(
				'label'        => __( 'Show read more link', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'source' => 'excerpt' ),
			)
		);

		$this->add_control(
			'read_more_text',
			array(
				'label'     => __( 'Read more text', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Read more', 'kdna-events' ),
				'condition' => array(
					'source'         => 'excerpt',
					'show_read_more' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_paragraph_style',
			array(
				'label' => __( 'Paragraphs', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'paragraph_color',
			array(
				'label'     => __( 'Paragraph colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-description, {{WRAPPER}} .kdna-events-event-description p' => 'color: {{VALUE}};',
				),
			)
		);

		$this->register_typography_control( 'paragraph_typography', __( 'Paragraph typography', 'kdna-events' ), '.kdna-events-event-description, {{WRAPPER}} .kdna-events-event-description p' );

		$this->register_spacing_controls( 'wrapper', '.kdna-events-event-description' );

		$this->end_controls_section();

		$this->start_controls_section(
			'section_heading_style',
			array(
				'label' => __( 'Headings', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'heading_color',
			array(
				'label'     => __( 'Heading colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-description h1, {{WRAPPER}} .kdna-events-event-description h2, {{WRAPPER}} .kdna-events-event-description h3, {{WRAPPER}} .kdna-events-event-description h4, {{WRAPPER}} .kdna-events-event-description h5, {{WRAPPER}} .kdna-events-event-description h6' => 'color: {{VALUE}};',
				),
			)
		);

		$this->register_typography_control( 'heading_typography', __( 'Heading typography', 'kdna-events' ), '.kdna-events-event-description h1, {{WRAPPER}} .kdna-events-event-description h2, {{WRAPPER}} .kdna-events-event-description h3, {{WRAPPER}} .kdna-events-event-description h4, {{WRAPPER}} .kdna-events-event-description h5, {{WRAPPER}} .kdna-events-event-description h6' );

		$this->end_controls_section();

		$this->start_controls_section(
			'section_link_style',
			array(
				'label' => __( 'Links', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'link_color',
			array(
				'label'     => __( 'Link colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-description a' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'link_hover_color',
			array(
				'label'     => __( 'Link hover colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-description a:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_list_style',
			array(
				'label' => __( 'Lists', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'list_color',
			array(
				'label'     => __( 'List colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-description ul, {{WRAPPER}} .kdna-events-event-description ol, {{WRAPPER}} .kdna-events-event-description li' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'list_indent',
			array(
				'label'      => __( 'Indent', 'kdna-events' ),
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
					'unit' => 'em',
					'size' => 1.25,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-description ul, {{WRAPPER}} .kdna-events-event-description ol' => 'padding-inline-start: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_blockquote_style',
			array(
				'label' => __( 'Blockquote', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'blockquote_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-description blockquote' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'blockquote_background',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-description blockquote' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'blockquote_border',
				'selector' => '{{WRAPPER}} .kdna-events-event-description blockquote',
			)
		);

		$this->add_responsive_control(
			'blockquote_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-description blockquote' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
			$this->render_editor_placeholder( __( 'Create an event to preview the description.', 'kdna-events' ) );
			return;
		}

		$settings = $this->get_settings_for_display();
		$source   = $settings['source'] ?? 'content';

		echo '<div class="kdna-events-event-description">';

		if ( 'excerpt' === $source ) {
			$excerpt = get_the_excerpt( $event_id );
			if ( '' === $excerpt ) {
				$this->render_editor_placeholder( __( 'No excerpt set for this event.', 'kdna-events' ) );
			} else {
				echo '<p>' . esc_html( $excerpt ) . '</p>';

				if ( 'yes' === ( $settings['show_read_more'] ?? 'yes' ) ) {
					printf(
						'<p><a class="kdna-events-event-description__read-more" href="%1$s">%2$s</a></p>',
						esc_url( get_permalink( $event_id ) ),
						esc_html( (string) ( $settings['read_more_text'] ?? __( 'Read more', 'kdna-events' ) ) )
					);
				}
			}
		} else {
			$content = get_post_field( 'post_content', $event_id );
			if ( '' === trim( (string) $content ) ) {
				$this->render_editor_placeholder( __( 'No content set for this event.', 'kdna-events' ) );
			} else {
				echo apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		echo '</div>';
	}
}
