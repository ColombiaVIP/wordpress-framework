<?php
/**
 * Gestiona las rutas utilizando la API de reescritura de WordPress.
 * 
 */

namespace Fw\Init\Routing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Router
{
    /** Query var de método de 1.3.1. Se sigue registrando por compatibilidad. */
    public const LEGACY_METHOD_VAR = 'method';

    /** @var string $routeName Nombre de la ruta. */
    private string $routeName;
    /** @var array $routes Rutas a registrar. */
    private array $routes = array();
    /** @var bool $force Si Forzar ruta. */
    private bool $force;
    /** @var string $pluginPath */
    public $pluginPath = '';

    /** @var bool $methodTagReady El query var de método de este plugin ya se registró. */
    private bool $methodTagReady = false;

    public function __construct(string $routeName, bool $force = false)
    {
        $this->routeName = $routeName;
        $this->force = $force;
    }

    /**
     * Añade una ruta al router.
     * Sobrescribe una ruta si comparte el mismo nombre que una ya registrada.
     *
     * @param string $name
     * @param Route  $route
     */
    public function addRoute(string $name, Route $route)
    {
        $this->routes[$name] = $route;
    }

    /**
     * Compila el enrutador (routeName) en reglas de reescritura de WordPress.
     * 
     * Crea las reglas de escritura para el uso de un controlador y métodos.
     * Registra las reglas de escritura en WordPress.
     * 
     */
    public function compile() : void
    {
        # Para el controlador. El query var de método se registra solo si
        # alguna ruta tiene enableUri (el del plugin y "method" por compatibilidad).
        add_rewrite_tag(
            "%{$this->routeName}%", 
            '(.+)'
        );

        foreach ($this->routes as $name => $route) {
            $this->addRule($name, $route);
        }
    }

    /**
     * Query var del segmento de método. cvip-loteria pasa a cvip_loteria_method.
     */
    public function getMethodQueryVar(): string
    {
        $safe = strtolower( str_replace( '-', '_', $this->routeName ) );
        $safe = (string) preg_replace( '/[^a-z0-9_]/', '', $safe );
        $safe = trim( $safe, '_' );
        if ( $safe === '' ) {
            $safe = 'wpfw';
        }

        return $safe . '_method';
    }

    /**
     * Registra el tag del método una sola vez, y solo porque hay una ruta con enableUri.
     */
    private function registerMethodTag(): void
    {
        if ( $this->methodTagReady ) {
            return;
        }

        add_rewrite_tag( '%' . $this->getMethodQueryVar() . '%', '([^/]*)' );
        // Compatibilidad 1.3.1: reglas guardadas con &method= y enlaces ?method=.
        add_rewrite_tag( '%' . self::LEGACY_METHOD_VAR . '%', '(.+)' );
        $this->methodTagReady = true;
    }

    /**
     * Añade una nueva regla de reescritura de WordPress para la ruta dada.
     *
     * @param string $name
     * @param Route  $route
     * @param string $priority
     */
    private function addRule(string $name, Route $route) : void
    {
        add_rewrite_rule(
            $this->generateRouteRegex($route), # Expresión regular para comparar la solicitud.
            "index.php?{$this->routeName}=$name", 
            $this->priority($route)
        );
    }

    /**
     * Añade una nueva regla de reescritura de WordPress para la ruta dada.
     * Habilita el uso de métodos por medio de URI.
     *
     * @param string $name
     * @param Route  $route
     * @param string $priority
     */
    public function addRuleEnableUri(string $name, Route $route) : void
    {
        $this->registerMethodTag();
        $queryVar = $this->getMethodQueryVar();

        add_rewrite_rule(
            $this->generateRouteRegex($route, '/([^/]*)'),
            'index.php?' . $this->routeName . '=' . $name . '&' . $queryVar . '=$matches[1]',
            $this->priority($route)
        );
    }

    /**
     * Se define la prioridad de la ruta.
     *
     * @param Route $route
     * @return string
     **/
    public function priority(Route $route) : string
    {
        if ( $this->force && $route->force ) {
            return 'top';
        } else if ( !$this->force && $route->force ) {
            return 'top';
        } else if ( $this->force && is_null($route->force) ) {
            return 'top';
        }

        return 'bottom';
    }


    /**
     * Genera la regex para la API de reescritura de WordPress para la ruta dada.
     *
     * @param Route $route
     * @return string
     */
    private function generateRouteRegex(Route $route, string $matches = '') : string
    {
        return '^'. ltrim(trim($route->getPath()), '/') . "{$matches}$";
    }

    /**
     * Intenta encontrar una ruta en queryVariables. 
     *
     * @param array $query_variables
     * @return Route|WP_Error
     */
    public function match(array $queryVariables)
    {
        if (empty($queryVariables[$this->routeName])) {
            return new \WP_Error('missing_routeName');
        }

        $routeName = $queryVariables[$this->routeName];
        if (!isset($this->routes[$routeName])) {
            return new \WP_Error('route_not_found');
        }

        return $this->routes[$routeName];
    }

    /**
     * Borra todas las rutas de WordPress.
     *
     */
    public static function flush() : void
    {
        flush_rewrite_rules();
    }

    /**
     * Obtener si se debe forzar la ruta (conf global).
     *
     */
    public function getForce()
    {
        return $this->force;
    }

    /**
     * Obtener el routeName
     *
     */
    public function getRouteName()
    {
        return $this->routeName;
    }
}