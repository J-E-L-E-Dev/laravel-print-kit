# Administración HTTP de catálogos

> Documento histórico de desarrollo; README y documentación vigente prevalecen.
> Describe el estado en su fecha, no el contrato actual de la versión 1.0.0.


Fecha: 8 de octubre de 2026. Backend reutilizable del paquete; la UI permanece en el consumidor.

## Contratos y arquitectura

Se añaden nueve rutas al grupo existente, conservando habilitación opt-in, prefijo, nombres y middleware configurables:

| Recurso | Operaciones | Nombres bajo `print-kit.` |
| --- | --- | --- |
| `/paper-sizes` | GET, POST | `paper-sizes.index`, `paper-sizes.store` |
| `/paper-sizes/{paperSize}` | GET, PUT, DELETE | `paper-sizes.show`, `paper-sizes.update`, `paper-sizes.destroy` |
| `/fields` | GET, POST | `fields.index`, `fields.store` |
| `/fields/{field}` | GET, PUT | `fields.show`, `fields.update` |

No se agrega DELETE de campos. Los controllers reciben FormRequests, utilizan managers y devuelven Resources; no contienen SQL ni dependen de clases del consumidor.

Archivos creados:

- `src/Http/Controllers/{PaperSizesController,PrintFieldsController}.php`.
- `src/Http/Requests/CatalogRequest.php` y seis requests: `PaperSizeIndexRequest`, `StorePaperSizeRequest`, `UpdatePaperSizeRequest`, `PrintFieldIndexRequest`, `StorePrintFieldRequest`, `UpdatePrintFieldRequest`.
- `src/Http/{CatalogManager,PaperSizeManager,PrintFieldManager}.php`.
- `src/Http/Resources/{PaperSizeResource,PrintFieldResource}.php`.
- `src/Support/CatalogRules.php`.
- `tests/Http/CatalogApiTest.php` y este informe.

Archivos modificados: `routes/api.php`, `src/Http/Middleware/ApiErrors.php`, modelos `PaperSize` y `PrintField` (comparten reglas estructurales sin cambiar contrato), `tests/Http/{AliasesApiTest,RoutingApiTest}.php` y README. No se modificaron migraciones, resolvers de datos, renderizadores ni dependencias declaradas.

## Validación y persistencia

La request exige objeto JSON en escrituras: 415 para contenido distinto, 422 para JSON inválido, tipos o atributos no permitidos. Lee el cuerpo JSON original para que middleware de trimming no normalice silenciosamente la identidad. GET recoge exclusivamente filtros definidos. El manager también rechaza claves desconocidas y valida todo el estado antes de casts/guardado. PUT parcial combina atributos existentes; POST aplica defaults configurados.

PaperSize valida nombre requerido de hasta 255, descripción nullable, dimensiones entre 0.0001 y 999999.9999 cm con hasta cuatro decimales y flag booleano. El Resource conserva strings de cuatro decimales. Las propiedades geométricas siguen en cm; `style.font_size` sigue en pt y debe ser positivo.

PrintField valida código de driver registrado; sistema registrado; categoría registrada y habilitada por el sistema, o `*`; identificadores simples para placeholder (máximo 100) y associated_field (máximo 128); nombre requerido (255); descripción nullable; booleano usable_in_detail. No acepta expresiones SQL como campo asociado. `DataType::parse` valida C, N, D, D,0…D,18 y T. Consultar los registries no abre conexiones de origen.

La identidad driver/system/category/placeholder se conserva literalmente. Duplicados, incluyendo ocultos por scopes, producen 422 sobre placeholder. Al editar se excluye el propio id. El índice único protege la carrera entre comprobación y escritura; los errores conocidos de unicidad se traducen a validación.

Todas las operaciones instancian modelos mediante ModelResolver y usan nombres canónicos a través del soporte de alias. No fijan tablas ni columnas físicas. Listado, lectura, edición y borrado respetan scopes de visibilidad. La unicidad y búsqueda de referencias omiten scopes deliberadamente para proteger integridad global. Se guardan instancias, manteniendo hooks del modelo. Se verificaron modelos independientes, alias y conexiones configuradas.

DELETE de papel ejecuta transacción/bloqueo, comprueba referencias usando el modelo Format configurado y devuelve 409 si está usado, incluso por formatos ocultos. La FK es la última defensa ante inserciones concurrentes y sus errores conocidos también devuelven 409. Un papel libre devuelve 204. El consumidor no recibe detalles SQL.

## Listados y proyecciones

PaperSize: search en nombre/descripción, is_active, page y per_page; orden name/id. PrintField: search en placeholder/display_name/description/associated_field, filtros driver/system/category/usable_in_detail, page y per_page; orden driver/system/category/display_name/id. El límite predeterminado es 25, máximo 100, configurable. Se usan bindings y búsquedas agrupadas.

Los Resources exponen solamente atributos administrativos canónicos. `/fields` incluye associated_field; `/options/fields` conserva la proyección segura y resolución exacta/comodín. Un campo exacto no utilizable sigue bloqueando el comodín. `/options/paper-sizes` mantiene el comportamiento de activos y la opción existente para incluir inactivos. No se agregaron consultas por fila ni consultas al origen sqlsrv durante administración.

Ejemplos completos, filtros, respuestas y restricciones están en la sección «Administración de catálogos por HTTP» del README.

## Verificación ejecutada

PHP real utilizado: 8.2.28; Testbench y SQLite en memoria.

| Laravel | PHPUnit | Baseline | Resultado final |
| --- | --- | --- | --- |
| 10.48.0 | 10.5.66 | 417 pruebas / 1.134 aserciones | 454 / 1.475 |
| 11.57.0 | 11.5.57 | 417 / 1.134 | 454 / 1.475 |
| 12.69.3 | 11.5.57 | 417 / 1.134 | 454 / 1.475 |

Son 37 casos y 341 aserciones adicionales, sin regresiones. Cubren CRUD, filtros, paginación, orden, flags, dimensiones inválidas, parser de tipos, registros, categorías, identidad, duplicados, actualización propia, proyecciones seguras, precedencia, referencias ocultas, aliases/modelos independientes/conexiones, scopes y ausencia de acceso al origen. Las nuevas rutas también pasan preparación/serialización de cache.

Composer validate --strict y check-platform-reqs pasaron en cada rama. Pint pasó. El parser para PHP 8.1 verificó 132 archivos y el YAML CI conserva siete combinaciones. Logs locales en `.compat/catalog-baseline*-tests.log` y `.compat/catalog-final*-tests.log`. Se restauraron composer.lock y vendor originales de Laravel 12; composer.json no cambió.

## Límites

- No se ejecutó PHP 8.1 real ni GitHub Actions; la comprobación local de 8.1 fue sintáctica.
- No se conectó a un SQL Server real. Las pruebas de catálogo usan SQLite y verifican que la conexión de origen no se abra.
- Modelos/esquemas personalizados deben conservar índices únicos y FK para garantías ante concurrencia; no hay transacción distribuida entre conexiones distintas.
- Una collation física insensible puede impedir dos identidades que difieran solo en capitalización. No se normalizan placeholders ni se cambia el esquema del consumidor.
- La resolución temporal de Laravel 10/11 usó la excepción de bloqueo de auditoría ya prevista por la matriz para dependencias históricas. No se debilitó configuración permanente de seguridad ni se cambiaron las dependencias finales.
