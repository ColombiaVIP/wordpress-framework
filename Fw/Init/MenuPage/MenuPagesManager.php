<?php
/**
 * Obtiene y valida las Menu Page y las Sub Menu Pages para su creación.
 * 
 */
namespace Fw\Init\MenuPage;

use Fw\Paths;
use Fw\Init\MenuPage\MenuPages;
use Fw\Core\Request\Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class MenuPagesManager  
{
    /** @var array $args Argumentos de las Menu Page y las Sub Menu Pages. */
    protected array $args;

    /** @var string $namespace Namespace base de la aplicación. */
    protected string $namespace;
    /** @var string $path Ruta principal de las Menu Pages. */
    protected string $path;

    public function __construct(string $namespace, string $path) {
        $this->namespace = $namespace;
        $this->path = $path;
    }

    /**
     * Prepara el proceso de creación.
     *
     * @return void
     **/
    public function prepare() : void
    {
        if ( !file_exists($this->path) ) {
            return;
        }

        # Folers
        if ( !$dirs = array_diff(scandir($this->path), array('.', '..')) ) {
            return;
        }
        
        $menuPages = [];
        foreach ($dirs as $directory) {
            $mainPath = Paths::buildPath($this->path, $directory);

            if ( !$files = glob(Paths::buildPath($mainPath, '*.php')) ) {
                continue;
            }

            # Menu Page Principal
            if ( ($menuPageKey = array_search(Paths::buildPath($mainPath, "{$directory}.php"), $files)) !== false ) {
                $menuPage = $files[$menuPageKey];
                unset($files[$menuPageKey]);
            } else if ( ($menuPageKey = array_search(Paths::buildPath($mainPath, "{$directory}Controller.php"), $files)) !== false ) {
                $menuPage = $files[$menuPageKey];
                unset($files[$menuPageKey]);
            } else {
                $menuPage = $files[0];
                unset($files[0]);
            }

            

            
            # Menu Page
            $classPath = Paths::buildNamespacePath(
                $this->namespace, 
                'Controllers', 
                'MenuPages', 
                $directory, 
                basename($menuPage, '.php')
            );
            $menuPages[$directory] = $this->prepareMenuPage(
                $classPath
            );
            
 
            // # Sub Menu Page para cada metodo #No se si es necesario ya que option recibe los metodos
            // $classMethods=get_class_methods($classPath);
            
            // $methodKey = array_search("index", $classMethods);
            // if ($methodKey !== false) {
            //     unset($classMethods[$methodKey]);
            // }
            // if( $methodKey = array_search("error404", $classMethods) ) {
            //     unset($classMethods[$methodKey]);
            // }

            // foreach ($classMethods as $classMethod) {
            //     $menuPages[$directory]['subMenuPages'][] = $this->prepareMenuPage(
            //         $classPath.'::'.$classMethod,
            //         true
            //     );
            // }
            

            # Sub Menu Page para el resto de archivos
            foreach ($files as $file) {
                $menuPages[$directory]['subMenuPages'][] = $this->prepareMenuPage(
                    Paths::buildNamespacePath(
                        $this->namespace, 
                        'Controllers', 
                        'MenuPages', 
                        $directory, 
                        basename($file, '.php')
                    ),
                    true
                );
            }
        }
        
        if ( count($menuPages) > 0 ) {
            $menuPages['path'] = Paths::findPluginPath($this->path);
            $this->args = $menuPages;
            # Se envian los argumentos para la creación de las (Sub) Menu Page.
            MenuPages::createMenuPages($this->args);
        }
    }


    /**
     * Retorna un array con los datos necesarios para crear una (Sub) Menu Page.
     *
     * @param string $class
     * @param bool $isSubMenuPage
     * @return array
     **/
    public function prepareMenuPage(string $class, bool $isSubMenuPage = false) : array
    {   
        # Primero extraigo el metodo de la clase #No se si es necesario ya que option recibe los metodos
        // $method= explode('::', $class)[1]??null;
        // $class= explode('::', $class)[0];

        $controllerName = str_replace('\\', '/', $class);
        $controllerName = basename($controllerName);
        $controllerName = str_replace('Controller', '', $controllerName);
        
        # Page Title
        $menuPage['pageTitle'] = Request::propertyExists($class, 'pageTitle') ? $class::$pageTitle : spaceUpper($controllerName);

        # Menu Title
        $menuPage['menuTitle'] = Request::propertyExists($class, 'menuTitle') ? $class::$menuTitle : spaceUpper($controllerName);

        # Capability. Aplica a todos los métodos del controlador ($capabilities puede
        # sobrescribirla por método). Sin $capability se usa install_plugins, como en 1.3.1.
        $menuPage['capability'] = Request::controllerCapability($class);
        self::noticeMissingCapability($class);

        # Slug
        $menuSlug = explode('\\', $class);
        $menuPage['menuSlug'] = Request::propertyExists($class, 'menuSlug') ? $class::$menuSlug : $controllerName;
        $menuPage['menuSlug'] = strToSlug($menuSlug[0]) . '-' . strToSlug($menuSlug[3]) . '-' . strToSlug($menuPage['menuSlug']);

        # callable
        $menuPage['callable'] = [
            'controller' => $class,
            'method' => Request::propertyExists($class, 'callable') ? $class::$callable : 'index'
        ];

        
        if ( !$isSubMenuPage ) {
            # Icon only for Menu Page
            $menuPage['icon'] = Request::propertyExists($class, 'icon') ? $class::$icon : 'dashicons-schedule';
        }elseif ($method??false) { # If Method exists #No se si es necesario ya que option recibe los metodos

                // # callable
                // $menuPage['callable'] = [
                //     'controller' => $class,
                //     'method' => $method,
                // ];

                // # Page Title
                // $menuPage['pageTitle'] .= ' ' . spaceUpper($method);

                // # Menu Title
                // $menuPage['menuTitle'] = spaceUpper($method);

                // # Slug   
                // $menuPage['menuSlug'] .= '-' . $method;
 
        }     

        # Position
        $menuPage['position'] = Request::propertyExists($class, 'position') ? $class::$position : 4;

        return $menuPage;
    }


    /**
     * Aviso (solo con WP_DEBUG y para quien puede activar plugins) cuando una Menu Page
     * no declara $capability. No cambia el comportamiento: se sigue usando install_plugins.
     */
    public static function noticeMissingCapability(string $class) : bool
    {
        if ( \Fw\Core\Request\Actions::declaresCapability($class) ) {
            return false;
        }

        if ( ! defined('WP_DEBUG') || ! WP_DEBUG ) {
            return false;
        }

        if ( ! function_exists('current_user_can') || ! current_user_can('activate_plugins') ) {
            return false;
        }

        if ( ! class_exists('AdminNotice') ) {
            require_once dirname(__DIR__, 2) . '/helpers/AdminNotice.php';
        }

        if ( class_exists('AdminNotice') ) {
            \AdminNotice::generalAdminNotice(
                'WP Framework',
                sprintf(
                    'La Menu Page <code>%s</code> no declara <code>public static $capability</code>. Se usa <code>%s</code> por defecto; decláralo para elegir quién puede entrar.',
                    esc_html($class),
                    esc_html(\Fw\Core\Request\Actions::DEFAULT_CAPABILITY)
                ),
                'warning',
                true
            );
        }

        return true;
    }
}
