# Plan de correcciones y mejoras — WP Framework

Revisión estática del 28 sep 2026 sobre WP Framework 1.3.0 (núcleo en `Fw/`, aplicación de ejemplo en `app/`).

El framework ya sirve como convención MVC para armar plugins rápido. No está listo para generar plugins de terceros: el despacho de métodos está abierto y el HTML sale sin escapar.

18 hallazgos: 1 crítico, 5 altos, 8 medios, 4 bajos.

Orden de trabajo: fase 1 esta semana, fase 2 en el mismo parche, fases 3 a 5 antes de abrir el generador a otros equipos.

Leyenda: `[x]` completado, `[~]` parcial, `[ ]` pendiente.

## Estado al 18 nov 2026

Cierre de seguridad para poder migrar un plugin de aplicación (cvip-loteria). Versión `1.4.0`.

El despacho ya no trata un método `public` como ruta. Cada controlador declara `$webActions` (o el atributo `#[WebAction]`). `index` y `error404` siguen permitidos. El query var `method` global desaparece: con `enableUri` el segmento vive en `{carpeta}_method`. Las acciones de menú (`option`) exigen nonce y capability (`manage_options` si la clase no dice otra). Hay capa AJAX con lista blanca. `template.php` no recorre `$wp_filter`. Una ruta inexistente marca la 404 del tema. `flush_rewrite_rules` corre al activar el plugin. Los PHP del plugin cortan el acceso directo. El contrato para quien arme el plugin de Lotería está en el README.

Sigue abierto: `in_footer` y el handle único de assets, el prefijo `wpfw_` de los helpers globales, la posición de menú 4, text domain, PHPCS, `readme.txt`, `uninstall.php`, PHPStan y GitHub Actions.

## Estado al 28 sep 2026

Pasada de bajo riesgo aplicada. No se tocó el despacho de rutas, los nonces, el escape general del HTML, la capability por defecto ni `flush_rewrite_rules`, porque cambian el comportamiento visible.

Completados en código:

- `WPFW_VERSION` se lee de la cabecera `Version` de `wp-framework.php`. La versión actual es `1.3.1`.
- `composer.json`: nombre `colombiavip/wordpress-framework`, licencia `GPL-2.0-or-later`, descripción sin bytes nulos.
- `MenuPagesManager`: `array_search` comparado con `!== false`.
- `Apps::setConfig` escribe en `$name` y ya no usa la variable inexistente `$configName`.
- `ResponseView`: `extract()` con `EXTR_SKIP`.
- `LoadAssets`: los casos `js` y `css` usan `$this->args`.
- `printVars` inicializa `$vars` y solo emite identificadores válidos.
- `spaceUpper`, `strToSlug` y `routeUrl` quedan detrás de `function_exists`.
- `HTMLController::textArea` ya no llama a `consoleLog`.
- El generador escapa el nombre del plugin con `esc_html`.
- `Model::describe()` solo acepta un nombre de tabla `[A-Za-z0-9_]+`.

## Qué conviene conservar

- **Descubrimiento por convención.** `Routes`, `MenuPages` y `Shortcodes` se registran al leer el sistema de archivos. Un controlador nuevo aparece sin un array de rutas.
- **Ciclo petición → respuesta.** `Request`, `ResponseView` y `ResponseAsset` separan el controlador de cómo se pinta. `view()` anida layouts sin un motor de plantillas.
- **Registro multi-plugin.** `Apps` guarda config y paths por slug. El diseño ya contempla más de un plugin, aunque la plantilla de ruta todavía no.
- **Contrato de menú por propiedades.** `pageTitle`, `capability`, `menuSlug` e `icon` viven en la clase. El andamiaje de `add_menu_page` está resuelto.
- **Generador de plugin.** `BasePlugin` copia `templateApp`, renombra el bootstrap y sustituye el namespace.
- **Temas de bloques y clásicos.** `getPart` elige `block_template_part` o `get_header` / `get_footer`.
- **PHP 8.3 en el núcleo.** Tipos, propiedades promovidas y `match` en helpers. La base admite análisis estático sin reescribir el estilo.

## Hallazgos

### Crítico

1. **[x] Completado en 1.4.0. Cualquier método público de una ruta se puede invocar sin comprobar visibilidad.** `RequestWeb::validations`, `Router::compile`. La comprobación `isPublic` está comentada. El query var `method` se registra siempre, así que `?method=accion` ejecuta el método aunque `enableUri` sea `false`. No hay autenticación ni nonce en el front.

### Alto

2. **[x] Completado en 1.4.0. Las acciones de administración viajan por GET sin nonce.** `RequestMenuPage`, `FormController`, `ListTableController`. `option`, `page` y los enlaces de editar/borrar se arman con `$_GET` / `$_REQUEST`. Un admin autenticado puede dispararlas por CSRF. Los formularios generados tampoco incluyen `wp_nonce_field`.
3. **[x] Completado en 1.4.0. La salida HTML no se escapa.** `printVars` ya no concatena claves crudas; el resto de vistas y formularios sigue sin escapar. `HTMLController`, `layout.php`, `ListTableController`, `printVars`. Nombres, valores, clases y celdas se imprimen con `<?= ?>`. `ListTable` refleja `page` y datos de fila. `printVars` concatena claves en JavaScript.
4. **[x] Completado en 1.4.0. Un shortcode ejecuta cualquier método público de su carpeta.** `RequestShortcode::prepare`. Quien pueda guardar el shortcode elige `Categoria=Clase@metodo` y pasa atributos al constructor. La categoría solo pasa por `sanitize_text_field`, que no limita el nombre a un identificador.
5. **[x] Completado en 1.4.0. La plantilla de ruta no distingue qué plugin respondió.** `Fw/Init/Routing/template.php`. Recorre `$wp_filter` y usa el primer `RoutingProcessor`. Con dos plugins del framework, la URL de uno puede ejecutar el request del otro.
6. **[x] Completado. `extract()` en las vistas puede pisar variables del renderer.** Quedó `EXTR_SKIP` en `sendView` y en `response`. `ResponseView::sendView` y `response`. Un argumento llamado `viewPath`, `parameters` o `args` sobrescribe el ámbito local antes del `require`.

### Medio

7. **[x] Completado en 1.4.0. `flush_rewrite_rules` corre en `init` y el tag `method` es global.** `RoutingProcessor::registerRoutes`, `Router::compile`. Cada cambio de hash reescribe las reglas en caliente, también para visitantes. El tag `%method%` choca con cualquier otro plugin que use ese query var.
8. **[~] Parcial. La carga selectiva de assets está rota y es incondicional.** `LoadAssets::loadAsset`. Los casos `js` y `css` ya usan `$this->args`. Sigue pendiente honrar `in_footer` (la config pide `false` y hoy los scripts cargan en el footer; cambiarlo los movería al head), la carga en cada página y el handle único.
9. **[~] Parcial. La capability por defecto es `install_plugins` y la posición es 4.** La capability por defecto pasó a `manage_options`. La posición sigue en 4. `MenuPagesManager::prepareMenuPage`. En multisitio el menú queda oculto para administradores de sitio. La posición 4 compite con los menús nativos de WordPress.
10. **[~] Parcial. Helpers en el espacio global, sin prefijo ni `function_exists`.** `spaceUpper`, `strToSlug` y `routeUrl` ya tienen `function_exists`. Sigue pendiente el prefijo `wpfw_` (renombrarlos rompería las llamadas actuales) y sacar `getHeader` / `getFooter` del guard de `getPart`.
11. **[x] Completado. Versión, licencia y metadatos de Composer no coinciden.** `WPFW_VERSION` sale de la cabecera `Version` (ahora `1.3.1`). Composer usa `colombiavip/wordpress-framework`, licencia `GPL-2.0-or-later` y la descripción ya no trae bytes nulos.
12. **[x] Completado. `array_search` trata el índice 0 como fallo.** `MenuPagesManager::prepare` compara el resultado con `!== false`.
13. **[x] Completado en 1.4.0. Una ruta inexistente tumba la petición con `wp_die`.** `RoutingProcessor::matchRequest`. `route_not_found` no cae en la 404 del tema. Las excepciones que no son `General` (método privado, `TypeError`) tampoco se capturan y dejan pantalla blanca.
14. **[~] Parcial. No hay pruebas, estándar de código ni guard `ABSPATH`.** Hay PHPUnit (Brain Monkey) y el guard `ABSPATH`. Siguen pendientes PHPStan, PHPCS, CI, `readme.txt`, `uninstall.php` y el text domain. No hay PHPUnit, PHPStan, PHPCS, CI, `readme.txt` ni `uninstall.php`. Solo `requirements.php` corta el acceso directo. El texto de interfaz no tiene text domain.

### Bajo

15. **[x] Completado. `printVars` usa `$vars` antes de definirla.** `$vars` se inicializa y la clave solo se imprime si es un identificador JavaScript válido.
16. **[x] Completado. `setConfig` escribe en una variable que no existe.** El ramal del slug asigna `$name` sobre la app ya registrada.
17. **[x] Completado. `describe()` concatena el nombre de tabla en SQL.** Si el nombre no es `[A-Za-z0-9_]+`, el método devuelve un array vacío y no arma el SQL.
18. **[~] Parcial. Quedan depuración y bloques comentados en el flujo principal.** `HTMLController::textArea` ya no llama a `consoleLog`. Siguen los bloques comentados en `MenuPagesManager` y `RequestWeb`.

## Contrato que hay que documentar

Quedó escrito en el README (versión 1.4.0), sección «Contrato de un plugin de aplicación». Resumen:

- Un método `public` no es una ruta. Solo lo es si está en `$webActions` o lleva `#[WebAction]`. `index` y `error404` van implícitos.
- La vista hoja escapa sus datos. El hueco `$content` de un layout es HTML ya renderizado por `view()`.
- Toda acción de menú que llega con `option` lleva nonce (`_wpfw_nonce`) y la capability del controlador.
- Cada acción AJAX declara si es pública, su capability y su nonce. No hay includes a partir de la petición.

## Fase 1 — Cerrar el despacho y el HTML

Bloqueante. Hacerla antes de cualquier otra fase.

- [x] Restaurar la comprobación `isPublic` en `RequestWeb` y limitar los métodos de ruta a una lista explícita (`index`, `error404` y los que la clase declare).
- [x] Quitar el query var `method` global y prefijarlo por plugin.
- [x] Exigir nonce y una capability concreta en `option`, en `FormController` y en cada acción de `ListTableController`.
- [x] Escapar con `esc_html`, `esc_attr` y `esc_url` en `HTMLController`, las vistas y la tabla.
- [x] Sanitizar `option`, `orderby` y la categoría del shortcode como identificador.
- [x] Usar `EXTR_SKIP` en `ResponseView`.
- [x] Borrar el `consoleLog` de `textArea` y corregir `printVars`.

Cubre los hallazgos 1, 2, 3, 4, 6, 15 y 18.

## Fase 2 — Bugs que ya cambian el comportamiento

Mismo parche que la fase 1, en cuanto el despacho quede cerrado.

- [x] `WPFW_VERSION` se lee de la cabecera `Version` del plugin.
- [x] Unificar la licencia en `GPL-2.0-or-later`.
- [x] Corregir el nombre del paquete Composer y quitar los bytes nulos de la descripción.
- [~] En `LoadAssets`, usar el array `args` de la instancia. Pendiente: honrar `in_footer` y generar handles con el slug del plugin.
- [x] Comparar `array_search` con `!== false`.
- [x] En `Apps::setConfig` asignar `$name`.
- [x] Capturar `Throwable` y, si la ruta no existe, devolver la 404 del tema en lugar de `wp_die`.
- [x] Pasar el request a `template.php` sin recorrer `$wp_filter`.
- [x] Capability por defecto `manage_options`.
- [x] Mover `flush_rewrite_rules` a la activación del plugin.
- [x] Añadir el guard `ABSPATH` al resto de archivos PHP.
- [x] Escapar el nombre en el generador de plugins, que ya verifica el nonce.

Cubre los hallazgos 5, 7, 8, 9, 11, 12, 13 y 16.

## Fase 3 — Un solo ciclo de petición

- [ ] Unificar rutas, menús y shortcodes en resolver, autorizar, validar, invocar y responder.
- [ ] El mapa de métodos permitidos sale de la clase, no de un string armado con datos del usuario.
- [ ] Cada plugin conserva su propio processor.
- [ ] Añadir parámetros de ruta y un gancho de middleware (capacidad, nonce, usuario logueado).
- [ ] Soportar respuesta JSON además de vista.
- [ ] Cargar CSS y JS solo en la pantalla del controlador que los declara.

## Fase 4 — Estándar de un plugin de WordPress

- [ ] Text domain único y cadenas con `__()`.
- [~] `function_exists` en `spaceUpper`, `strToSlug` y `routeUrl`. Pendiente: prefijo `wpfw_` (renombrar rompe las llamadas actuales).
- [ ] PHPCS con WordPress-Extra.
- [ ] `readme.txt` y `uninstall.php`.
- [ ] Borrar el código comentado que contradice el flujo.
- [x] Dejar escrito el contrato de la sección anterior en la documentación del framework.

Cubre los hallazgos 10 y 14 (la parte de estándar e i18n).

## Fase 5 — Red de seguridad

- [x] PHPUnit (Brain Monkey o wp-env) para el despacho de rutas, el rechazo de métodos privados, el nonce de menú, el escape de vistas y el aislamiento entre dos plugins.
- [ ] PHPStan en nivel 6 sobre `Fw/`.
- [ ] GitHub Actions en cada push.

Primera batería de casos, tomada de esta revisión:

- [x] `method` por query string con `enableUri` en `false`.
- [x] `option` sin nonce.
- [x] Índice 0 al elegir el controlador de menú.
- [x] Carga con `load => js` y `load => css`.
- [x] Dos `RoutingProcessor` a la vez.

Cubre el hallazgo 14 (la parte de pruebas y CI). El hallazgo 17 (`Model::describe()`) ya está cerrado en código; falta el test.

## Fase 6 — Producto

Después de las fases 1 a 5.

- [ ] Verbos HTTP, parámetros en la URL y factories para no hacer `new $clase` dentro del framework.
- [ ] Dejar `dbout/wp-orm` como dependencia opcional: el `Model` actual solo añade `describe()`.
- [x] Cuando el generador copie `templateApp`, el plugin resultante tiene que nacer ya con nonce, escape y la lista de acciones. Si el molde sigue el patrón inseguro, cada plugin nuevo hereda los hallazgos de la fase 1. El molde declara `$webActions`, escapa las vistas, documenta el nonce de `option` y trae un controlador AJAX con lista blanca.
