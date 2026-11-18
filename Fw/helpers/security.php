<?php
/**
 * Atajos del contrato de seguridad para los plugins de aplicación.
 */

use Fw\Core\Request\AdminActions;
use Fw\Init\Ajax\AjaxManager;

// Este archivo lo incluye el autoload de Composer. exit solo si se abre directo.
if ( ! defined( 'ABSPATH' ) && isset( $_SERVER['SCRIPT_FILENAME'] ) && realpath( (string) $_SERVER['SCRIPT_FILENAME'] ) === __FILE__ ) {
	exit;
}

if ( ! function_exists( 'wpfw_admin_action_url' ) ) :
	/**
	 * URL de administración con page, option y nonce.
	 *
	 * @param array<string, scalar> $args
	 */
	function wpfw_admin_action_url( string $page, string $option, array $args = array() ): string
	{
		return AdminActions::url( $page, $option, $args );
	}
endif;

if ( ! function_exists( 'wpfw_admin_nonce_field' ) ) :
	/**
	 * Campo oculto de nonce para un formulario que envía option.
	 */
	function wpfw_admin_nonce_field( string $page, string $option ): string
	{
		return AdminActions::field( $page, $option );
	}
endif;

if ( ! function_exists( 'wpfw_ajax_hook' ) ) :
	/**
	 * Valor del parámetro action de admin-ajax.php.
	 */
	function wpfw_ajax_hook( string $pluginSlug, string $action ): string
	{
		return AjaxManager::hook( $pluginSlug, $action );
	}
endif;

if ( ! function_exists( 'wpfw_ajax_nonce' ) ) :
	/**
	 * Nonce de una acción AJAX. Se envía como _ajax_nonce.
	 */
	function wpfw_ajax_nonce( string $pluginSlug, string $action ): string
	{
		return wp_create_nonce( AjaxManager::nonceAction( $pluginSlug, $action ) );
	}
endif;

if ( ! function_exists( 'wpfw_ajax_client_config' ) ) :
	/**
	 * Mapa listo para wp_localize_script: url y, por cada acción del plugin que el
	 * usuario actual puede ejecutar (anónima o con su capability), action, nonce,
	 * anonimo y requiereNonce. Sirve igual para el modo automático (métodos public
	 * de app/Controllers/Ajax) y para los controladores con $ajaxActions.
	 *
	 * @return array{url: string, actions: array<string, array{action: string, nonce: string, anonimo: bool, requiereNonce: bool}>}
	 */
	function wpfw_ajax_client_config( string $pluginSlug ): array
	{
		return AjaxManager::clientConfig( $pluginSlug );
	}
endif;
