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
