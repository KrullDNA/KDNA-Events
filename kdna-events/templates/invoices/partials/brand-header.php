<?php
/**
 * Invoice brand header. Logo left, 'Tax Invoice' heading right.
 *
 * Expects:
 *   $design  Resolved design tokens.
 *   $heading Document heading string.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="inv-header" role="presentation" cellpadding="0" cellspacing="0" border="0">
	<tr>
		<td style="width:50%;vertical-align:middle;">
			<?php if ( ! empty( $design['logo_url'] ) ) : ?>
				<img class="inv-header__logo" src="<?php echo esc_url( $design['logo_url'] ); ?>" alt="<?php echo esc_attr( (string) get_bloginfo( 'name' ) ); ?>" width="<?php echo esc_attr( (string) $design['logo_width'] ); ?>" style="max-width:<?php echo esc_attr( (string) $design['logo_width'] ); ?>px;" />
			<?php endif; ?>
		</td>
		<td class="inv-header__title" style="width:50%;vertical-align:middle;text-align:right;">
			<?php echo esc_html( (string) $heading ); ?>
		</td>
	</tr>
</table>
