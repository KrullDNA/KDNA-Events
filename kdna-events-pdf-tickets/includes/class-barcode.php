<?php
/**
 * Code 128 barcode renderer for the PDF Tickets add-on.
 *
 * Thin wrapper over picqer/php-barcode-generator that returns a PNG
 * data URI at 2x pixel density for print sharpness.
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Barcode renderer.
 */
class KDNA_Events_PDF_Barcode {

	/**
	 * Render a Code 128 barcode as a data URI PNG.
	 *
	 * @param string $ticket_code The ticket code to encode.
	 * @param int    $width_px    Target width in pixels (at 96dpi).
	 * @param int    $height_px   Target height in pixels.
	 * @return string data:image/png;base64,... or empty string on failure.
	 */
	public static function render( $ticket_code, $width_px = 480, $height_px = 96 ) {
		$code = (string) $ticket_code;
		if ( '' === $code ) {
			return '';
		}

		if ( ! class_exists( '\\Picqer\\Barcode\\BarcodeGeneratorPNG' ) ) {
			return '';
		}

		try {
			$generator  = new \Picqer\Barcode\BarcodeGeneratorPNG();
			// Picqer draws each bar as $widthFactor pixels. For a
			// Code 128 encoding of an 8-char code the symbol is ~120
			// modules wide, so widthFactor 2 yields ~240px, 4 yields
			// ~480px. Double-density for print sharpness at 2x.
			$width_factor = max( 1, (int) round( $width_px / 120 ) );
			$binary       = $generator->getBarcode(
				$code,
				$generator::TYPE_CODE_128,
				$width_factor,
				max( 10, (int) $height_px )
			);
		} catch ( \Throwable $e ) {
			return '';
		}

		if ( ! is_string( $binary ) || '' === $binary ) {
			return '';
		}

		return 'data:image/png;base64,' . base64_encode( $binary );
	}
}
