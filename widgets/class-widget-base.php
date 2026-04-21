<?php
/**
 * Base class for every KDNA Events Elementor widget.
 *
 * Provides a consistent category, atomic-markup handling, and a small
 * set of shared helpers widgets can call to register typography,
 * spacing and hover-transition controls without duplicating boilerplate.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
	return;
}

/**
 * Abstract base for KDNA Events widgets.
 */
abstract class KDNA_Events_Widget_Base extends \Elementor\Widget_Base {

	/**
	 * Return the widget categories this widget belongs to.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'kdna-widgets' );
	}

	/**
	 * Return the keywords Elementor uses to search for the widget.
	 *
	 * Child classes can override to tune discoverability.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'kdna', 'events', 'event' );
	}

	/**
	 * Control whether Elementor wraps the widget in its inner container.
	 *
	 * When the e_optimized_markup experiment is active we skip the
	 * inner wrapper so our BEM wrapper div is the outermost element.
	 *
	 * @return bool
	 */
	public function has_widget_inner_wrapper(): bool {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return true;
		}

		$instance = \Elementor\Plugin::$instance;
		if ( isset( $instance->experiments ) && method_exists( $instance->experiments, 'is_feature_active' ) ) {
			return ! $instance->experiments->is_feature_active( 'e_optimized_markup' );
		}
		return true;
	}

	/**
	 * Register a Typography group control scoped to a selector.
	 *
	 * @param string $control_name Control name (e.g. 'title_typography').
	 * @param string $label        Visible label.
	 * @param string $selector     CSS selector relative to the widget wrapper.
	 * @return void
	 */
	protected function register_typography_control( $control_name, $label, $selector ) {
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => $control_name,
				'label'    => $label,
				'selector' => '{{WRAPPER}} ' . $selector,
			)
		);
	}

	/**
	 * Register standard spacing controls (margin, padding) for a selector.
	 *
	 * @param string $prefix   Prefix used to namespace the two controls.
	 * @param string $selector CSS selector relative to the widget wrapper.
	 * @return void
	 */
	protected function register_spacing_controls( $prefix, $selector ) {
		$this->add_responsive_control(
			$prefix . '_margin',
			array(
				'label'      => __( 'Margin', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} ' . $selector => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			$prefix . '_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} ' . $selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
	}

	/**
	 * Register a hover transition-duration slider control.
	 *
	 * Applies a CSS transition-duration on the given selector so child
	 * widgets can expose a single, tidy control for hover speed.
	 *
	 * @param string $control_name Control name (e.g. 'button_transition').
	 * @param string $selector     CSS selector to apply the transition to.
	 * @param int    $default_ms   Default duration in milliseconds.
	 * @return void
	 */
	protected function register_hover_transition_control( $control_name, $selector, $default_ms = 200 ) {
		$this->add_control(
			$control_name,
			array(
				'label'      => __( 'Transition duration', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'ms' ),
				'range'      => array(
					'ms' => array(
						'min'  => 0,
						'max'  => 1000,
						'step' => 10,
					),
				),
				'default'    => array(
					'unit' => 'ms',
					'size' => (int) $default_ms,
				),
				'selectors'  => array(
					'{{WRAPPER}} ' . $selector => 'transition-duration: {{SIZE}}{{UNIT}};',
				),
			)
		);
	}

	/**
	 * Resolve the event ID the widget should read from.
	 *
	 * Pulls from the current post when on a kdna_event single view and
	 * falls back to the most recent published event in the Elementor
	 * editor preview. Child widgets can override when checkout widgets
	 * want to read from a query parameter.
	 *
	 * @return int
	 */
	protected function get_event_id() {
		if ( is_singular( 'kdna_event' ) ) {
			return (int) get_queried_object_id();
		}

		$post_id = get_the_ID();
		if ( $post_id && 'kdna_event' === get_post_type( $post_id ) ) {
			return (int) $post_id;
		}

		$latest = get_posts(
			array(
				'post_type'      => 'kdna_event',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		return empty( $latest ) ? 0 : (int) $latest[0];
	}
}

/**
 * Widget loader.
 *
 * Registered at file load time per Section 2. Concrete widgets are
 * attached in subsequent stages by hooking 'kdna_events_register_widgets'
 * or by calling $widgets_manager->register() directly inside the action.
 */
add_action(
	'elementor/widgets/register',
	function ( $widgets_manager ) {
		/**
		 * Allow later stages to register concrete KDNA Events widgets.
		 *
		 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
		 */
		do_action( 'kdna_events_register_widgets', $widgets_manager );
	}
);
