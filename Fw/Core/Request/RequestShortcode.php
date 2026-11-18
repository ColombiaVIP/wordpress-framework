<?php
/**
 * Gestiona las solicitudes y la creación de Shortcode.
 *
 * La categoría, la clase y el método tienen que ser identificadores.
 * El método, además, tiene que estar en la lista blanca del controlador.
 */

namespace Fw\Core\Request;

use Fw\Core\Exceptions\General;
use Fw\Core\Response\Response;
use Fw\Config\Apps;
use Fw\Paths;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RequestShortcode extends Request
{
	/** @var object $instance Instancia de la App actual. */
	private object $instance;

	/** @var string $category Categoría a la que pertenece el controlador a ejecutar. */
	private string $category = '';

	/** @var array $params Atributos del shortcode, sin la categoría. */
	private array $params = array();

	/** @var array $files Listado de archivos de los shortcodes. */
	private array $files = array();

	/**
	 * @param array  $attrs         Atributos del shortcode. El primero es Categoria=Clase@metodo.
	 * @param string $content       Contenido interno. No se usa para elegir el método.
	 * @param string $shortcodeTag  Tag registrado.
	 */
	public function prepare( array $attrs = array(), string $content = '', string $shortcodeTag = '' ): ?string
	{
		unset( $content, $shortcodeTag );

		$instance = Apps::getApp( $this->pluginPath );
		if ( ! is_object( $instance ) ) {
			return null;
		}
		$this->instance = $instance;

		try {
			$categoryKey = array_key_first( $attrs );
			if ( ! is_string( $categoryKey ) ) {
				throw new General( 'El shortcode no indica una categoría válida.', 404 );
			}

			$category = Actions::sanitizeIdentifier( $categoryKey );
			if ( $category === null ) {
				throw new General( 'El shortcode no indica una categoría válida.', 404 );
			}

			$actionRaw = $attrs[ $categoryKey ] ?? '';
			unset( $attrs[ $categoryKey ] );

			$params = array();
			foreach ( $attrs as $key => $value ) {
				if ( ! is_string( $key ) || is_array( $value ) || is_object( $value ) ) {
					continue;
				}
				if ( Actions::sanitizeIdentifier( $key ) === null ) {
					continue;
				}
				$params[ $key ] = (string) $value;
			}

			$actionRaw = is_string( $actionRaw ) ? sanitize_text_field( $actionRaw ) : '';
			if ( $actionRaw === '' ) {
				throw new General( 'El shortcode requiere un controlador.', 404 );
			}

			$action = explode( '@', $actionRaw, 2 );
			$class  = Actions::sanitizeIdentifier( $action[0] );
			if ( $class === null ) {
				throw new General( 'El controlador del shortcode no es válido.', 404 );
			}

			$method = 'index';
			if ( isset( $action[1] ) && $action[1] !== '' ) {
				$parsed = Actions::sanitizeIdentifier( $action[1] );
				if ( $parsed === null ) {
					throw new General( 'El método del shortcode no es válido.', 404 );
				}
				$method = $parsed;
			}

			$this->category = $category;
			$this->params   = $params;
			$this->method   = $method;

			$path        = Paths::buildPath(
				$this->instance->paths->controllers->shortcodes,
				$this->category,
				'*.php'
			);
			$found       = glob( $path );
			$this->files = is_array( $found ) ? $found : array();

			$this->controller = Paths::buildNamespacePath(
				$this->instance->config->namespace,
				'Controllers',
				'Shortcodes',
				$this->category,
				$class
			);

			if ( class_exists( $this->controller . 'Controller' ) ) {
				$this->controller .= 'Controller';
			} elseif ( ! class_exists( $this->controller ) ) {
				throw new General( 'No se encontró el controlador del shortcode.', 404 );
			}

			ob_start();
			$this->send();
			return ob_get_clean();
		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				return esc_html( $e->getMessage() );
			}

			return null;
		}
	}

	public function send(): ?string
	{
		try {
			if ( ! $this->isAllowedFile() ) {
				throw new General( 'El shortcode no puede usar ese controlador.', 404 );
			}

			$callback = $this->validations();
			if ( ! $callback ) {
				throw new General( 'El método del shortcode no está permitido.', 404 );
			}

			$response = $this->invoke( $callback, array( $this->params ) );
			if ( $response instanceof Response ) {
				$response->send( $this->instance->paths->app );
			}
		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				echo esc_html( $e->getMessage() );
			}
		}

		return null;
	}

	/**
	 * @return array{0: object, 1: string}|null
	 */
	public function validations(): ?array
	{
		$controller = $this->getController();
		$method     = Actions::sanitizeIdentifier( $this->getMethod() ) ?? '';

		if ( $method === '' || ! Actions::isAllowed( $controller, $method ) ) {
			if ( $method !== 'error404' && Actions::isAllowed( $controller, 'error404' ) ) {
				$method = 'error404';
			} else {
				return null;
			}
		}

		$this->method = $method;

		return array(
			new $controller( $this->params ),
			$method,
		);
	}

	/**
	 * El archivo tiene que estar dentro de la carpeta de la categoría, ya saneada.
	 */
	public function isAllowedFile(): bool
	{
		$controllerName = basename( Paths::parsePath( $this->getController() ) );
		$controllerPath = Paths::parsePath(
			Paths::buildPath(
				$this->instance->paths->controllers->shortcodes,
				$this->category,
				$controllerName . '.php'
			)
		);

		return in_array( $controllerPath, $this->files, true );
	}
}
