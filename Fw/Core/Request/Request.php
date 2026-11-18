<?php
/**
 * Recibe la solicitud y gestiona la respuesta para el usuario.
 *
 */

namespace Fw\Core\Request;

use ReflectionMethod;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


abstract class Request implements RequestInterface
{
    /** @var string $pluginPath Ruta principal de la aplicación. */
    public string $pluginPath;
    
    /** @var string $controller Namespace del controlador. */
    protected string $controller;
    
    /** @var string $method Método a ejecutar por el controller. */
    protected string $method;
    
    public function __construct(string $pluginPath, ?string $controller = null, ?string $method = null)
    {
        $this->pluginPath = $pluginPath;
        $this->controller = is_string( $controller ) ? $controller : '';
        $this->method     = ( is_string( $method ) && $method !== '' ) ? $method : 'index';
    }

    /**
     * Capability del controlador de menú. Si no declara una, manage_options.
     */
    public static function controllerCapability( string $controller ): string
    {
        if ( class_exists( $controller ) && property_exists( $controller, 'capability' ) ) {
            $capability = $controller::$capability;
            if ( is_string( $capability ) && $capability !== '' ) {
                return $capability;
            }
        }

        return 'manage_options';
    }

    /**
     * Invoca el método. Si no declara parámetros, no se le pasa el array de la petición:
     * en PHP 8 un argumento de más es un ArgumentCountError.
     *
     * @param array{0: object, 1: string} $callback
     * @param list<mixed>                 $arguments
     */
    protected function invoke( array $callback, array $arguments = array() ): mixed
    {
        $reflection = new ReflectionMethod( $callback[0], $callback[1] );
        if ( $reflection->getNumberOfParameters() === 0 ) {
            $arguments = array();
        }

        return $reflection->invokeArgs( $callback[0], $arguments );
    }
    
    /**
     * Obtiene el controlador para la solicitud.
     *
     * @return string
     */
    public function getController(): string
    {
        return $this->controller;
    }
    
    /**
     * Obtiene el método para la solicitud.
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }
    
    /**
     * Verifica si un método existe.
     * Comprueba su accesibilidad.
     *
     * isFinal, isPrivate, isProtected, isPublic, isStatic.
     *
     * @param string $controller
     * @param string $method
     * @param string|null $access Valores permitidos: isAbstract, isConstructor(), isDestructor(),
     *
     * @return bool
     **/
    public static function methodExists(string $controller, string $method, ?string $access = null): bool
    {
        $methodExists = false;
        
        if (method_exists($controller, $method)) {
            $methodExists = true;
            if ($access) {
                $reflection = new ReflectionMethod($controller, $method);
                if (!$reflection->$access()) {
                    $methodExists = false;
                }
            }
        }
        
        return $methodExists;
    }
    
    /**
     * Verifica si una propiedad existe.
     *
     * @param string $controller
     * @param string $property
     * @return null|bool
     **/
    public static function propertyExists(string $controller, string $property): ?bool
    {
        if (property_exists($controller, $property)) {
            return (bool)$controller::$$property;
        }
        
        return null;
    }
    
    /**
     * @param string $name
     * @param mixed $property
     * @return void
     */
    protected function setProperty(string $name, mixed $property): void
    {
        if ( $property ) {
            $this->$name = $property;
        }
    }
    
}
