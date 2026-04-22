<?php
/**
 * Hidden preheader, surfaces in inbox list as preview text.
 *
 * Expects:
 *   $preheader_text Plain string.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( '' === (string) $preheader_text ) {
	return;
}
?>
<div class="kdna-events-email-preheader" style="display:none !important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;max-height:0;max-width:0;overflow:hidden;mso-hide:all;">
	<?php echo esc_html( (string) $preheader_text ); ?>
</div>
