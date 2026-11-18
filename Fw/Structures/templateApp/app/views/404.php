<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpfw-error-404">
    <div class="wpfw-error-content">
        <h2>404</h2>
    
        <p>
            Sorry, the page you are looking for could not be found.
        </p>
    
        <div class="wpfw-error-link">
            <a href="<?php echo esc_url( (string) ( $backLink ?? '' ) ); ?>"><?php echo esc_html( (string) ( $backText ?? '' ) ); ?></a>
        </div>
    </div>
    
    <div class="wpfw-error-img">
        <img src="<?php echo esc_url( (string) asset( 'images/error404.svg', __FILE__ ) ); ?>" alt="Error 404">
    </div>
</div>
