<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wp-shortcode-content">
    <?php foreach ( $categories as $category ) : ?>
        <div class="wp-shortcode-post">
            <a href="<?php echo esc_url( site_url( (string) $category->taxonomy . '/' . (string) $category->slug ) ); ?>">
                <h2><?php echo esc_html( (string) $category->name ); ?></h2>
            </a>
            <p class="excerpt"><?php echo esc_html( (string) $category->category_description ); ?></p>
        </div>
    <?php endforeach; ?>
</div>
