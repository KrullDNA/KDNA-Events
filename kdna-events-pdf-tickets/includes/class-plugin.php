<?php
/**
 * Main plugin class for the KDNA Events PDF Tickets add-on.
 *
 * Singleton. Bootstraps every subsystem exactly once after the core
 * dependency check passes.
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin lifecycle controller.
 */
class KDNA_Events_PDF_Plugin {

	/**
	 * @var KDNA_Events_PDF_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * @var bool
	 */
	protected $booted = false;

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
	 * Wire up subsystems. Safe to call multiple times.
	 *
	 * @return void
	 */
	public function init() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		KDNA_Events_PDF_Settings::instance()->init();
		KDNA_Events_PDF_Event_Meta::instance()->init();
		KDNA_Events_PDF_Email_Integration::instance()->init();
		KDNA_Events_PDF_Widget_Integration::instance()->init();
		KDNA_Events_PDF_REST::instance()->init();
		KDNA_Events_PDF_Temp_Cleanup::instance()->init();
	}
}
