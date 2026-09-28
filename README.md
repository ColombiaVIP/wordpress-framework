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

---
### Changelog

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
