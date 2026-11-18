<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wp-shortcode-content">
    <?php foreach ( $posts as $post ) : ?>
        <div class="wp-shortcode-post">
            <a href="<?php echo esc_url( (string) get_post_permalink( $post->ID ) ); ?>">
                <h2><?php echo esc_html( (string) $post->post_title ); ?></h2>
            </a>
            <p class="excerpt"><?php echo esc_html( (string) $post->post_excerpt ); ?></p>
            <p class="author"><strong>Author: </strong><?php echo esc_html( (string) get_the_author() ); ?></p>
        </div>
    <?php endforeach; ?>
</div>
