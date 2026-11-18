<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
?>
<div class="wpfw-error-404">
    <div class="wpfw-error-content">
        <h2>404</h2>
    
        <p>
            Sorry, the page you are looking for could not be found.
        </p>
    
        <div class="wpfw-error-link">
            <a href="<?php echo esc_url( (string) menu_page_url( $page, false ) ); ?>">GO MENU PAGE</a>
        </div>
    </div>
    
    <div class="wpfw-error-img">
        <img src="<?php echo esc_url( (string) asset( 'images/error404.svg', __FILE__ ) ); ?>" alt="Error 404">
    </div>
</div>
