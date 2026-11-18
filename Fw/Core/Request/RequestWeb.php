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
	 * Comprobación isPublic (estaba comentada en 1.3.1).
	 *
	 * - Cualquier método public del controlador se ejecuta, sin declararlo en ninguna lista.
	 * - private, protected, constructor, destructor y nombres que empiezan por "__" nunca se ejecutan.
	 * - Un nombre que no existe lo atiende __call si el controlador lo tiene (como en 1.3.1).
	 * - Si no, error404() del controlador; y si no lo tiene, la plantilla 404 del tema.
	 *
	 * @return array{0: object, 1: string}|string|null
	 */
	public function validations(): array|string|null
	{
		$controller = $this->getController();
		$method     = Actions::resolve( $controller, $this->getMethod(), true );

		if ( $method === null ) {
			$template = ThemeNotFound::template();
			return $template !== '' ? $template : null;
		}

		$this->method = $method;

		return array(
			new $controller(),
			$method,
		);
	}
}
