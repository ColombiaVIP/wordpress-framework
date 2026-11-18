<?php
/**
 * Superficie mínima de WordPress para las pruebas del framework.
 * Las funciones que el test debe observar (wp_die, current_user_can, JSON)
 * no se definen aquí: las aporta Brain Monkey en cada caso.
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WPFW_VERSION', '1.4.0' );
define( 'WP_DEBUG', false );
define( 'WP_PLUGIN_DIR', sys_get_temp_dir() );

if ( ! class_exists( 'WP' ) ) {
	class WP {
		public array $query_vars = array();
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public function __construct( private string $code = '', private string $message = '' ) {
		}

		public function get_error_codes(): array {
			return array( $this->code );
		}
	}
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	class WP_List_Table {
		public array $items = array();

		public array $_column_headers = array();

		public function __construct( array $args = array() ) {
		}

		public function row_actions( array $actions, bool $always_visible = false ): string {
			return implode( ' ', $actions );
		}

		public function get_pagenum(): int {
			return 1;
		}

		public function set_pagination_args( array $args ): void {
		}

		public function display(): void {
		}

		public function search_box( string $text, string $input_id ): void {
		}
	}
}

require dirname( __DIR__ ) . '/vendor/autoload.php';
require __DIR__ . '/FrameworkTestCase.php';

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ): string {
		$url = (string) $url;
		if ( preg_match( '/^\s*javascript:/i', $url ) === 1 ) {
			return '';
		}

		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ): string {
		$value = strip_tags( (string) $value );
		return trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', $value ) );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ): string {
		$key = strtolower( (string) $key );
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ): string {
		$title = strtolower( strip_tags( (string) $title ) );
		$title = (string) preg_replace( '/[^a-z0-9_\-]+/', '-', $title );
		return trim( $title, '-' );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
		return json_encode( $value, $flags, $depth );
	}
}
if ( ! function_exists( 'remove_accents' ) ) {
	function remove_accents( $text ) {
		return $text;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ): string {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url = '' ): string {
		if ( ! is_array( $args ) ) {
			return (string) $url;
		}
		$query = http_build_query( $args );
		$separator = str_contains( (string) $url, '?' ) ? '&' : '?';
		return (string) $url . ( $query === '' ? '' : $separator . $query );
	}
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action ): string {
		return 'nonce-' . $action;
	}
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action ) {
		return (string) $nonce === 'nonce-' . $action;
	}
}
if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $url, $action, $name = '_wpnonce' ): string {
		return add_query_arg( array( $name => wp_create_nonce( $action ) ), $url );
	}
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $display = true ): string {
		$field = '<input type="hidden" name="' . esc_attr( (string) $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />';
		if ( $display ) {
			echo $field;
		}
		return $field;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['wpfw_test_actions'][ $hook ][] = $callback;
		return true;
	}
}
if ( ! function_exists( 'add_rewrite_tag' ) ) {
	function add_rewrite_tag( $tag, $regex ): void {
		$GLOBALS['wpfw_test_tags'][ $tag ] = $regex;
	}
}
if ( ! function_exists( 'add_rewrite_rule' ) ) {
	function add_rewrite_rule( $regex, $query, $priority = 'bottom' ): void {
		$GLOBALS['wpfw_test_rules'][] = array(
			'regex'    => $regex,
			'query'    => $query,
			'priority' => $priority,
		);
	}
}
if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ): void {
		$GLOBALS['wpfw_test_activation'][ $file ][] = $callback;
	}
}
if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules( $hard = true ): void {
		$GLOBALS['wpfw_test_flushes'] = ( $GLOBALS['wpfw_test_flushes'] ?? 0 ) + 1;
	}
}
if ( ! function_exists( 'get_query_var' ) ) {
	function get_query_var( $variable, $default = '' ) {
		return $GLOBALS['wpfw_test_query_vars'][ $variable ] ?? $default;
	}
}
if ( ! function_exists( 'status_header' ) ) {
	function status_header( $code ): void {
		$GLOBALS['wpfw_test_status'][] = $code;
	}
}
if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers(): void {
		$GLOBALS['wpfw_test_nocache'] = true;
	}
}
if ( ! function_exists( 'get_the_block_template_html' ) ) {
	function get_the_block_template_html(): string {
		$GLOBALS['wpfw_test_block_template'] = ( $GLOBALS['wpfw_test_block_template'] ?? 0 ) + 1;
		return '';
	}
}
if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( $path = '', $plugin = '' ): string {
		return 'https://example.test/wp-content/plugins' . (string) $path;
	}
}
if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ): void {
		$GLOBALS['wpfw_test_scripts'][] = compact( 'handle', 'src' );
	}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle ): void {
	}
}
if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $object_name, $data ): void {
	}
}
if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( $handle, $src = '', $deps = array(), $ver = false ): void {
		$GLOBALS['wpfw_test_styles'][] = compact( 'handle', 'src' );
	}
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle ): void {
	}
}
if ( ! function_exists( 'wp_enqueue_media' ) ) {
	function wp_enqueue_media(): void {
	}
}
if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( ...$args ) {
		$GLOBALS['wpfw_test_menus'][] = $args;
		return 'menu';
	}
}
if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( ...$args ) {
		$GLOBALS['wpfw_test_submenus'][] = $args;
		return 'submenu';
	}
}
if ( ! function_exists( 'site_url' ) ) {
	function site_url( $path = '' ): string {
		return 'https://example.test/' . ltrim( (string) $path, '/' );
	}
}
if ( ! function_exists( 'menu_page_url' ) ) {
	function menu_page_url( $page, $echo = true ): string {
		$url = 'https://example.test/wp-admin/admin.php?page=' . $page;
		if ( $echo ) {
			echo $url;
		}
		return $url;
	}
}
if ( ! function_exists( 'get_admin_page_title' ) ) {
	function get_admin_page_title(): string {
		return 'Título';
	}
}
