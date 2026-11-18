<h1 align="center" style="color: red !important;">Wp Framework</h1>

## Descripción

<p>Es un framework desarrollado para el CMS WordPress que hace uso del patrón de arquitectura mvc (modelo-vista-controlador), esto con la intención de ayudar y disminuir el tiempo de desarrollo de sistemas.

La idea original y la primera versión fue planteada y desarrollada por el <strong><a href="https://www.linkedin.com/in/ingenieroleon">Ingeniero César León</a></strong>, CEO de la empresa <strong><a href="https://colombiavip.com">ColombiaVIP</a></strong>, la cual cuenta con más de 15 años de experiencia en la creación y diseño de páginas web.

Este proyecto se hace <strong>Open Source</strong> y será encabezado por <strong><a href="https://flikimax.com">Flikimax</a></strong> para su reestructuración, se tomara como punto de partida, la idea original, un framework mvc para Wordpress.</p>

## Instalación

#### Prerrequisitos

- PHP ^8.3.0
- Composer

#### Instalación

```
git clone https://github.com/ColombiaVIP/wordpress-framework.git
```

```
cd wordpress-framework
```

```
composer install
```

#### Documentación

[La documentación la puedes encontrar aquí.](https://docs.wordpress-framework.com/docs)

## Contrato de un plugin de aplicación

Un plugin de aplicación se instala aparte y arranca con `new Fw\Framework(__FILE__)`. El framework descubre controladores por carpeta. A partir de la 1.4.0 un método `public` no es una URL, ni un shortcode, ni una acción de menú.

### Métodos web (rutas y shortcodes)

En el controlador de `app/Controllers/Routes` o de `app/Controllers/Shortcodes`:

```php
class PqrsController
{
    public static bool $enableUri = true;

    /** @var list<string> Además de index y error404. */
    public static array $webActions = ['enviar', 'consultar'];

    public function index() { /* /pqrs */ }

    public function enviar() { /* /pqrs/enviar */ }

    public function consultar() { /* /pqrs/consultar */ }

    public function guardarEnBase() { /* public, pero la URL no lo llama */ }
}
```

También vale el atributo `#[\Fw\Core\Request\WebAction]` sobre el método. `index` y `error404` no hace falta listarlos. Un método `private` o `protected` nunca es acción web.

Con `enableUri` en true la URL sigue siendo `https://sitio.test/pqrs/enviar`. El query var ya no se llama `method`. Para la carpeta `cvip-loteria` es `cvip_loteria_method` (guiones a guion bajo, más `_method`). Si `enableUri` es false, ese query var no se registra y `?method=` no ejecuta nada.

El shortcode sigue siendo `[cvip_loteria Pqrs=Pqrs@enviar]`. La categoría, la clase y el método tienen que ser identificadores (`A-Za-z_`). `@enviar` solo funciona si `enviar` está en `$webActions`.

### Menú de administración

La capability por defecto es `manage_options`. Se puede declarar `public static $capability`.

La pantalla principal (sin `option`) no lleva nonce. Cualquier `option` exige el nonce `_wpfw_nonce` y la capability. El método tiene que estar en `$webActions`.

```php
public static array $webActions = ['guardar', 'borrar'];

echo '<a href="' . esc_url(wpfw_admin_action_url($page, 'borrar', ['id' => $id])) . '">Borrar</a>';
```

Un formulario POST hacia `option=save`:

```php
<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . $page . '&option=save')); ?>">
    <?php echo wpfw_admin_nonce_field($page, 'save'); ?>
</form>
```

`FormController` y `ListTableController` ya arman ese nonce. `orderby` solo ordena si el nombre es una columna real de la fila.

### AJAX

Los controladores viven en `app/Controllers/Ajax`. No se incluye ningún archivo a partir de la petición: al iniciar, el framework lee la carpeta y registra solo las claves de `$ajaxActions`.

```php
class PqrsController
{
    public static array $ajaxActions = [
        'consultar' => [
            'public' => true,   // también wp_ajax_nopriv_
            'nonce'  => true,   // es el valor por defecto
        ],
        'registrar' => [
            'public'     => false,
            'capability' => 'manage_options', // por defecto si public es false
            'nonce'      => true,
        ],
    ];

    public function consultar(): array
    {
        return ['ok' => true];
    }

    public function registrar(): array
    {
        return ['ok' => true];
    }
}
```

Para el slug `cvip-loteria` la petición a `admin-ajax.php` lleva:

- `action` = `cvip_loteria_consultar` (`wpfw_ajax_hook('cvip-loteria', 'consultar')`)
- `_ajax_nonce` = `wpfw_ajax_nonce('cvip-loteria', 'consultar')`

`wpfw_ajax_client_config('cvip-loteria')` devuelve la URL, el `action` y el nonce de cada acción para `wp_localize_script`. El método tiene que ser `public`, sin argumentos obligatorios, y puede devolver un array: se responde con `wp_send_json_success`. Un método que no esté en `$ajaxActions` no tiene gancho, aunque sea `public`.

Por defecto una acción no es pública, exige nonce y, si no es pública, exige `manage_options`. Poner `'nonce' => false` es una decisión explícita.

### Vistas

En la hoja se escapa cada dato: `esc_html` para texto, `esc_attr` para atributos, `esc_url` para enlaces. El `$content` de un layout es HTML que ya devolvió `view()`. No se vuelve a escapar.

### Cambios que rompen plugins ya generados

1. Hay que declarar en `$webActions` (o con `#[WebAction]`) cada método que hoy se abre por URL, shortcode u `option`. `index` y `error404` siguen igual.
2. `?method=accion` deja de despachar. La ruta bonita `/controlador/accion` se mantiene si `enableUri` es true.
3. Los enlaces `admin.php?page=...&option=accion` necesitan `wpfw_admin_action_url()`. Sin el nonce responden 403.
4. La capability por defecto del menú pasa de `install_plugins` a `manage_options`.
5. Las reglas de reescritura se regeneran al activar el plugin, no en cada visita. Después de cambiar rutas hay que reactivar el plugin (o guardar los enlaces permanentes).
6. La categoría del shortcode y las claves de sus atributos tienen que ser identificadores.
7. Textos que antes salían con HTML crudo ahora salen escapados.

### Nota para cvip-loteria

PQRS, contratos y documentos pueden ser rutas con `$webActions` (`enviar`, `consultar`, `descargar`) y `enableUri` donde el visitante deba abrir el método. Lo que modifica datos en el admin va por `option` con nonce, o por AJAX con `public`, `capability` y `nonce` declarados en `$ajaxActions`. Un helper interno, aunque sea `public`, se queda fuera de las dos listas.

---
### Changelog

## [1.4.0] 20261118:

Cierre del despacho abierto, del HTML sin escapar y de las acciones de administración sin nonce. Hay una capa AJAX con lista blanca. La versión sube de 1.3.1 a 1.4.0 porque cambia lo que un plugin de aplicación puede invocar desde fuera.

* Un método `public` ya no es una ruta. Se declara en `$webActions` o con `#[Fw\Core\Request\WebAction]`. `index` y `error404` siguen permitidos. La comprobación `isPublic` vuelve a ejecutarse.
* El query var global `method` desaparece. Con `enableUri` el segmento de la URL usa `{carpeta}_method` (`cvip-loteria` → `cvip_loteria_method`). Si `enableUri` es false, ese dato no se lee.
* `option` en un menú exige el nonce `_wpfw_nonce` y la capability del controlador. Atajos: `wpfw_admin_action_url()` y `wpfw_admin_nonce_field()`. `FormController` y `ListTableController` los incluyen. `orderby` solo acepta una columna de la fila.
* La capability por defecto del menú es `manage_options`.
* Shortcode: categoría, clase y método son identificadores, y el método tiene que estar en la lista blanca.
* `HTMLController`, las vistas hoja y la tabla escapan texto, atributos y URLs.
* `template.php` despacha al processor que coincidió con la petición y no recorre los filtros globales. Dos plugins no se pisan.
* Una ruta inexistente, un método fuera de la lista o un `Throwable` en el controlador caen en la 404 del tema. Ya no se llama a `wp_die` en ese caso.
* `flush_rewrite_rules` corre al activar el plugin, no en cada `init`.
* Los PHP del plugin cortan el acceso directo si no existe `ABSPATH`. Los cuatro helpers que carga Composer solo salen si el archivo se abrió directo, para no cortar la consola.
* AJAX en `app/Controllers/Ajax`: `$ajaxActions` declara `public`, `capability` y `nonce`. El gancho es `{slug}_{metodo}`. No hay includes tomados de la petición.
* `templateApp` nace con la lista de acciones, vistas escapadas, el nonce de menú documentado y un controlador AJAX de ejemplo.
* Pruebas PHPUnit con Brain Monkey: despacho, nonce, escape, aislamiento de dos plugins, AJAX, shortcode, assets y `Model::describe()`.

## [1.3.1] 20260928:

Correcciones locales que no cambian rutas, menús ni la carga de scripts. La versión de los assets en producción sale de la cabecera `Version` de `wp-framework.php`: `WPFW_VERSION` ya no se escribe a mano.

* `WPFW_VERSION` se lee con `get_file_data()` desde la cabecera del plugin. Cambiar `Version` en `wp-framework.php` actualiza el cache-bust de CSS y JS en modo producción.
* `composer.json`: el paquete pasa a llamarse `colombiavip/wordpress-framework`, la licencia queda en `GPL-2.0-or-later` (la misma de la cabecera) y la descripción deja de traer bytes nulos en «patrón» e «intención».
* El menú principal ya no ignora el controlador cuando `array_search` lo encuentra en el índice 0.
* `Apps::setConfig` guarda la opción en la propiedad pedida. Antes escribía en una variable que no existía.
* Las vistas usan `extract()` con `EXTR_SKIP`, así un argumento no puede pisar las variables internas del renderer.
* Pedir solo JS o solo CSS en `LoadAssets` usa la configuración de la instancia. Esos dos modos fallaban porque leían una variable inexistente.
* `printVars` inicializa su acumulador y solo imprime claves que son identificadores JavaScript válidos.
* `spaceUpper`, `strToSlug` y `routeUrl` se definen solo si otro plugin no las declaró antes.
* El textarea del formulario ya no manda el nombre del campo a la consola del navegador.
* Al crear un plugin desde el menú del framework, el nombre se imprime escapado.
* `Model::describe()` solo ejecuta `DESCRIBE` si el nombre de tabla contiene letras, números o guion bajo.

## [1.3.0] 20251023:
* Refactor template rendering by removing HTML structure from template.php, improving view functions in views.php, and adding layoutHead.php for consistent header and footer management.

## [1.2.0] 20250701:
* Refactor FormController to handle text areas and update HTMLController textArea method to use wp_editor.

## [1.1.9] 20250628:
* Update asset loading logic and enhance template processing in Routing.

## [1.1.8] 20250619:
* Change trash link color to dark red in fwAdminStyle.css.

## [1.1.7] 20250409:
* Add printVars function to output PHP variables in script tags and update printPre return type.

## [1.1.6] 20250401:
* Change default order from ascending to descending in ListTableController.

## [1.1.5] 20250313:
* Remove bootstrap framework loading from asset initialization.

## [1.1.4] 20250224:
* FormController.php + HTMLController.php: ReadOnly and Required options added.


## [1.1.3] 20250220:
* proyectos-adminStyle: Optimized fwAdminStyle.css and removed duplicated styles.
* ListTableController: Corrected sort_data.
* FormController: optimized defaults.
* HTMLController: media method optimized.
* helpers/general: Added functions = consoleLog, printPre.
* wp-framework: Updated version number.

## [1.1.2] 20250217:
* Starter development of FormController.
* Search and header actions of LisTableController, also table is returned not displayed.s

## [1.1.1] 20250213 WP_List_Table:
* Added ListTableController based on WP_List_Table.

## [1.1.0] 20250211 Method Sub-menu :
* Menu Pages Controller creates Sub-Menu pages for every public method.

## [1.0.4] 20250124 Archive :
* Model integrated (check if needed).

## [1.0.3] 20250124 Archive :
* Archived zip.

## [1.0.2] 20250124 builsStructures :
* Validates file existes before build structures

## [1.0.1] 20250124 ORM :
* Commented ORM FACADE to reduce load, please use manually on app, see: https://github.com/dimitriBouteille/wp-orm/wiki/DB-facade

## [1.0.0] 20250124 First stable :
* Removed custom ORM, start using https://github.com/dimitriBouteille/wp-orm
* Removes 404 controller method, use __call magic method for this
