<?php
/**
 * Clase principal del Framework.
 * 
 * Carga las rutas.
 * Setea los argumentos.
 * Construcción de estrucruas.
 * Procesos iniciales.
 * 
 */

namespace Fw;

use Fw\Paths;

class Framework  
{ 
    public object $paths;
    protected array $args;

    public function __construct (string $pluginFilePath, array $args = array())
    { 
        # Rutas de la aplicacion.
        $this->paths = new Paths($pluginFilePath);
        # Se establecen argumentos que seran utilizados para extension del Framework.
        $this->args = $this->setArguments($args);

        # Creación de estructuras.
        Structures\BuildStructures::init(['autoload', 'app'], [
            'mode' => $this->args['mode'],
            'autoload' => $this->args['autoload'],
            'pluginPath' => $this->paths->pluginPath,
        ]);
        
        # Se carga el autoload del plugin
        include_once ( Paths::buildPath($this->paths->pluginPath, 'autoload', 'autoload.php') );

        new Init($this->paths, [
            'loadAssets' => $this->args['loadAssets'],
            'routing' => $this->args['routing'],
        ]);
    }


    /**
     * Se establecen los argumentos de la App.
     * Esto permitira implementar a futuro configuraciones de la App.
     *
     * @param array $args
     * @return array
     **/
    public function setArguments(array $args) : array
    {
        $mode = array_key_exists('mode', $args) ? $args['mode'] : 'dev';

        return array_replace_recursive( array(
            'mode' => $mode,
            'autoload' => array(
                'uniqueName' => Structures\Autoload::createUniqueName(basename($this->paths->pluginFilePath)),
                'psr-4' => [
                    Paths::createNamepace($this->paths->pluginFilePath) . "\\" => 'app/',
                ],
                'files' => $this->listHelperFiles(),
            ),
            'routing' => array(
                'force' => false,
            ),
            'loadAssets' => array(
                'admin' => [
                    'is_admin' => true,
                    'load' => 'all',
                    'mode' => $mode,
                    'path' => $this->paths->adminAssets,
                    'argsJs' => [
                        'ajaxurl' => admin_url('admin-ajax.php')
                    ],
                    'in_footer' => false
                ], 
                'public' => [
                    'load' => 'all',
                    'mode' => $mode,
                    'path' => $this->paths->assets,
                    'argsJs' => [
                        'ajaxurl' => admin_url('admin-ajax.php')
                    ],
                    'in_footer' => false
                ]
            ),
        ), $args );
    }

    /**
     * Lista en un array los archivos helpers de la aplicación.
     *
     * @return array
     **/
    public function listHelperFiles() : array
    {   # Archivos para autocargar
        $files = array();
        foreach (glob(Paths::buildPath($this->paths->helpers, '*.php')) as $index => $file) {
            $file = str_replace($this->paths->pluginPath, '', $file);
            $files[] = ltrim($file, '/');
        }
        return $files;
    }

}
