<?php
/**
 * Gestiona las solicitudes Menu Page para el usuario de administración.
 */

namespace Fw\Core\Request;

use Fw\Core\Response\Response;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RequestMenuPage extends Request
{
	private int $failureStatus = 404;

	private string $failureMessage = 'La acción no está disponible.';

	/**
	 * Se valida y ejecuta la solicitud para las (Sub) Menu Page.
	 */
	public function send(): ?string
	{
		try {
			$callback = $this->validations();
			if ( ! $callback ) {
				wp_die( esc_html( $this->failureMessage ), '', array( 'response' => $this->failureStatus ) );
				return null;
			}

			$response = $this->invoke( $callback, array( $this->params() ) );
			if ( $response instanceof Response ) {
				$response->send( $this->pluginPath );
			}
		} catch ( Throwable $e ) {
			$message = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
				? $e->getMessage()
				: 'No se pudo completar la acción.';
			wp_die( esc_html( $message ), '', array( 'response' => 500 ) );
		}

		return null;
	}

	/**
	 * Resuelve el método (option o el de la Menu Page) y aplica, en este orden:
	 *
	 * 1. Solo métodos public (sin lista blanca). private/protected/mágicos caen en error404.
	 * 2. Capability del método: $capabilities[metodo] o, si no aparece, $capability
	 *    (la misma que usa add_menu_page). Sin permiso: error404 del controlador o 403.
	 * 3. Nonce solo si el controlador lo pide en $nonce (opt-in, los enlaces viejos siguen sirviendo).
	 *
	 * @return array{0: object, 1: string}|null
	 */
	public function validations(): ?array
	{
		$controller = $this->getController();
		if ( ! class_exists( $controller ) ) {
			$this->deny( 404, 'La acción no está disponible.' );
			return null;
		}

		$requested = $this->getMethod();
		$option    = null;
		if ( isset( $_GET['option'] ) && is_string( $_GET['option'] ) && $_GET['option'] !== '' ) {
			$option    = wp_unslash( $_GET['option'] );
			$requested = $option;
		}

		$method = Actions::resolve( $controller, $requested );
		if ( $method === null ) {
			$this->deny( 404, 'La acción no está disponible.' );
			return null;
		}

		if ( ! current_user_can( Actions::capabilityFor( $controller, $method ) ) ) {
			// error404 solo si el usuario sí puede verlo (capability general del controlador).
			if (
				$method !== 'error404'
				&& Actions::isPublicAction( $controller, 'error404' )
				&& current_user_can( Actions::capabilityFor( $controller, 'error404' ) )
			) {
				$method = 'error404';
			} else {
				$this->deny( 403, 'No tienes permiso para ejecutar esta acción.' );
				return null;
			}
		}

		if ( $option !== null && $method !== 'error404' && Actions::requiresNonce( $controller, $method ) ) {
			$page = AdminActions::currentPage();
			if ( $page === null || ! AdminActions::verify( $page, $method ) ) {
				$this->deny( 403, 'El enlace caducó o no es válido. Vuelve a la página e inténtalo de nuevo.' );
				return null;
			}
		}

		$this->method = $method;

		return array(
			new $controller(),
			$method,
		);
	}

	/**
	 * Parámetros a enviar a las funciones Menu Page.
	 *
	 * @return array{baseUrl: string}
	 */
	public function params(): array
	{
		$baseUrl = explode( '\\', $this->getController() );
		array_pop( $baseUrl );
		$baseUrl = array_shift( $baseUrl ) . '-' . array_pop( $baseUrl );
		$baseUrl = strToSlug( $baseUrl );

		return compact( 'baseUrl' );
	}

	private function deny( int $status, string $message ): void
	{
		$this->failureStatus  = $status;
		$this->failureMessage = $message;
	}
}
