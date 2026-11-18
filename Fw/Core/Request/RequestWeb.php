<?php
/**
 * Gestiona la solicitud Web para el usuario.
 */

namespace Fw\Core\Request;

use Fw\Core\Response\Response;
use Fw\Init\Routing\ThemeNotFound;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RequestWeb extends Request
{
	/**
	 * Valida y ejecuta la solicitud.
	 */
	public function send(): ?string
	{
		try {
			$callback = $this->validations();
			if ( is_string( $callback ) || $callback === null ) {
				ThemeNotFound::render();
				return null;
			}

			$response = call_user_func( $callback );
			if ( $response instanceof Response ) {
				$response->send( $this->pluginPath );
			}
		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				echo esc_html( $e->getMessage() );
			}
			ThemeNotFound::render();
		}

		return null;
	}

	/**
	 * Solo corre un método public que esté en la lista blanca.
	 * Si el método no está permitido y el controlador ofrece error404, se usa ese.
	 * Si no, se devuelve la plantilla 404 del tema.
	 *
	 * @return array{0: object, 1: string}|string|null
	 */
	public function validations(): array|string|null
	{
		$controller = $this->getController();
		$method     = Actions::sanitizeIdentifier( $this->getMethod() ) ?? '';

		if ( $method === '' || ! Actions::isAllowed( $controller, $method ) ) {
			if ( $method !== 'error404' && Actions::isAllowed( $controller, 'error404' ) ) {
				$method = 'error404';
			} else {
				$template = ThemeNotFound::template();
				return $template !== '' ? $template : null;
			}
		}

		return array(
			new $controller(),
			$method,
		);
	}
}
