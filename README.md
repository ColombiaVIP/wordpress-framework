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

Un plugin de aplicación se instala aparte y arranca con `new Fw\Framework(__FILE__)`. El framework descubre controladores por carpeta. Un plugin hecho con 1.3.1 funciona sin cambios en 1.4.0.

### Regla de oro: `public` es la superficie pública

Igual que en 1.3.1, **todo método `public` de un controlador se puede invocar desde fuera**, sin declararlo en ninguna lista ni pasar variables extra:

| Tipo | Carpeta | Cómo se llama un método `public` |
|------|---------|----------------------------------|
| Ruta | `app/Controllers/Routes` | `https://sitio.test/ruta/metodo` (si `$enableUri = true`) |
| Shortcode | `app/Controllers/Shortcodes` | `[mi_plugin Categoria='Clase@metodo']` |
| Menu Page | `app/Controllers/MenuPages` | `wp-admin/admin.php?page=…&option=metodo` |

Por eso:

- Piense cada método `public` para el usuario final que lo va a abrir: valide la entrada y documéntelo como ruta, shortcode o acción.
- Todo lo interno (consultas, cálculos, guardar en base, helpers) va en métodos `private` o `protected` y se llama desde los `public`.
- `private`, `protected`, el constructor, el destructor y los métodos que empiezan por `__` **nunca** se ejecutan desde una URL, un shortcode ni un `option`. Si se piden, el framework usa `error404()` del controlador; si no lo tiene, la 404 del tema (rutas) o un `wp_die` 404 (menús).
- En rutas, un nombre que no existe en la clase lo atiende `__call()` si el controlador lo tiene (por ejemplo `/proyectos/mi-proyecto`), como en 1.3.1. Un nombre que coincide con un método `private`/`protected` no llega a `__call()`: cae en `error404()`.

```php
class PqrsController   // app/Controllers/Routes/PqrsController.php
{
    public static bool $enableUri = true;

    public function index()     { /* /pqrs */ }
    public function enviar()    { /* /pqrs/enviar: valida y llama a guardar() */ }
    public function consultar() { /* /pqrs/consultar */ }

    private function guardar(array $datos) { /* interno: ninguna URL lo alcanza */ }
}
```

Con `enableUri` el segmento de la URL viaja en el query var del plugin (`cvip-loteria` → `cvip_loteria_method`); `method` se sigue leyendo por compatibilidad con reglas y enlaces de 1.3.1. Al actualizar, las reglas de reescritura se regeneran solas una vez (también al activar el plugin y cuando cambian las rutas).

### Menu Pages: `$capability`, `$capabilities` y `$nonce`

`add_menu_page` ya exige estar logueado y tener `$capability`; además, el framework comprueba `current_user_can()` para el método concreto antes de llamarlo.

```php
class IngeurbeController   // app/Controllers/MenuPages/Ingeurbe/IngeurbeController.php
{
    public static $pageTitle = 'Proyectos INGEURBE';
    public static $menuSlug  = 'listado';

    // Obligatoria en la práctica: quién entra al menú y ejecuta TODOS los métodos public.
    // Si falta, se usa install_plugins (como en 1.3.1) y, con WP_DEBUG, un aviso en el admin
    // dice qué controlador no la declara.
    public static $capability = 'edit_posts';

    // Opcional: capability distinta para métodos concretos. Los no listados usan $capability.
    public static array $capabilities = [
        'trash' => 'delete_posts',
    ];

    // Opcional: métodos que exigen nonce (_wpfw_nonce) cuando llegan por ?option=.
    public static array $nonce = ['save', 'trash'];   // o true para todos

    public function index(array $params)  { /* listado */ }
    public function save(array $params)   { /* ?option=save */ }
    public function trash(array $params)  { /* ?option=trash&model=…&id=… */ }
    public function error404(array $params) { /* método inexistente o sin permiso */ }
}
```

- **Solo `$capability` (código 1.3.1):** se comporta exactamente como antes; esa capability vale para todos los métodos.
- **`$capabilities`:** sobrescribe por método. Si el usuario no la tiene, se muestra `error404()` del controlador (si lo puede ver) o `wp_die` 403.
- **`$nonce` (opt-in):** sin esta propiedad no se exige nonce, así los enlaces viejos siguen sirviendo. `ListTableController` y `FormController` ya añaden el nonce a sus enlaces y formularios, así que activar `$nonce` no exige cambiar vistas. Para enlaces o formularios propios:

```php
echo '<a href="' . esc_url(wpfw_admin_action_url($page, 'trash', ['model' => 'Proyectos', 'id' => $id])) . '">Eliminar</a>';

<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . $page . '&option=save')); ?>">
    <?php echo wpfw_admin_nonce_field($page, 'save'); ?>
</form>
```

Recomendado para métodos que escriben o borran: `$nonce` + una capability concreta en `$capabilities`, y no construir nombres de clase a partir de la petición.

`ListTableController` acepta claves con parámetros, como en 1.3.1: `'trash&model=Proyectos' => 'Eliminar'` genera `?page=…&option=trash&model=Proyectos&id=N`. `orderby` solo ordena si el nombre es una columna real de la fila.

### Shortcodes

`[cvip_loteria Pqrs=Pqrs@enviar]` ejecuta `enviar()` si es `public`. La categoría, la clase y el método tienen que ser identificadores (`A-Za-z0-9_`). Los demás atributos llegan completos al controlador.

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

### Compatibilidad con plugins de 1.3.1

No hay que tocar código. Cambios visibles:

1. Un método `private`/`protected` pedido por URL ya no llega a `__call()` ni da un error fatal: cae en `error404()` o en la 404 del tema.
2. Una ruta inexistente devuelve la 404 del tema en lugar de `wp_die`.
3. Textos que antes salían con HTML crudo en `HTMLController` y `ListTableController` ahora salen escapados.

### Nota para cvip-loteria

PQRS, contratos y documentos pueden ser rutas con `enableUri`: sus métodos `public` son lo que el visitante abre; lo interno va `private`. Lo que modifica datos en el admin va por `option` con `$nonce` y `$capabilities`, o por AJAX con `public`, `capability` y `nonce` declarados en `$ajaxActions`.

---
### Changelog

## [1.4.0] 20261118:

Endurecimiento retrocompatible: un plugin hecho con 1.3.1 funciona sin cambios.

* Los métodos `public` siguen siendo la superficie pública (ruta con `enableUri`, shortcode `Clase@metodo`, `?option=` en menús), sin listas ni variables extra.
* Vuelve la comprobación `isPublic`: `private`, `protected`, constructor, destructor y `__*` nunca se ejecutan desde fuera; caen en `error404()`. `__call()` sigue atendiendo nombres desconocidos en rutas.
* Menu Pages: `$capability` aplica a todos los métodos y el framework la comprueba antes de llamar al método. Nuevo `$capabilities` opcional por método. Sin `$capability` se sigue usando `install_plugins`, con aviso en el admin si `WP_DEBUG`.
* Nonce opt-in con `$nonce`. Atajos `wpfw_admin_action_url()` y `wpfw_admin_nonce_field()`; `FormController` y `ListTableController` ya lo incluyen.
* `ListTableController` mantiene las claves con parámetros (`'trash&model=X'`) y escapa celdas y enlaces. `orderby` solo acepta una columna de la fila.
* Query var de método por plugin (`{carpeta}_method`), con `method` todavía aceptado. Las reglas se regeneran una vez al actualizar, al cambiar rutas y al activar el plugin (en `wp_loaded`, no en medio de `init`).
* `HTMLController`, las vistas hoja y la tabla escapan texto, atributos y URLs.
* `template.php` despacha al processor que coincidió con la petición y no recorre los filtros globales. Dos plugins no se pisan.
* Una ruta inexistente o un `Throwable` en el controlador caen en la 404 del tema en lugar de `wp_die`.
* Los PHP del plugin cortan el acceso directo si no existe `ABSPATH`.
* AJAX opcional en `app/Controllers/Ajax`: `$ajaxActions` declara `public`, `capability` y `nonce`. El gancho es `{slug}_{metodo}`.
* Pruebas PHPUnit con Brain Monkey: despacho público por defecto, private/protected, `__call`, capability, `$capabilities`, nonce opt-in, escape, aislamiento, AJAX, shortcode, assets y `Model::describe()`.

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
