<?php
/**
 * Separa un string utilizando las letras en mayusculas.
 * Ejemplo: FrameworkWp
 * Resultado: Framework Wp
 *
 * @param string $string
 * @param string $separator
 * @return string
 **/
function spaceUpper(string $string, string $separator = ' ') : string
{
    $string = preg_replace( '/[A-Z]/', "{$separator}$0",  $string);
    $string = trim($string, $separator);
    
    return $string;
}

/**
 * Convierte un string a un slug.
 *
 * @param string $string
 * @param string $separator Separador de palabras.
 * @return string
 **/
function strToSlug(string $string, string $separator = '-') : string
{
    $string = remove_accents($string);
    $string = strtolower($string);
    $string = str_replace(' ', $separator, $string);

    return $string;
}

/**
 * Retorna la url base de un controlador.
 * Ejemplo: Controllers\Routes\AlohaMundoController => /aloha-mundo
 * 
 * @param string $routeController
 * @return string
 **/
function routeUrl(string $routeController) : string
{
    # Se remueve la palabra controller, se recorta el nombre de la clase y se remueven los acentos.
    $routeController = str_replace('Controller', '', $routeController);

    $routeController = explode('\\', $routeController);
    $routeController = end($routeController);

    $routeController = remove_accents($routeController);
    $routeController = preg_replace( '/[A-Z]/', '-$0',  $routeController);
    $routeController = strtolower( ltrim($routeController, '-') );
    $routeController = str_replace( ' ', '-', $routeController );

    return '/' . trim($routeController, '/');
}

if ( ! function_exists('consoleLog') ) :
/**
 * console_log
 *
 * Allows PHP to print to js console 
 *
 * @param string|array|object $input Var to Show
 * @param bool $with_script_tags Inside Script Tags
 * @return null 
 **/
function consoleLog($input, $with_script_tags = true) {
    $js_code = 'console.log(' . json_encode($input, JSON_HEX_TAG) .
    ');';
    if ($with_script_tags) {
        $js_code = '<script>' . $js_code . '</script>';
    }
    echo $js_code;
}
endif;

if ( ! function_exists('printPre') ) :
/**
 * printPre
 *
 * Prints any PHP object|array|var to preformatted tags 
 *
 * @param mixed $input Var to Show
 * @return string
 **/
function printPre($input, $return = false):?string  {
    $pre="<pre>".print_r( $input,true)."</pre>";
    if ($return) {
        return $pre;
    }
    echo $pre;
};
endif;

//NOT TESTED:
if ( ! function_exists('printVars') ) :
/**
 * printVars
 *
 * Prints any PHP object|array|var to preformatted tags 
 *
 * @param mixed $input Var to Show
 * @return string
 **/
function printVars($input, $return = false):?string  {

    foreach ( $input as $key => $value ) {
        if ( ! is_scalar( $value ) ) {
            continue;
        }

        $var = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
        $vars += "var $input[$key] = " . wp_json_encode( $var ) . ';';
    }
    
    $script="<script class='cvipVars'>".$vars."</script>";
    if ($return) {
        return $script;
    }
    echo $script;
};  
endif;


