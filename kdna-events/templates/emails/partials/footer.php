<?php
/**
 * Email footer block, lives on the grey page background BELOW the
 * white content card per the PDF. Receives pre-resolved footer text
 * and the business name, both already merge-tag replaced.
 *
 * Expects:
 *   $footer_text        Plain text, possibly multi-line.
 *   $footer_business    Business name (optional).
 *   $body_stack         Inline font-family stack.
 *   $muted_color        Hex text colour.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$footer_text     = (string) $footer_text;
$footer_business = (string) $footer_business;

if ( '' === $footer_text && '' === $footer_business ) {
	return;
}

$paragraphs = preg_split( '/\r\n|\n|\r/', $footer_text );
?>
<tr>
	<td class="kdna-events-email-footer" align="center" style="padding:24px 24px 40px;text-align:center;font-family:<?php echo esc_attr( $body_stack ); ?>;font-size:13px;line-height:1.6;color:<?php echo esc_attr( $muted_color ); ?>;mso-line-height-rule:exactly;">
		<?php if ( '' !== $footer_business ) : ?>
			<p style="margin:0 0 6px;font-weight:600;color:<?php echo esc_attr( $muted_color ); ?>;"><?php echo esc_html( $footer_business ); ?></p>
		<?php endif; ?>
		<?php foreach ( $paragraphs as $line ) :
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			?>
			<p style="margin:0 0 6px;color:<?php echo esc_attr( $muted_color ); ?>;"><?php echo esc_html( $line ); ?></p>
		<?php endforeach; ?>
	</td>
</tr>
