<?php
/**
 * Obtiene y valida las Menu Page y las Sub Menu Pages para su creación.
 * 
 */
namespace Fw\Init\MenuPage;

use Fw\Paths;
use Fw\Init\MenuPage\MenuPages;
use Fw\Core\Request\Request;

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
            if ( $menuPageKey = array_search(Paths::buildPath($mainPath, "{$directory}.php"), $files) ) {
                $menuPage = $files[$menuPageKey];
                unset($files[$menuPageKey]);
            } else if ( $menuPageKey = array_search(Paths::buildPath($mainPath, "{$directory}Controller.php"), $files) ) {
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
            
 
            # Sub Menu Page para cada metodo
            $classMethods=get_class_methods($classPath);
            
            $methodKey = array_search("index", $classMethods);
            if ($methodKey !== false) {
                unset($classMethods[$methodKey]);
            }
            if( $methodKey = array_search("error404", $classMethods) ) {
                unset($classMethods[$methodKey]);
            }

            foreach ($classMethods as $classMethod) {
                $menuPages[$directory]['subMenuPages'][] = $this->prepareMenuPage(
                    $classPath.'::'.$classMethod,
                    true
                );
            }
            

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
        # Primero extraigo el metodo de la clase
        $method= explode('::', $class)[1]??null;
        $class= explode('::', $class)[0];

        $controllerName = str_replace('\\', '/', $class);
        $controllerName = basename($controllerName);
        $controllerName = str_replace('Controller', '', $controllerName);
        
        # Page Title
        $menuPage['pageTitle'] = Request::propertyExists($class, 'pageTitle') ? $class::$pageTitle : spaceUpper($controllerName);

        # Menu Title
        $menuPage['menuTitle'] = Request::propertyExists($class, 'menuTitle') ? $class::$menuTitle : spaceUpper($controllerName);

        # Capability
        $menuPage['capability'] = Request::propertyExists($class, 'capability') ? $class::$capability : 'install_plugins';

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
        }elseif ($method??false) { # If Method exists

                # callable
                $menuPage['callable'] = [
                    'controller' => $class,
                    'method' => $method,
                ];

                # Page Title
                $menuPage['pageTitle'] .= ' ' . spaceUpper($method);

                # Menu Title
                $menuPage['menuTitle'] = spaceUpper($method);

                # Slug   
                $menuPage['menuSlug'] .= '-' . $method;
 
        }     

        # Position
        $menuPage['position'] = Request::propertyExists($class, 'position') ? $class::$position : 4;

        return $menuPage;
    }

}
