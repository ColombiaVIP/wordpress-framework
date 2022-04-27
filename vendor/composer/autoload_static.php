<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite4dbe638b99b562ed43b1e802e260206
{
    public static $files = array (
        'f59669d56395e3551023b7098f6d8dba' => __DIR__ . '/../..' . '/Fw/helpers/assets.php',
        'ec2dd61954688f8c3e51da99206d21db' => __DIR__ . '/../..' . '/Fw/helpers/general.php',
        'adad57b39792bf8ea687658285e24658' => __DIR__ . '/../..' . '/Fw/helpers/views.php',
    );

    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'WordpressFramework\\' => 19,
        ),
        'F' => 
        array (
            'Fw\\' => 3,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'WordpressFramework\\' => 
        array (
            0 => __DIR__ . '/../..' . '/app',
        ),
        'Fw\\' => 
        array (
            0 => __DIR__ . '/../..' . '/Fw',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite4dbe638b99b562ed43b1e802e260206::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite4dbe638b99b562ed43b1e802e260206::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInite4dbe638b99b562ed43b1e802e260206::$classMap;

        }, null, ClassLoader::class);
    }
}
