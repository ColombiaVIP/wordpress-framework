# Plan de correcciones y mejoras — WP Framework

Revisión estática del 28 sep 2026 sobre WP Framework 1.3.0 (núcleo en `Fw/`, aplicación de ejemplo en `app/`).

El framework ya sirve como convención MVC para armar plugins rápido. No está listo para generar plugins de terceros: el despacho de métodos está abierto y el HTML sale sin escapar.

18 hallazgos: 1 crítico, 5 altos, 8 medios, 4 bajos.

Orden de trabajo: fase 1 esta semana, fase 2 en el mismo parche, fases 3 a 5 antes de abrir el generador a otros equipos.

Leyenda: `[x]` completado, `[~]` parcial, `[ ]` pendiente.

## Estado al 28 sep 2026

Pasada de bajo riesgo aplicada. No se tocó el despacho de rutas, los nonces, el escape general del HTML, la capability por defecto ni `flush_rewrite_rules`, porque cambian el comportamiento visible.

Completados en código:

- `WPFW_VERSION` queda en `1.3.0`, igual que la cabecera del plugin.
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

1. **[ ] Pendiente. Cualquier método público de una ruta se puede invocar sin comprobar visibilidad.** `RequestWeb::validations`, `Router::compile`. La comprobación `isPublic` está comentada. El query var `method` se registra siempre, así que `?method=accion` ejecuta el método aunque `enableUri` sea `false`. No hay autenticación ni nonce en el front.

### Alto

2. **[ ] Pendiente. Las acciones de administración viajan por GET sin nonce.** `RequestMenuPage`, `FormController`, `ListTableController`. `option`, `page` y los enlaces de editar/borrar se arman con `$_GET` / `$_REQUEST`. Un admin autenticado puede dispararlas por CSRF. Los formularios generados tampoco incluyen `wp_nonce_field`.
3. **[ ] Pendiente. La salida HTML no se escapa.** `printVars` ya no concatena claves crudas; el resto de vistas y formularios sigue sin escapar. `HTMLController`, `layout.php`, `ListTableController`, `printVars`. Nombres, valores, clases y celdas se imprimen con `<?= ?>`. `ListTable` refleja `page` y datos de fila. `printVars` concatena claves en JavaScript.
4. **[ ] Pendiente. Un shortcode ejecuta cualquier método público de su carpeta.** `RequestShortcode::prepare`. Quien pueda guardar el shortcode elige `Categoria=Clase@metodo` y pasa atributos al constructor. La categoría solo pasa por `sanitize_text_field`, que no limita el nombre a un identificador.
5. **[ ] Pendiente. La plantilla de ruta no distingue qué plugin respondió.** `Fw/Init/Routing/template.php`. Recorre `$wp_filter` y usa el primer `RoutingProcessor`. Con dos plugins del framework, la URL de uno puede ejecutar el request del otro.
6. **[x] Completado. `extract()` en las vistas puede pisar variables del renderer.** Quedó `EXTR_SKIP` en `sendView` y en `response`. `ResponseView::sendView` y `response`. Un argumento llamado `viewPath`, `parameters` o `args` sobrescribe el ámbito local antes del `require`.

### Medio

7. **[ ] Pendiente. `flush_rewrite_rules` corre en `init` y el tag `method` es global.** `RoutingProcessor::registerRoutes`, `Router::compile`. Cada cambio de hash reescribe las reglas en caliente, también para visitantes. El tag `%method%` choca con cualquier otro plugin que use ese query var.
8. **[~] Parcial. La carga selectiva de assets está rota y es incondicional.** `LoadAssets::loadAsset`. Los casos `js` y `css` ya usan `$this->args`. Sigue pendiente honrar `in_footer` (la config pide `false` y hoy los scripts cargan en el footer; cambiarlo los movería al head), la carga en cada página y el handle único.
9. **[ ] Pendiente. La capability por defecto es `install_plugins` y la posición es 4.** `MenuPagesManager::prepareMenuPage`. En multisitio el menú queda oculto para administradores de sitio. La posición 4 compite con los menús nativos de WordPress.
10. **[~] Parcial. Helpers en el espacio global, sin prefijo ni `function_exists`.** `spaceUpper`, `strToSlug` y `routeUrl` ya tienen `function_exists`. Sigue pendiente el prefijo `wpfw_` (renombrarlos rompería las llamadas actuales) y sacar `getHeader` / `getFooter` del guard de `getPart`.
11. **[x] Completado. Versión, licencia y metadatos de Composer no coinciden.** `WPFW_VERSION` es `1.3.0`. Composer usa `colombiavip/wordpress-framework`, licencia `GPL-2.0-or-later` y la descripción ya no trae bytes nulos.
12. **[x] Completado. `array_search` trata el índice 0 como fallo.** `MenuPagesManager::prepare` compara el resultado con `!== false`.
13. **[ ] Pendiente. Una ruta inexistente tumba la petición con `wp_die`.** `RoutingProcessor::matchRequest`. `route_not_found` no cae en la 404 del tema. Las excepciones que no son `General` (método privado, `TypeError`) tampoco se capturan y dejan pantalla blanca.
14. **[ ] Pendiente. No hay pruebas, estándar de código ni guard `ABSPATH`.** No hay PHPUnit, PHPStan, PHPCS, CI, `readme.txt` ni `uninstall.php`. Solo `requirements.php` corta el acceso directo. El texto de interfaz no tiene text domain.

### Bajo

15. **[x] Completado. `printVars` usa `$vars` antes de definirla.** `$vars` se inicializa y la clave solo se imprime si es un identificador JavaScript válido.
16. **[x] Completado. `setConfig` escribe en una variable que no existe.** El ramal del slug asigna `$name` sobre la app ya registrada.
17. **[x] Completado. `describe()` concatena el nombre de tabla en SQL.** Si el nombre no es `[A-Za-z0-9_]+`, el método devuelve un array vacío y no arma el SQL.
18. **[~] Parcial. Quedan depuración y bloques comentados en el flujo principal.** `HTMLController::textArea` ya no llama a `consoleLog`. Siguen los bloques comentados en `MenuPagesManager` y `RequestWeb`.

## Contrato que hay que documentar

- Un método `public` no es una ruta. Solo lo es si está en la lista de acciones.
- La vista nunca imprime una variable cruda.
- Toda acción que cambia estado lleva nonce y capability.

## Fase 1 — Cerrar el despacho y el HTML

Bloqueante. Hacerla antes de cualquier otra fase.

- [ ] Restaurar la comprobación `isPublic` en `RequestWeb` y limitar los métodos de ruta a una lista explícita (`index`, `error404` y los que la clase declare).
- [ ] Quitar el query var `method` global y prefijarlo por plugin.
- [ ] Exigir nonce y una capability concreta en `option`, en `FormController` y en cada acción de `ListTableController`.
- [ ] Escapar con `esc_html`, `esc_attr` y `esc_url` en `HTMLController`, las vistas y la tabla.
- [ ] Sanitizar `option`, `orderby` y la categoría del shortcode como identificador.
- [x] Usar `EXTR_SKIP` en `ResponseView`.
- [x] Borrar el `consoleLog` de `textArea` y corregir `printVars`.

Cubre los hallazgos 1, 2, 3, 4, 6, 15 y 18.

## Fase 2 — Bugs que ya cambian el comportamiento

Mismo parche que la fase 1, en cuanto el despacho quede cerrado.

- [x] Igualar `WPFW_VERSION` a `1.3.0`.
- [x] Unificar la licencia en `GPL-2.0-or-later`.
- [x] Corregir el nombre del paquete Composer y quitar los bytes nulos de la descripción.
- [~] En `LoadAssets`, usar el array `args` de la instancia. Pendiente: honrar `in_footer` y generar handles con el slug del plugin.
- [x] Comparar `array_search` con `!== false`.
- [x] En `Apps::setConfig` asignar `$name`.
- [ ] Capturar `Throwable` y, si la ruta no existe, devolver la 404 del tema en lugar de `wp_die`.
- [ ] Pasar el request a `template.php` sin recorrer `$wp_filter`.
- [ ] Capability por defecto `manage_options`.
- [ ] Mover `flush_rewrite_rules` a la activación del plugin.
- [ ] Añadir el guard `ABSPATH` al resto de archivos PHP.
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
- [ ] Dejar escrito el contrato de la sección anterior en la documentación del framework.

Cubre los hallazgos 10 y 14 (la parte de estándar e i18n).

## Fase 5 — Red de seguridad

- [ ] PHPUnit (Brain Monkey o wp-env) para el despacho de rutas, el rechazo de métodos privados, el nonce de menú, el escape de vistas y el aislamiento entre dos plugins.
- [ ] PHPStan en nivel 6 sobre `Fw/`.
- [ ] GitHub Actions en cada push.

Primera batería de casos, tomada de esta revisión:

- [ ] `method` por query string con `enableUri` en `false`.
- [ ] `option` sin nonce.
- [ ] Índice 0 al elegir el controlador de menú.
- [ ] Carga con `load => js` y `load => css`.
- [ ] Dos `RoutingProcessor` a la vez.

Cubre el hallazgo 14 (la parte de pruebas y CI). El hallazgo 17 (`Model::describe()`) ya está cerrado en código; falta el test.

## Fase 6 — Producto

Después de las fases 1 a 5.

- [ ] Verbos HTTP, parámetros en la URL y factories para no hacer `new $clase` dentro del framework.
- [ ] Dejar `dbout/wp-orm` como dependencia opcional: el `Model` actual solo añade `describe()`.
- [ ] Cuando el generador copie `templateApp`, el plugin resultante tiene que nacer ya con nonce, escape y la lista de acciones. Si el molde sigue el patrón inseguro, cada plugin nuevo hereda los hallazgos de la fase 1.
