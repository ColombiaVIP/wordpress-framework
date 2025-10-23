<?php

use Fw\Core\Exceptions\General;
use Fw\Core\Response\ResponseView;
use Fw\Init\Init;
use Fw\Paths;

if (!function_exists('view')) {
    /**
     * Retorna una respuesta de tipo View.
     * 
     * @param string $view Nombre de la vista.
     * @param string|array|null $args Parámetros para la vista.
     * @return ResponseView
     */
    function view(string $view, string|array|null $args = null): ResponseView
    {
        $view = str_replace('.php', '', $view);
        return new ResponseView('view', $view, $args);
    }
}

if (!function_exists('viewPath')) {
    /**
     * Valida si una vista existe y retorna su path.
     *
     * @param string $pluginPath Ruta base.
     * @param string $view Nombre de la vista.
     * @return string|null
     * @throws General
     */
    function viewPath(string $pluginPath, string $view): ?string
    {
        if (file_exists(Paths::BuildPath($pluginPath, 'views', "$view.php"))) {
            return Paths::BuildPath($pluginPath, 'views', "$view.php");
        } else if (WP_DEBUG) {
            throw new General("Path view: $view", 404);
        }

        return null;
    }
}

if (!function_exists('getPart')) {
    /**
     * Retorna una parte del sitio dependiendo si es un tema de tipo bloque o estándar.
     * 
     * @param string $part
     * @return string|null
     */
    function getPart(string $part): ?string
    {
        $isThemeTypeBlock = Init::isThemeTypeBlock();

        return match ($part) {
            'header' => $isThemeTypeBlock ? block_template_part('header') : get_header(),
            'footer' => $isThemeTypeBlock ? block_template_part('footer') : get_footer(),
            default => null,
        };

    }
    function getHeader()
    {

        ?>
            <!DOCTYPE html>
            <html <?php language_attributes(); ?>>

            <head>
                <meta charset="<?php bloginfo('charset'); ?>" />
                <?php
                wp_head();
                ?>
            </head>

            <body <?php body_class(); ?>>
                <?php wp_body_open(); ?>
                <header>
                    <?php getPart('header'); ?>
                </header>

        <?php

    }
    function getFooter()
    {

        ?>
            <footer>
                <?php getPart('footer'); ?>
            </footer>
            <?php wp_footer(); ?>
            </body>

            </html>
        <?php

    }
}