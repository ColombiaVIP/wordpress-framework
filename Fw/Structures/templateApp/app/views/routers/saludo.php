<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h1><?php echo esc_html( (string) ( $title ?? '' ) ); ?></h1>
<p><?php echo esc_html( (string) ( $message ?? '' ) ); ?></p>
