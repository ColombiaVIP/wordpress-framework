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
	 * Capability, lista blanca y nonce cuando llega option.
	 *
	 * @return array{0: object, 1: string}|null
	 */
	public function validations(): ?array
	{
		$controller = $this->getController();
		$capability = self::controllerCapability( $controller );

		if ( ! current_user_can( $capability ) ) {
			$this->deny( 403, 'No tienes permiso para ejecutar esta acción.' );
			return null;
		}

		$method = Actions::sanitizeIdentifier( $this->getMethod() ) ?? 'index';
		$option = null;

		if ( isset( $_GET['option'] ) && is_string( $_GET['option'] ) && $_GET['option'] !== '' ) {
			$option = Actions::sanitizeIdentifier( wp_unslash( $_GET['option'] ) );
			if ( $option === null ) {
				$this->deny( 404, 'La acción no está disponible.' );
				return null;
			}
			$method = $option;
		}

		if ( ! Actions::isAllowed( $controller, $method ) ) {
			if ( $method !== 'error404' && Actions::isAllowed( $controller, 'error404' ) ) {
				$method = 'error404';
			} else {
				$this->deny( 404, 'La acción no está disponible.' );
				return null;
			}
		}

		if ( $option !== null ) {
			$page = AdminActions::currentPage();
			if ( $page === null || ! AdminActions::verify( $page, $option ) ) {
				$this->deny( 403, 'El nonce no es válido.' );
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
