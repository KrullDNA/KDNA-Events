<?php
/**
 * Fixed page footer shown on every page.
 *
 * Expects: $data.
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$design = (array) $data['design'];
if ( empty( $design['show_footer'] ) ) {
	return;
}

$biz = trim( (string) $design['business_name'] );
$web = trim( (string) $design['website_url'] );
$em  = trim( (string) $design['support_email'] );
$ph  = trim( (string) $design['support_phone'] );
$ts  = ! empty( $design['show_timestamp'] );
?>
<div class="tkt-footer">
	<?php if ( '' !== $biz ) : ?>
		<p><strong><?php echo esc_html( $biz ); ?></strong><?php if ( '' !== $web ) : ?> &nbsp;|&nbsp; <?php echo esc_html( $web ); ?><?php endif; ?></p>
	<?php endif; ?>
	<?php if ( '' !== $em || '' !== $ph ) : ?>
		<p>
			<?php if ( '' !== $em ) : ?><strong>E:</strong> <?php echo esc_html( $em ); ?><?php endif; ?>
			<?php if ( '' !== $em && '' !== $ph ) : ?> &nbsp;|&nbsp; <?php endif; ?>
			<?php if ( '' !== $ph ) : ?><strong>T:</strong> <?php echo esc_html( $ph ); ?><?php endif; ?>
		</p>
	<?php endif; ?>
	<?php if ( $ts ) : ?>
		<p><?php printf( esc_html__( 'Generated %s', 'kdna-events-pdf-tickets' ), esc_html( date_i18n( 'j M Y' ) ) ); ?></p>
	<?php endif; ?>
</div>
