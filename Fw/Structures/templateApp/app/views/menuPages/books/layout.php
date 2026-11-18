<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpfw-layout">
    <?php
    // Fragmento HTML ya renderizado por view(). Las vistas hoja escapan sus datos.
    echo $content ?? '';
    ?>
</div>
