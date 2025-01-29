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

<<<<<<< HEAD
## Changelog

### [1.0.1] 20250124 First stable :
* Commemted ORM FACADE to reduce load, please use manually on app, see: https://github.com/dimitriBouteille/wp-orm/wiki/DB-facade

### [1.0.0] 20250124 First stable :
=======
### Changelog

## [1.0.1] 20250124 First stable :
* Commemted ORM FACADE to reduce load, please use manually on app, see: https://github.com/dimitriBouteille/wp-orm/wiki/DB-facade

## [1.0.0] 20250124 First stable :
>>>>>>> 9294c19 (20250128 orm facade comented to reduce load)
* Removed custom ORM, start using https://github.com/dimitriBouteille/wp-orm
* Removes 404 controller method, use __call magic method for this.
