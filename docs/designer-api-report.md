# Informe de la API backend del diseñador

> Documento histórico de desarrollo; README y documentación vigente prevalecen.
> Describe el estado en su fecha, no el contrato actual de la versión 1.0.0.


Fecha: 6 de octubre de 2026. Paquete `ahinest/laravel-print-kit`.
La fase agrega una API opcional para formatos, catálogos, validación y preview
HTML de etiquetas ET. `consult-web` permanece sin cambios.

## 1. Archivos creados y modificados

Archivos creados (24):

```text
routes/api.php
src/Http/Controllers/ApiController.php
src/Http/Controllers/FormatsController.php
src/Http/Controllers/OptionsController.php
src/Http/Controllers/DesignController.php
src/Http/Requests/ApiInput.php
src/Http/Resources/FormatResource.php
src/Http/Resources/PaperSizeOptionResource.php
src/Http/Resources/RegistryOptionResource.php
src/Http/Resources/FieldOptionResource.php
src/Http/Resources/OptionResponse.php
src/Http/Middleware/ApiErrors.php
src/Http/FormatManager.php
src/Support/PaperSizeResolver.php
src/Support/InvalidPaperSize.php
tests/Http/ApiTestCase.php
tests/Http/RoutingApiTest.php
tests/Http/FormatsApiTest.php
tests/Http/OptionsApiTest.php
tests/Http/DesignApiTest.php
tests/Http/AliasesApiTest.php
tests/Http/StorageApiTest.php
tests/Fixtures/ApiGate.php
docs/designer-api-report.md
```

Archivos modificados (10):

```text
composer.json
composer.lock
config/print-kit.php
src/PrintKitServiceProvider.php
src/Design/LabelDesignValidator.php
src/Rendering/LabelRenderContextFactory.php
src/Data/Exceptions/MissingProvidedValue.php
src/Data/ProvidedValuesResolver.php
src/Data/ResolvedLabel.php
README.md
```

Composer declara directamente Illuminate HTTP, Routing y Pagination con los
mismos rangos 10/11/12. El lock final conserva Laravel 12.69.3. Las migraciones,
el esquema, los renderizadores puros y la matriz CI existente no cambian.
Los logs, locks de comprobación y el verificador PHP/YAML están en `.compat/`,
ya ignorado; no son entregables del paquete. No existe repositorio Git en la
carpeta del paquete, por lo que no se creó commit ni PR.

## 2. Configuración API

`enabled=false`, `prefix=print-kit`, `middleware=['api']`, `per_page=25`,
`max_per_page=100`. El consumidor habilita y protege los endpoints. Una lista
explícita de middleware reemplaza el default, incluso si está vacía.
El provider carga las rutas únicamente cuando enabled es true; la comprobación
en ejecución también deshabilita rutas conservadas en un cache anterior.
Publicación mediante el tag existente `print-kit-config`; no se publican controllers.

## 3. Rutas

Todas tienen prefijo configurable; sus nombres llevan `print-kit.`:

| Método | Ruta relativa | Nombre |
| --- | --- | --- |
| GET | formats | formats.index |
| POST | formats | formats.store |
| GET | formats/{format} | formats.show |
| PUT | formats/{format} | formats.update |
| DELETE | formats/{format} | formats.destroy |
| GET | options/drivers | options.drivers |
| GET | options/systems | options.systems |
| GET | options/categories | options.categories |
| GET | options/paper-sizes | options.paper-sizes |
| GET | options/fields | options.fields |
| POST | design/validate | design.validate |
| POST | preview | preview |
| POST | formats/{format}/preview | formats.preview |

Son 13 rutas con acciones de controller serializables, sin closures como acciones
ni binding rígido a PrintFormat. No se añade /v1 ni /api automáticamente.

## 4. Controllers

FormatsController delega escrituras a FormatManager y limita la consulta de
listado. OptionsController usa registries, ModelResolver y PrintFieldResolver.
DesignController comparte el pipeline de borrador/guardado.
ApiController captura errores de las acciones y de la conversión de Resources
antes de que el pipeline de Laravel los entregue al handler global.
No registra manejadores globales que alteren rutas del consumidor.

## 5. Requests

ApiInput es el validador dedicado para store/update/draft, ambos previews y
filtros/opciones. Usa Factory de Laravel con una lista explícita de propiedades;
no usa request->all(). Rechaza propiedades corporales desconocidas y campos de
solo lectura. POST/PUT exigen un objeto JSON, con 415 para Content-Type incorrecto
y 422 para JSON inválido. Los filtros GET desconocidos se ignoran.
Lee el JSON original para conservar valores finales frente a trims globales.

## 6. Resources

FormatResource diferencia resumen y detalle; el resumen ni selecciona detail
en SQL ni carga papeles. PaperSizeOptionResource, RegistryOptionResource y
FieldOptionResource proyectan contratos seguros. OptionResponse ordena por label
en minúsculas Unicode y desempata por value textual, con comparación binaria estable.
JSON usa data; paginación usa links/meta estándar. Las medidas de papel se
serializan como cadenas decimales de cuatro posiciones.

## 7. Servicio de formatos

FormatManager implementa find/create/update/delete/temporary/validate/preview.
Resuelve modelos configurados, aplica atributos permitidos, valida registries y
diseño, controla unicidad y usa save/delete de instancia. No hace mass update.
Preview usa directamente ProvidedValuesResolver y los adaptadores/renderizadores
existentes: no necesita construir LabelResolver con sus resolvers de origen.

## 8. Construcción temporal

Se crea el modelo configurado, se completan defaults ausentes y se asignan
atributos validados sin guardar. Funciona con modelos independientes que cumplan
el contrato. Code/name no son obligatorios para borradores; detail sí.
PaperSizeResolver comparte el papel explícito entre dominio y render, verificando
clase, entidad, id y conexión. Cambiar paper_size_id descarta la relación anterior.

## 9. CRUD

Create retorna 201, show/update 200 con detalle y delete 204 sin cuerpo.
PUT permite cambios parciales; atributos JSON enviados se reemplazan completos.
Code es único en tabla/conexión configurada, con alias y exclusión del propio id.
La comprobación global omite scopes para coincidir con el índice; una colisión
detectada después de un fallo de escritura también se convierte en validación.
Se preservan save hooks y validación de dominio de los modelos.
Disabled no impide lectura/edición/borrado. Delete conserva el papel.
Printer/use_default_printer se administran sin comprobar impresoras.
Parameters acepta objeto/array JSON/null sin interpretarlo.
Version es visible y no editable por HTTP; no se incrementa automáticamente.

## 10. Paginación y filtros

Listado ordenado por id ascendente, 25 por defecto y máximo 100 configurable.
Filtros system, category=ET, disabled=0|1 y search por code/name con LIKE y bindings.
Search conserva los comodines % y _. El listado incluye disabled por defecto.
No hay query language ni consultas por elemento para cargar papeles.

## 11. Options drivers

DriverRegistry, cero consultas: value/label/description. No comprueba ni abre
la conexión de origen. Orden estable por etiqueta.

## 12. Options systems

SystemRegistry, cero consultas: value/label/description/categories.
No expone table/key físicos.

## 13. Options categories

CategoryRegistry, cero consultas: value/label/description, sin wildcard como
categoría de formato. Las registries pueden contener futuras categorías;
el diseñador valida ET exclusivamente.

## 14. Options paper sizes

Un query al modelo configurado, activos por defecto, include_inactive=1 opcional.
Proyecta value/label/description/width/height. Los formatos pueden referenciar
papeles inactivos; el filtro se aplica solo al selector normal.

## 15. Options fields

Requiere driver/system/category=ET. PrintFieldResolver::fieldsFor ejecuta una
consulta y aplica exacto sobre wildcard. El filtro usable_in_detail se aplica
después: un exacto no utilizable no deja reaparecer el wildcard.
Devuelve value/label/description/data_type, sin associated_field, tablas ni
drivers físicos; no duplica placeholders.

## 16. Design validation

POST design/validate crea el temporal y reutiliza LabelDesign/LabelDesignValidator.
Valida esquema, geometría, márgenes y catálogo de campos sin persistir.
Responde valid=true o 422 con paths de dominio. Papel inexistente produce 404.
Mantiene cm para geometría y font_size numérico positivo en pt para tipografía.

## 17. Preview sin guardar

POST preview recibe format + values finales. Resuelve el papel una vez y valida
el diseño/catálogo; ProvidedValuesResolver realiza cero consultas adicionales.
LabelRenderContextFactory, LabelHtmlRenderer y HtmlDocumentRenderer producen HTML
completo con Content-Type text/html; charset=UTF-8, sin attachment.
No guarda formato, no llama al origen, formatter ni transformers.
Values faltantes producen 422 en values.PLACEHOLDER; null se acepta.
Conserva espacios, cadenas vacías, decimales textuales, booleanos y Unicode.

## 18. Preview guardado

POST formats/{format}/preview acepta únicamente values. Comparte el mismo
FormatManager.preview, sin cambiar el registro. Formato inexistente es 404.
Con copia normal hace formato + papel + catálogo. Si el servicio recibe el
papel ya cargado, lo reutiliza y solo consulta el catálogo para diseños con fields.
Copies no multiplica páginas, incluso con copies=5; disabled no bloquea preview.

## 19. Errores

404 para formato/papel inexistente; 422 para entrada, diseño, registries,
unicidad y valores; 415 para contenido no JSON. No se introdujo 409.
InvalidDesign conserva su path; preview temporal agrega format. al path del diseño.
MissingProvidedValue ahora incluye el placeholder estructurado sin parsear mensajes.
Los errores de acciones son JSON incluso con Accept text/html. Un fallo inesperado
se registra y responde 500 genérico sin stack trace/SQL aun con debug=true.
El consumidor sigue manejando sus middleware y el resto de sus rutas.

## 20. Modelos, tablas, alias y conexiones

Toda búsqueda pasa por ModelResolver y builders canónicos. Las pruebas usan
format/field/paper independientes, tres tablas renombradas y alias físicos de
id/code/name/detail/paper_size_id y campos de catálogo. CRUD, paginación,
unique, options, validate y previews funcionan con esos sustitutos.
Se comprueba que una conexión default vacía no reciba consultas si el
almacenamiento del paquete está configurado en otra conexión.
Los scopes limitan lectura y mutaciones; la unicidad se contrasta globalmente.
Los modelos sustitutos deben ofrecer casts del contrato para atributos JSON.

## 21. Seguridad

API desactivada por defecto; autenticación/autorización, CSRF, CORS y throttle
son responsabilidad del consumidor mediante middleware. No depende de Sanctum,
Spatie o permisos propios. Las entradas pasan por whitelist y validadores.
No se expone DatabaseDataResolver por HTTP: driver/key/record se rechazan en preview.
Textos y values se escapan; style rechaza propiedades arbitrarias e inyección CSS.
HTML sin scripts/print(). Estilos inline documentados, sin imponer CSP ni iframe.

## 22. Consultas medidas

Con SQLite y modelos predeterminados, excluyendo middleware propio:

| Operación | Consultas |
| --- | --- |
| Options drivers/systems/categories | 0 |
| Options paper sizes | 1 |
| Options fields | 1 al catálogo |
| Index no vacío | 2, sin detail/papeles |
| Show | 2: formato + papel |
| Validate/preview temporal con fields | 2: papel + catálogo |
| Preview temporal solo texto | 1: papel |
| Preview guardado con fields | 3: formato + papel + catálogo |
| Servicio preview con papel cargado y fields | 1: catálogo |

Todos los previews: origen 0, formatter/transformers 0, escrituras 0.
Las escrituras CRUD conservan save hooks y pueden repetir validación de
papel/catálogo; no se desactiva una validación de dominio para reducir esa cifra.

## 23. Pruebas nuevas

Se agregan 69 casos y 418 aserciones a la base de 348/716. Cubren las 13 rutas
serializables, activación/desactivación, prefijo/middleware, defaults y paginación,
CRUD, unicidad, entradas inválidas, options/precedencia, geometría, XSS/CSS,
preview temporal/guardado, conteos SQL, preservación de valores finales, aliases,
modelos independientes, conexiones, scopes y papel precargado/cambiado.
Fixtures aíslan el almacenamiento y bloquean resolvers/transformaciones de origen.

## 24. Total final y comprobaciones

**417 pruebas, 1.134 aserciones**, correctas en las tres ramas.
Composer validate --strict y check-platform-reqs pasaron en Laravel 10, 11 y 12.
Pint --test pasó en el entorno final. El parser configurado para PHP 8.1 aceptó
los 116 archivos PHP de src/config/database/tests/examples/routes. El YAML CI
se parseó correctamente y conserva siete combinaciones. También se verificó
por DOM el ejemplo HTML anterior: tres etiquetas, tamaño físico, UTF-8 y escaping.
El lock final y vendor corresponden a Laravel 12.69.3; Composer audit no encontró
avisos de vulnerabilidad en ese entorno.

## 25. Resultados Laravel 10/11/12

Ejecuciones reales con PHP 8.2.28 y SQLite en memoria:

| Framework | PHPUnit | Pruebas | Aserciones | Resultado |
| --- | --- | --- | --- | --- |
| Laravel 10.48.0 | 10.5.66 | 417 | 1.134 | Correcto |
| Laravel 11.57.0 | 11.5.57 | 417 | 1.134 | Correcto |
| Laravel 12.69.3 | 11.5.57 | 417 | 1.134 | Correcto |

La resolución temporal de Laravel 10/11 usó la excepción de bloqueo de seguridad
ya prevista para probar ramas antiguas (6 y 4 avisos, respectivamente, en sus
dependencias). No se desactivó la política de seguridad en composer.json.
Se restauró el lock de Laravel 12 con las nuevas dependencias HTTP declaradas.
Los archivos api-laravel10/11/12-tests.log de .compat conservan las salidas.

## 26. Limitaciones actuales

Solo etiquetas ET y los elementos field/text ya soportados. No hay UI Vue,
Tailwind, canvas, drag/drop, zoom, modal ni iframe; tampoco PDF, impresión física,
descubrimiento de impresoras, QZ Tray/ZPL, barcode/QR, imágenes, colores,
familia tipográfica configurable, alineación vertical, facturas o diseños multipágina.
No se agregan caches, muestras automáticas, resolución HTTP por driver/key,
control de concurrencia optimista ni un paquete de permisos.
La ejecución local usa PHP 8.2.28 y SQLite; la sintaxis PHP 8.1 se verifica por
parser, no mediante ejecución local en ese runtime. GitHub Actions no se lanzó
en esta sesión. MySQL/PostgreSQL/SQL Server y rendering visual de navegador no
se ejecutaron. El frontend podrá consumir HTML con fetch y srcdoc/Blob en una
fase posterior. El README incluye los contratos y ejemplos completos.
