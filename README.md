# Laravel Print Kit

Paquete MIT `ahinest/laravel-print-kit`, namespace `Ahinest\LaravelPrintKit`.
Incluye persistencia configurable, diseño, resolución de datos, renderizado HTML
de etiquetas con medidas físicas y una API opcional para el diseñador.
Produce documentos para preview e impresión desde navegador.
No incluye interfaz visual, PDF ni impresión directa.

## Compatibilidad e instalación

| Framework admitido | PHP compatible del framework | Testbench | PHPUnit |
| --- | --- | --- | --- |
| Laravel 10.48 o posterior de la rama 10 | 8.1, 8.2, 8.3 | 8 | 10.5 |
| Laravel 11 | 8.2, 8.3, 8.4 | 9 | 10.5 o 11.5 |
| Laravel 12 | 8.2, 8.3, 8.4 | 10 | 11.5 |

Composer exige PHP `^8.1` e Illuminate `^10.48 || ^11.0 || ^12.0`.
Laravel 11/12 exigen PHP ≥ 8.2 mediante sus propias dependencias.
No se fija `config.platform.php`. El formatter usa `brick/math ^0.12 || ^0.13 || ^0.14`
como dependencia directa para preservar la precisión de cadenas decimales.

El mínimo Laravel 10.48 permite las consultas JSON utilizadas con SQLite.

Los rangos PHP se contrastaron con las [notas oficiales de Laravel](https://laravel.com/docs/12.x/releases)
y la [tabla de Testbench](https://packages.tools/testbench#version-compatibility).
La compatibilidad técnica del paquete no prolonga el mantenimiento de cada framework.
Cada aplicación debe mantener su framework actualizado con los parches de seguridad
disponibles para su rama; una rama sin mantenimiento no recibe esa garantía.

Instalación:

```bash
composer require ahinest/laravel-print-kit
php artisan vendor:publish --tag=print-kit-config
php artisan vendor:publish --tag=print-kit-migrations
php artisan migrate
```

Después de migrar, configurar los registros de la aplicación. La configuración
publicada tiene `drivers => []`, `systems => []` y `categories => []`.
El provider arranca sin registros; resolver desde un origen requiere registrarlo.

Ejemplo mínimo para los ejemplos de esta guía, no defaults del paquete:

```php
'drivers' => [
    'inventory' => ['label' => 'Inventario', 'description' => 'Origen de ejemplo.'],
],
'systems' => [
    'INV' => [
        'label' => 'Inventario', 'description' => 'Sistema de ejemplo.',
        'table' => 'products', 'key' => 'sku', 'categories' => ['ET'],
    ],
],
'categories' => [
    'ET' => ['label' => 'Etiquetas', 'description' => 'Formatos de etiquetas.'],
],
'defaults' => [
    'fields' => ['driver' => 'inventory', 'system' => 'INV'],
    'formats' => ['system' => 'INV'],
],
```

Los defaults de aplicación anteriores son opcionales: también se pueden indicar
`driver` y `system` explícitamente al crear los modelos. La conexión `inventory`
debe existir en `config/database.php` cuando se usa DatabaseDataResolver.
ProvidedValues no necesita registries y ProvidedRecord no abre el origen.
`ET` es la categoría funcional de etiquetas, no un sistema de negocio ficticio.

Laravel descubre automáticamente `PrintKitServiceProvider`. Las migraciones
solo se publican a petición: no se cargan ni ejecutan automáticamente.
Para tablas existentes, publicar únicamente el config y administrar su esquema.

Para una instalación local, añadir un repositorio `path` en la aplicación:

```json
{
  "repositories": [
    {"type": "path", "url": "../laravel-print-kit", "options": {"symlink": true}}
  ],
  "require": {"ahinest/laravel-print-kit": "@dev"}
}
```

Después ejecutar `composer update ahinest/laravel-print-kit` en esa aplicación.
La licencia MIT completa está en [LICENSE](LICENSE).

## Persistencia y defaults

Todas las tablas predeterminadas tienen `id` BIGINT autoincremental y timestamps
`created_at` / `updated_at` nullable administrados por Eloquent.

| Tabla | Campos específicos y defaults |
| --- | --- |
| print_fields | driver y system requeridos por el modelo; category=ET o *; placeholder obligatorio (100); display_name obligatorio (255); description nullable; data_type=C (32); usable_in_detail=false; associated_field obligatorio (128) |
| print_paper_sizes | name obligatorio (255); description nullable; width y height DECIMAL(10,4) positivos, en cm; is_active=true |
| print_formats | code obligatorio UNIQUE (100); name obligatorio (255); copies=1; preview=true; printer nullable (255); paper_size_id obligatorio FK; orientation=V; system requerido por el modelo; category=ET; version=1; detail con schema 1 vacío; margins con cuatro ceros; use_default_printer=false; disabled=false; parameters nullable |

`system` es CHAR(3), `orientation` CHAR(1) y `category` es CHAR(2) en
formatos y VARCHAR(2) en PrintField para admitir * sin relleno.
`driver` es VARCHAR(100). Las descripciones son TEXT nullable.
Copias y versión son enteros entre 1 y 2147483647.
Las medidas de papel admiten 0.0001..999999.9999 cm.
Los JSON son `detail`, `margins` y `parameters`.

Índices: UNIQUE `print_fields(driver, system, category, placeholder)`,
`print_fields(system, category)` y
`print_formats(system, category, disabled)`. La FK de papel restringe su eliminación
si hay formatos que lo utilizan. El catálogo admite tamaños personalizados; no
se crean semillas obligatorias.

Casts:

- PrintField: usable_in_detail → boolean.
- PaperSize: width/height → decimal:4, devueltos como cadenas; is_active → boolean.
- PrintFormat: copies/version → integer; preview/use_default_printer/disabled →
  boolean; detail/margins/parameters → array. NULL sigue siendo NULL en parameters.

`preview` y `copies` son metadatos del formato. El consumidor decide cómo usarlos
y cuándo expandir las copias; el renderer no las multiplica. Guardar una
impresora o preview=false no ejecuta ninguna impresión.
Los defaults JSON se asignan desde el modelo, sin defaults SQL específicos
de un motor. Las escrituras SQL de bajo nivel deben proporcionar los JSON no nulos.

## Un área libre para etiquetas

La categoría ET utiliza toda la superficie del papel como un área libre, limitada
por sus márgenes. No hay secciones, encabezados, pies ni detalle repetitivo.
El origen (0, 0) es la esquina superior izquierda; x aumenta hacia la derecha,
y hacia abajo.

`PrintFormat.detail` contiene la definición completa del diseño.

## Schema 1 exacto

```json
{
  "schema_version": 1,
  "elements": [
    {
      "id": "field-description",
      "type": "field",
      "field": "DESCRIPTION",
      "x": "0.2500",
      "y": "0.3000",
      "width": "4.0000",
      "height": "0.5000",
      "style": {
        "font_size": 10,
        "font_weight": "normal",
        "text_align": "left"
      }
    },
    {
      "id": "text-price",
      "type": "text",
      "text": "Precio Bs.",
      "x": "0.2500",
      "y": "1.2000",
      "width": "1.5000",
      "height": "0.4000"
    }
  ]
}
```

Reglas de estructura:

- `schema_version` debe ser el entero 1; `elements` es una lista ordenada, que
  puede estar vacía. El último elemento se dibujará encima; no existe z_index.
- Cada elemento exige id, type, x, y, width, height y su contenido específico.
- id es una cadena UTF-8 no vacía, de hasta 128 bytes, única en el diseño.
  UUID es válido, pero no obligatorio.
- type=field exige `field`: un placeholder canónico de hasta 100 caracteres,
  con sintaxis de identificador. Nunca representa associated_field.
- type=text exige `text`: texto UTF-8 literal; se permite la cadena vacía.
  El diseño no interpreta HTML; el renderer muestra este contenido como texto escapado.
- `style` es opcional, al igual que cada propiedad de estilo. Solo admite
  font_size, font_weight y text_align. Un estilo vacío se normaliza omitiéndolo.
- font_weight: normal o bold. text_align: left, center o right.
- Las propiedades desconocidas se rechazan para evitar pérdida silenciosa.
  Nuevas propiedades opcionales podrán incorporarse mediante evolución del
  parser de schema 1; los consumidores antiguos deberán actualizarse para usarlas.

### Unidades y precisión

Papel, x, y, width, height y márgenes se expresan en **centímetros**.
La excepción es **style.font_size, expresado en puntos tipográficos (pt)**:
es una propiedad tipográfica, no una dimensión geométrica.
font_size debe ser un número JSON finito estrictamente mayor que cero;
por ejemplo, 10 o 10.5. No acepta cadenas, booleanos ni NULL.

Las medidas geométricas aceptan enteros, números finitos o cadenas decimales
no negativas con hasta cuatro decimales. Se recomiendan cadenas como `"0.25"`.
Se serializan de forma canónica como `"0.2500"`.
No se acepta notación científica en cadenas, signos, comas decimales ni precisión
adicional; se rechaza en lugar de redondear. Máximo por medida: 999999.9999.
x/y admiten cero; width/height deben ser estrictamente positivas.

La clase Decimal usa escala fija y sumas de cadenas decimales para evitar errores
como 0.1 + 0.2 > 0.3 y depender de extensiones numéricas o enteros de 64 bits.
No se convierten medidas a píxeles, puntos, dots ni unidades de impresora.

### Dos versiones distintas

- `PrintFormat.version`: revisión del formato almacenado; comienza en 1.
- `detail.schema_version`: versión del contrato JSON; comienza en 1.

Una revisión 7 puede utilizar schema 1. La revisión no se incrementa automáticamente.
No incluye migración automática entre versiones de schema.

## Clases y API de dominio

Las clases del namespace `Ahinest\LaravelPrintKit\Design` son:

| Clase | Responsabilidad |
| --- | --- |
| LabelDesign | Documento inmutable y orden de elementos |
| DesignElement | Factory y propiedades comunes de cajas |
| FieldElement | Referencia a placeholder |
| TextElement | Texto literal |
| ElementStyle | Propiedades tipográficas opcionales |
| Decimal | Medidas con cuatro decimales y sumas exactas |
| LabelDesignValidator | Reglas relativas al formato, papel y catálogo |
| InvalidDesign | Error con ruta de propiedad y método errors() |

```php
use Ahinest\LaravelPrintKit\Design\LabelDesign;
use Ahinest\LaravelPrintKit\Design\TextElement;

$design = LabelDesign::fromArray($format->detail);
$design->elements();
$design->toArray();
$design->validateFor($format);

$text = TextElement::fromArray([
    'id' => 'price-caption',
    'type' => 'text',
    'text' => 'Precio Bs.',
    'x' => '0.25', 'y' => '1.20',
    'width' => '1.50', 'height' => '0.40',
    'style' => ['font_size' => 10],
]);

$changed = $design->withElement($text);
$format->setDesign($changed)->save();
```

`LabelDesign::empty()` crea el diseño vacío válido.
`withElement()` añade al final y rechaza ids repetidos.
`replaceElement()` reemplaza un id existente conservando su posición.
`withoutElement()` elimina un id existente.
`reorder()` requiere la lista completa de ids, sin repetidos.
Todos devuelven una nueva instancia. Ninguna operación altera el diseño original
ni guarda automáticamente en base de datos.

Las factories garantizan estructura válida sin consultar la base.
`validateFor()` necesita la aplicación Laravel inicializada; también puede
inyectarse `LabelDesignValidator` y llamar a `validate($design, $format)`.

## Validación contextual y guardado

El validador comprueba:

1. Formato ET, sistema de tres caracteres, orientación V/H y papel existente.
2. V conserva width/height; H intercambia las dimensiones efectivas.
3. Los cuatro márgenes son no negativos y dejan un área imprimible positiva.
   Se aplican sobre los ejes efectivos después de orientar el papel.
4. x ≥ left, y ≥ top, x + width + right ≤ ancho efectivo,
   y + height + bottom ≤ alto efectivo. Se permiten los límites exactos.
5. Cada field tiene una definición efectiva con usable_in_detail=true para el
   mismo system: categoría ET antes de *, dentro de cada driver. Basta un driver
   disponible para validar la plantilla, que no fija conexión. La resolución
   de datos exige después disponibilidad en el driver seleccionado.
   Puede reutilizarse un placeholder en varias cajas con ids distintos.

Se consulta una vez el papel y, si hay elementos field, se consulta el conjunto de
placeholders habilitados. Se respetan clases, tablas, alias, scopes y conexiones
configuradas. No se confía en una relación paperSize cargada anteriormente.
Nunca se abre la conexión contenida en PrintField.driver.

`PrintFormat::setDesign()` valida antes de asignar.
`PrintFormat::save()` vuelve a validar el diseño completo y su contexto.
Una asignación directa de detail también pasa por el parser estructural.
Cargar el modelo completo antes de editarlo; una proyección sin detail no puede
guardarse como formato válido.

Las actualizaciones masivas por query de detail, paper_size_id, orientation,
margins, system o category se rechazan, incluidas rutas JSON y nombres físicos.
Usar `$format->update([...])` o `setDesign()->save()` sobre una instancia.
Las actualizaciones masivas de otros atributos mantienen casts y validaciones.

La validación comprueba una instantánea del catálogo: cambiar después un papel
o un campo puede invalidar diseños existentes. Deben revalidarse antes de un
renderizado. No se añade bloqueo global ni propagación automática.
SQL directo, toBase(), getQuery() e INSERT masivos eluden validaciones y eventos,
como en Eloquent; no son APIs de escritura de dominio.

## Ejemplo completo

```php
use Ahinest\LaravelPrintKit\Design\LabelDesign;
use Ahinest\LaravelPrintKit\Models\PaperSize;
use Ahinest\LaravelPrintKit\Models\PrintField;
use Ahinest\LaravelPrintKit\Models\PrintFormat;

$paper = PaperSize::create([
    'name' => 'Etiqueta 5x3', 'width' => '5.00', 'height' => '3.00',
]);

PrintField::create([
    'placeholder' => 'DESCRIPTION',
    'display_name' => 'Descripción',
    'associated_field' => 'description',
    'usable_in_detail' => true,
]);

$format = PrintFormat::create([
    'code' => 'INVETQ00', 'name' => 'Etiqueta pequeña', 'paper_size_id' => $paper->id,
]);

$design = LabelDesign::fromArray([
    'schema_version' => 1,
    'elements' => [[
        'id' => 'description-1', 'type' => 'field', 'field' => 'DESCRIPTION',
        'x' => '0.25', 'y' => '0.30', 'width' => '4.00', 'height' => '0.50',
        'style' => ['font_size' => 10, 'font_weight' => 'normal', 'text_align' => 'left'],
    ]],
]);

$format->setDesign($design)->save();
$format->paperSize;
$paper->formats;
```

## Tablas, modelos y columnas configurables

El config contiene `models`, `tables`, `columns`, `defaults` y
`database.connection`. El proveedor fusiona recursivamente los mapas parciales.

```php
return [
    'database' => ['connection' => null],
    'tables' => ['fields' => 'campos_impresion'],
    'columns' => [
        'fields' => [
            'placeholder' => 'comodin',
            'display_name' => 'nombre',
            'description' => 'descripcion',
            'usable_in_detail' => 'en_detalle',
        ],
    ],
];
```

Las entidades son fields/formats/paper_sizes; las claves de clases son
field/format/paper_size. La configuración debe establecerse antes de crear modelos.
No se debe cambiar el mapa durante la vida de una instancia.

La conexión de almacenamiento puede configurarse globalmente o con `on()` /
`setConnection()`. No es el driver del origen de datos.
Cambiar defaults afecta los modelos, no los defaults SQL previamente instalados.
Las migraciones iniciales conservan sus defaults SQL de compatibilidad (`mysql`,
`INV`, `ET`); estos no registran conexiones ni sistemas. Las escrituras SQL directas
deben proporcionar explícitamente driver/system. Los modelos no asignan un
origen ni sistema de aplicación predeterminado.
Las tres migraciones originales siempre crean el esquema predeterminado;
las tablas heredadas deben aportar columnas, tipos, índices y constraints compatibles.

`ColumnResolver` traduce identificadores, `HasCanonicalColumns` hidrata atributos
canónicos y `CanonicalQueryBuilder` traduce consultas/escrituras.
`CanonicalBuilder` aplica casts a actualizaciones masivas permitidas.
Casts, fillable, hidden, accessors, dirty/original y serialización permanecen canónicos.

Se prueban create/read/select/where/update/delete, get/first/value/pluck/cursor,
filtros anidados, whereIn/whereNull/whereBetween/whereColumn/whereDate,
orden, agregación, JSON, paginación y updateOrCreate.
También funcionan claves/timestamps renombrados, carga diferida/anticipada,
asociación, whereHas y withCount.

```php
PrintField::where('placeholder', 'DESCRIPTION')
    ->update(['description' => 'Descripción ampliada']);

$field->column('description'); // descripcion
$field->toArray();             // description, con nombre canónico
```

Los nombres configurados deben ser identificadores simples; no se aceptan SQL,
mapas duplicados ni colisiones. SQL raw, joins manuales y alias de tablas requieren
identificadores físicos escritos por el desarrollador y valores enlazados.
El adaptador no analiza SQL arbitrario ni garantiza macros futuras.

Para LIKE portable entre las tres ramas, usar `where('campo', 'like', $pattern)`.
`whereLike()` conserva la API nativa en Laravel 11/12; en Laravel 10 utiliza LIKE
según la collation y rechaza la opción caseSensitive=true. Esta única comprobación
de capacidad evita llamar a un método ausente sin ramas basadas en números de versión.

`increment`, `decrement`, sus variantes Each y `upsert` se rechazan en el
builder Eloquent. Usar asignación + save o updateOrCreate; para concurrencia,
añadir transacción y bloqueo desde la aplicación cuando corresponda.

### Sustituir modelos

Los modelos predeterminados no son final. Heredarlos conserva defaults y validación.
Utilizar la propiedad protegida `$casts`, compatible con las tres ramas,
en lugar del método casts() exclusivo de frameworks más recientes.

Para respetar una clase configurada, resolverla:

```php
$resolver = app(\Ahinest\LaravelPrintKit\Support\ModelResolver::class);
$query = $resolver->make('field')->newQuery();
```

Cambiar config no altera referencias PHP directas como PrintField::create().
Las relaciones y el validador sí utilizan el resolvedor.

Un sustituto completo debe extender Eloquent Model e implementar el contrato
`Ahinest\LaravelPrintKit\Contracts\PrintKitModel`, aportando printKitEntity() y
column(). El trait `HasCanonicalColumns` proporciona el adaptador reutilizable.
El sustituto es responsable de casts, defaults, reglas y hooks de guardado;
puede usar el servicio LabelDesignValidator explícitamente sin heredar PrintFormat.


## Resolución de datos

La plantilla `LabelDesign` conserva únicamente geometría, estilo y contenido
declarativo. `ResolvedLabel` representa una ejecución inmutable y añade `value`
solo a los elementos field al serializar. No se guarda en Eloquent ni modifica
`PrintFormat.detail`. El orden, los textos literales y las cajas se conservan.

La geometría sigue expresándose en centímetros; `style.font_size` es la
excepción tipográfica: puntos (pt), número finito estrictamente positivo.

### Registros de conexiones, sistemas y categorías

Ejemplo de configuración de `config/print-kit.php`. Los nombres de conexión,
sistema, tabla y clave pertenecen a esta aplicación de ejemplo y no son requisitos
del paquete. ET identifica el tipo de etiqueta actualmente soportado:

```php
'drivers' => [
    'warehouse' => ['label' => 'Almacén', 'description' => 'Origen secundario de ejemplo.'],
    'inventory' => ['label' => 'Inventario', 'description' => 'Origen principal de ejemplo.'],
],
'systems' => [
    'INV' => [
        'label' => 'Inventario',
        'description' => 'Productos y existencias.',
        'table' => 'products',
        'key' => 'sku',
        'categories' => ['ET'],
    ],
],
'categories' => [
    'ET' => ['label' => 'Etiquetas', 'description' => 'Impresión de etiquetas.'],
],
```

Los identificadores driver son nombres de conexiones Laravel elegidos por el
consumidor, no nombres obligatorios de motores. Una conexión SQL Server puede
llamarse `sqlsrv`, `inventory` u otro nombre configurado.
Las conexiones nombradas deben existir en `config/database.php` para el modo
Database. ProvidedRecord no abre esas conexiones: basta que estén permitidas en
DriverRegistry. SystemRegistry nunca contiene driver; INV usa la misma tabla y
clave tanto con warehouse como con inventory. La conexión del catálogo se configura
independientemente mediante `print-kit.database.connection`.

Las secciones drivers/systems/categories/transformers explícitas reemplazan sus
defaults completos, incluso si son vacías. Los mapas de columnas y defaults de
modelos conservan la combinación parcial de mapas.

```php
use Ahinest\LaravelPrintKit\Data\Registries\DriverRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\SystemRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\CategoryRegistry;

$drivers = app(DriverRegistry::class)->all(); // Mapa por código.
$option = $drivers['warehouse']->toArray();       // value, label, description.
$system = app(SystemRegistry::class)->get('INV');
$table = $system->table;
$key = $system->key;
$categories = app(CategoryRegistry::class)->all();
```

Los tres registros validan su configuración completa. Códigos de sistema:
tres caracteres ASCII mayúsculos/numéricos, comenzando por letra; categorías:
dos caracteres con la misma regla. Drivers: 1..100 letras, números, guiones o
guiones bajos. Label no vacío y description string son obligatorios.
Tabla y clave admiten un identificador simple de hasta 128 caracteres, sin
schema, puntos, expresiones ni SQL. Todas las categorías del sistema deben
estar registradas. Los registros admiten categorías adicionales, pero el
servicio de etiquetas actual solo resuelve ET.

### Catálogo de PrintField

```php
use Ahinest\LaravelPrintKit\Models\PrintField;

PrintField::create([
    'driver' => 'inventory',
    'system' => 'INV',
    'category' => '*',
    'placeholder' => 'DESCRIPTION',
    'display_name' => 'Descripción',
    'associated_field' => 'description',
    'data_type' => 'C',
    'usable_in_detail' => true,
]);
PrintField::create([
    'driver' => 'inventory',
    'system' => 'INV',
    'category' => 'ET',
    'placeholder' => 'PRICE',
    'display_name' => 'Precio',
    'associated_field' => 'price',
    'data_type' => 'D,2',
    'usable_in_detail' => true,
]);
```

La migración inicial incorpora UNIQUE(driver, system, category, placeholder),
con nombre `print_fields_identity_unique`. Category utiliza VARCHAR(2) para que
`*` no reciba relleno de CHAR.

La categoría exacta gana siempre sobre *, dentro del mismo driver + system.
Una definición exacta deshabilitada NO permite usar el wildcard como alternativa.
No se permiten * en driver ni system. Distintos drivers pueden definir el mismo
placeholder. Las comparaciones de resolución son exactas, incluso cuando la
collation del catálogo ignora mayúsculas. El índice de la base puede imponer
reglas de unicidad adicionales según su collation.

```php
use Ahinest\LaravelPrintKit\Data\PrintFieldResolver;

$catalog = app(PrintFieldResolver::class);
$field = $catalog->resolve('inventory', 'INV', 'ET', 'DESCRIPTION');
$fields = $catalog->resolveMany('inventory', 'INV', 'ET', ['DESCRIPTION', 'PRICE']);
$options = $catalog->fieldsFor('inventory', 'INV', 'ET');
```

Los resultados son mapas por placeholder. fieldsFor elimina duplicados aplicando
la precedencia y conserva la bandera usable_in_detail para futuros selectores.
La resolución de etiquetas exige que esa bandera sea verdadera.
Se usa ModelResolver y se respetan modelos, tablas, alias, scopes y conexión
de almacenamiento. Si el modelo del catálogo no fija conexión, hereda la del
formato. resolveMany recibe opcionalmente storageConnection para uso directo.
La identidad duplicada en una tabla externa sin UNIQUE produce una excepción
de dominio. El índice sigue siendo necesario para proteger escrituras concurrentes.

### Tipos, NULL y decimales

`DataType::parse($code)` expone kind y decimalPlaces nullable.
El default del modelo, configuración y migración es C.

| Código | Comportamiento |
| --- | --- |
| C | Texto desde un escalar; NULL se convierte en cadena vacía. true produce "1", false produce "". |
| N | Número decimal normalizado sin ceros fraccionarios finales; salida string. |
| D | Decimal string sin imponer escala; conserva ceros fraccionarios del valor recibido. |
| D,n | Exactamente n decimales, con n entero entre 0 y 18 inclusive. |
| T | Transformer obligatorio; su resultado es final y omite el formatter. |

N/D/D,n rechazan NULL, booleanos, arrays y objetos. Las cadenas numéricas aceptan
signo y punto decimal, sin separadores regionales ni notación exponencial, hasta
1024 caracteres. C rechaza objetos y arrays; no ejecuta conversiones Stringable.
Los valores finales, incluidos T y ProvidedValues, solo admiten escalares finitos,
texto UTF-8 o NULL. Una clave ausente es distinta de una clave presente con NULL.

D,n usa HALF_UP: empate alejándose de cero. Ejemplos: 125.555 → "125.56";
-125.555 → "-125.56"; 125 → "125.00" con D,2. No añade moneda ni miles.
La aritmética utiliza brick/math y no convierte cadenas decimales a float.
Se aceptan floats finitos por conveniencia, pero su precisión ya pudo perderse
antes de entrar al paquete; para importes exactos utilizar strings.
Decimal del diseño mantiene su responsabilidad geométrica independiente.

### Los tres modos

```php
use Ahinest\LaravelPrintKit\Data\LabelResolver;
use Ahinest\LaravelPrintKit\Models\PrintFormat;

$format = PrintFormat::where('code', 'PRODUCT_LABEL')->firstOrFail();
$resolver = app(LabelResolver::class);

// Valores canónicos finales: cero consultas después de cargar el formato.
$fromValues = $resolver->resolveWithValues(
    format: $format,
    values: ['DESCRIPTION' => 'Martillo profesional', 'PRICE' => 'Bs. 125,50'],
);

// Registro ya disponible: una consulta al catálogo, ninguna al origen.
$product = ['sku' => 'P001', 'description' => 'Martillo profesional', 'price' => '125.5'];
$fromRecord = $resolver->resolveWithRecord(
    format: $format,
    driver: 'inventory',
    record: $product,
);
// PRICE => "125.50"

// Registro completo obtenido de inventory.products mediante sku = P001.
$fromDatabase = $resolver->resolveByKey(
    format: $format,
    driver: 'inventory',
    key: 'P001',
);

// Consulta claves únicas y reconstruye el orden, incluidas repeticiones.
$labels = $resolver->resolveByKeys(
    format: $format,
    driver: 'inventory',
    keys: ['P003', 'P001', 'P003'],
);
foreach ($labels as $label) {
    $payload = $label->toArray();
    $requestedKey = $label->context->key;
}
```

ProvidedValuesResolver valida presencia y valores finales; no usa registros de
configuración, catálogo, associated_field, transformers ni formatter.
Ignora claves adicionales que el diseño no utiliza. Un valor NULL explícito se
conserva; un placeholder ausente produce MissingProvidedValue.

ProvidedRecordResolver usa RecordAccessor con arrays, propiedades públicas de
objetos, ArrayAccess y atributos Eloquent. NULL se conserva si el atributo existe.
ArrayAccess debe distinguir presencia mediante offsetExists; si su implementación
usa isset y oculta NULL, envolver el registro en array o ArrayObject.
Eloquent no carga relaciones. Accessors, scopes y transformers del consumidor
son código de confianza y pueden ejecutar sus propias consultas.

DatabaseDataResolver valida driver antes de abrir conexión. Consulta registros
completos con SELECT * porque los transformers pueden necesitar otras columnas.
Las claves deben formar una lista de strings no vacíos o enteros. Se consideran
equivalentes un entero y su representación decimal canónica string (0 y "0").
Las claves devueltas por la base deben coincidir exactamente con las solicitadas;
no hay normalización de mayúsculas, espacios ni ceros iniciales según collation.
La clave configurada debe identificar un registro único.

No hay resultado parcial cuando falta una clave. RecordNotFound expone system,
driver, keyField y keyValue; el mensaje omite keyValue para reducir exposición
accidental en logs. Una clave no única en el origen produce InvalidResolvedValue.

| Modo | Consultas al catálogo | Consultas al origen |
| --- | --- | --- |
| ProvidedValues | 0 | 0 |
| ProvidedRecord | 1 para todos los placeholders | 0 |
| Database, una clave | 1 | 1 |
| Database, lista | 1 | ceil(claves únicas / 500) |
| Database, lista vacía | 0 | 0 |

Sin campos dinámicos se omite la consulta al catálogo. Los lotes usan whereIn
con bloques de hasta 500 claves para limitar parámetros. No hay caché global;
los metadatos se preparan una sola vez por ejecución/lote. Las cifras excluyen
la carga previa del formato y consultas añadidas por código del consumidor.

La resolución valida la estructura del diseño, pero no vuelve a consultar papel
ni márgenes: la validación geométrica completa sigue en design()->validateFor()
y en el guardado del formato. Revalidar explícitamente diseños si cambió su papel.
Los resolvers aceptan Model & Contracts\PrintKitModel, incluidos sustitutos.

### Transformers

El contrato vive en `Contracts\FieldTransformer`; las clases se registran en
configuración/código, nunca en PrintField ni en la base de datos.

```php
use Ahinest\LaravelPrintKit\Contracts\FieldTransformer;
use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Data\ResolutionContext;
use Illuminate\Database\Eloquent\Model;

final class FullNameTransformer implements FieldTransformer
{
    public function transform(
        mixed $value,
        mixed $record,
        Model&PrintKitModel $field,
        ResolutionContext $context,
    ): mixed {
        $accessor = app(\Ahinest\LaravelPrintKit\Data\RecordAccessor::class);
        return $value.' - '.$accessor->get($record, 'marca');
    }
}
```

El transformer recibe el valor de associated_field, el registro completo original,
una copia de la definición y ResolutionContext inmutable con driver, system,
category, mode y key nullable. ResolutionMode distingue los tres modos.
ProvidedValues no invoca transformers. En C/N/D/D,n son opcionales y se ejecutan
antes de formatear. En T son obligatorios y pueden recibir/devolver NULL.

```php
// En config/print-kit.php: nombres de clases compatibles con config:cache.
'transformers' => [
    'drivers' => [
        'inventory' => ['INV' => ['ET' => ['DESCRIPTION' => FullNameTransformer::class]]],
    ],
    'systems' => [
        'INV' => ['*' => ['DESCRIPTION' => FullNameTransformer::class]],
    ],
],
```

```php
use Ahinest\LaravelPrintKit\Data\FieldTransformerRegistry;

app(FieldTransformerRegistry::class)->register(
    system: 'INV',
    category: 'ET',
    placeholder: 'DESCRIPTION',
    transformer: FullNameTransformer::class,
    driver: 'inventory', // Omitir o usar NULL para un transformer genérico.
);
```

El registro programático también acepta instancias del contrato. Las clases se
resuelven con el contenedor, permitiendo inyección de dependencias. Registrar
durante el arranque de la aplicación; el registry es singleton.

Precedencia: (1) driver + system + categoría exacta, (2) driver + system + *,
(3) system + categoría exacta, (4) system + *. En cada nivel se exige placeholder
exacto. El registro programático sustituye la configuración del mismo nivel;
no altera la prioridad entre niveles. Driver y system no admiten *.

### Excepciones y límites actuales

Las excepciones de `Data\Exceptions` distinguen UnknownDriver, UnknownSystem,
UnknownCategory, InvalidRegistryConfiguration, PrintFieldNotFound,
DuplicatePrintFieldDefinition, InvalidDataType, RecordNotFound,
MissingProvidedValue, AssociatedFieldNotFound, TransformerNotFound e
InvalidResolvedValue. Errores de conexión o del motor SQL conservan las
excepciones de Laravel; errores del código transformer se propagan al consumidor.

No se implementan filtros multiempresa, JOIN, fórmulas SQL, autorización HTTP,
caché persistente ni transacciones distribuidas entre catálogo y origen.
El renderizado es una etapa separada. Los resultados del lote permanecen en memoria. Los transformers
deben ser deterministas y no modificar registros; el paquete no puede impedir
efectos secundarios de código registrado por el consumidor.



## Renderizado HTML y preview

El pipeline separa `resolve → ResolvedLabel → render → RenderedDocument`.
Los renderers nunca consultan PrintField, drivers o registros, ni ejecutan
transformers o DataTypeFormatter. No se publican vistas Blade.

### Uso con un formato y una etiqueta

```php
use Ahinest\LaravelPrintKit\Data\LabelResolver;
use Ahinest\LaravelPrintKit\Rendering\LabelRenderService;
use Ahinest\LaravelPrintKit\Models\PrintFormat;

$format = PrintFormat::with('paperSize')
    ->where('code', 'PRODUCT_LABEL')
    ->firstOrFail();

$resolved = app(LabelResolver::class)->resolveByKey(
    format: $format,
    driver: 'inventory',
    key: 'P001',
);

$service = app(LabelRenderService::class);
$fragment = $service->render(format: $format, label: $resolved);
$document = $service->renderDocument(format: $format, label: $resolved);

$html = $document->html();
$metadata = $document->toArray();
// html, count, width, height, unit=cm, mediaType=text/html, charset=utf-8.
```

También se puede resolver con valores finales o un registro proporcionado.
RenderedLabel contiene html(), width/height efectivos, orientation y margins.
RenderedLabel y RenderedDocument son inmutables y serializables como JSON.
Los resultados se construyen desde objetos del paquete; no admiten HTML crudo
como entrada. No se modifica ni persiste ResolvedLabel o PrintFormat.

### Colecciones

```php
$resolved = app(LabelResolver::class)->resolveByKeys(
    format: $format,
    driver: 'inventory',
    keys: ['P003', 'P001', 'P002', 'P001'],
);
$document = $service->renderCollection(format: $format, labels: $resolved);
$html = $document->html(); // Cuatro etiquetas, en ese mismo orden.
```

El servicio prepara un único contexto físico para toda la colección. No expande
PrintFormat.copies automáticamente: cada entrada recibida produce una etiqueta.
Una colección vacía genera body vacío, count=0 y conserva la medida del contexto.
No se garantiza que un navegador imprima cero hojas si se le solicita imprimir
un documento vacío; no se invoca la impresión automáticamente.

### Contexto explícito y render puro

LabelRenderContext contiene el diseño esperado, sistema, categoría, dimensiones
efectivas en Decimal, orientación y márgenes canónicos en cm. No contiene Eloquent.

```php
use Ahinest\LaravelPrintKit\Rendering\LabelRenderContext;
use Ahinest\LaravelPrintKit\Rendering\LabelRenderContextFactory;
use Ahinest\LaravelPrintKit\Rendering\LabelHtmlRenderer;
use Ahinest\LaravelPrintKit\Rendering\HtmlDocumentRenderer;

// Con valores físicos explícitos: cero consultas, sin inicializar Laravel.
$context = new LabelRenderContext(
    design: $resolvedLabel->design,
    paperWidth: '5',
    paperHeight: '3',
    orientation: 'V',
    margins: ['top' => '0.2', 'right' => '0.2', 'bottom' => '0.2', 'left' => '0.2'],
    system: 'INV',
    category: 'ET',
);
$fragment = (new LabelHtmlRenderer)->render($context, $resolvedLabel);
$document = (new HtmlDocumentRenderer)->render($fragment);

// Alternativa Eloquent: la fábrica prepara el contexto una sola vez.
$context = app(LabelRenderContextFactory::class)->create($format);
// Si ya se tiene el modelo de papel:
// $context = app(LabelRenderContextFactory::class)->create($format, $paper);

$renderer = new LabelHtmlRenderer;
$fragments = [];
foreach ($resolvedLabels as $label) {
    $fragments[] = $renderer->render($context, $label);
}
$document = (new HtmlDocumentRenderer)->render($fragments, $context);
```

La fábrica utiliza ModelResolver, modelos reemplazables, alias, scopes y conexión
del paquete. Si no hay conexión explícita de papel, hereda la del formato.
Reutiliza paperSize cargado o el papel explícito, comprobando clase configurada,
contrato, id y conexión; en caso contrario consulta el papel una vez. No escribe
ni carga relaciones de forma oculta sobre el formato recibido. Una relación
cargada null o incompatible produce error; para recargarla el consumidor debe
hacerlo explícitamente. Un papel cargado es una instantánea confiada: refrescar
el modelo si cambió la base o los scopes.

LabelGeometry comparte las reglas físicas con LabelDesignValidator: dimensiones
positivas, márgenes válidos y cajas dentro del área imprimible. No se duplica la
validación del catálogo. El contexto valida la geometría y el renderer comprueba
que diseño canónico, sistema y categoría coincidan con la etiqueta resuelta.
Esto detecta diseños cambiados desde la resolución. ResolvedLabel no conserva
el id del formato/papel de origen: dos formatos con idéntico diseño y contexto
lógico son indistinguibles. El contexto físico explícito es la autoridad de render.

### Medidas, orientación y márgenes

Geometría en **cm**, sin convertir a px, mm, dots o puntos PDF. Se conserva la
representación Decimal de cuatro posiciones: left:0.2000cm, por ejemplo.
Únicamente font_size usa **pt**, numérico positivo. V conserva las dimensiones
del papel; H intercambia width/height. No se rotan los elementos.

Los márgenes son restricciones/metadatos. Las coordenadas x/y ya se miden desde
la esquina del papel: left:0.2000cm sigue siendo 0.2 cm aunque margin.left sea
0.2. No se añade padding o desplazamiento por esos márgenes.
El contenedor tiene position:relative, tamaño físico efectivo y overflow:hidden.
Cada elemento es una caja position:absolute y usa box-sizing:border-box.

### Defaults visuales y contenido

| Propiedad de render | Default |
| --- | --- |
| font-size | 10pt |
| font-weight | normal |
| text-align | left |
| font-family | sans-serif |
| line-height | 1.2 |
| Alineación vertical | Arriba, flujo normal dentro de la caja |
| white-space | normal |
| overflow-wrap / word-break | break-word / normal |
| overflow | hidden |

Los estilos conocidos del diseño sustituyen tamaño, peso y alineación.
Los defaults se aplican al HTML, nunca al JSON persistido. No hay bordes,
fondos, grids, sombras ni decoraciones de debugging. El contenido que excede
la altura se recorta; palabras largas pueden partirse. No hay scroll,
shrink-to-fit, auto font size, ellipsis ni alineación vertical configurable.

El renderer conserva el texto y su formato numérico; solo normaliza para HTML.
NULL produce caja vacía; boolean true produce "1", false produce "0".
Esta regla se aplica a booleanos finales: si un formatter C produjo antes "",
esa cadena sigue vacía. No se aplica lógica de negocio al renderizar.

Todos los textos y atributos dinámicos se escapan centralmente con HtmlEscaper,
incluidos TextElement.text, valores finales e ids. Entidades ya escritas como
"&lt;b&gt;" siguen siendo texto literal; no se consideran HTML confiable.
No se admite style arbitrario ni atributos aportados por valores de datos.
Se preserva UTF-8. El HTML conserva espacios y saltos en su texto; CSS
white-space:normal los colapsa visualmente según el comportamiento normal del
navegador, sin alterar el valor guardado o resuelto.

### Documento, CSS de impresión y páginas

El fragmento tiene clases estructurales print-kit-label y print-kit-element;
los estilos necesarios, incluidos los geométricos, son inline. No requiere
CSS externo, JavaScript o Blade ni una respuesta HTTP.
El documento añade DOCTYPE, html/head/body y meta charset=utf-8.

```html
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    @page { size: 5.0000cm 3.0000cm; margin: 0; }
    html, body { margin: 0; padding: 0; }
    @media print {
      .print-kit-label { break-after: page; page-break-after: always; }
      .print-kit-label:last-child { break-after: auto; page-break-after: auto; }
    }
  </style>
</head>
<body>
  <div class="print-kit-label"
       style="position:relative;box-sizing:border-box;width:5.0000cm;height:3.0000cm;overflow:hidden;">
    <div class="print-kit-element"
         style="position:absolute;left:0.2000cm;top:0.3000cm;width:4.6000cm;height:0.8000cm;font-size:10pt;">
      Martillo profesional
    </div>
  </div>
</body>
</html>
```

El ejemplo está simplificado; la salida real incluye los resets y defaults.
@page usa dimensiones efectivas, sin depender de portrait/landscape, y margin:0
para no duplicar márgenes. Se evita dividir una etiqueta entre páginas mediante
break-inside/page-break-inside. Los saltos se aplican en impresión y la última
etiqueta los desactiva para no forzar una hoja adicional.
En pantalla las etiquetas se apilan con sus medidas físicas CSS, sin UI adicional.

HtmlDocumentRenderer acepta RenderedLabel o una lista ordenada de esos objetos.
Rechaza medidas efectivas distintas, HTML crudo y mapas no ordenados. Una lista
vacía requiere LabelRenderContext explícito. No hay soporte de papeles mezclados.
Las excepciones específicas son InvalidRenderContext, UnsupportedRenderCategory
e IncompatibleRenderedLabels, en el namespace Rendering\\Exceptions.

### Consultas y alcance

| Llamada | Papel | Catálogo de campos / origen / transformers |
| --- | --- | --- |
| Renderers puros con contexto | 0 | 0 |
| Servicio con paperSize cargado | 0 | 0 |
| Servicio sin papel cargado | 1 por llamada, también para toda una colección | 0 |
| Fábrica con papel explícito | 0 | 0 |

Las cifras excluyen carga previa de modelos y consultas de accessors/scopes
personalizados del consumidor. No se introduce caché global.
Se puede usar el renderer en comandos, jobs, tests y controllers.

Los resets inline protegen la geometría y tipografía principal del fragmento,
sin reset global del sitio. No constituyen aislamiento absoluto frente a CSS
externo con !important u otras reglas del consumidor. Para controlar el documento
completo, usar la salida de HtmlDocumentRenderer sin CSS adicional.
Una política CSP del consumidor debe permitir los estilos inline del renderer.

El tamaño CSS solicitado no garantiza calibración física en cualquier navegador
o impresora. Al imprimir, el consumidor debe usar papel correspondiente, escala
100% y desactivar encabezados/pies y ajustes automáticos del navegador. El paquete
no automatiza el diálogo de impresión ni verifica hardware o drivers.
No se implementan diseñador, iframe/modal de UI, PDF, headless Chrome,
impresión directa, códigos de barras, fuentes configurables ni gráficos.

El ejemplo reproducible [examples/render-label.php](examples/render-label.php)
genera un documento de tres etiquetas sin Laravel/DB:

```bash
php examples/render-label.php examples/label-preview.html
```

La salida [examples/label-preview.html](examples/label-preview.html) muestra
texto normal, HTML escapado y Unicode. No ejecuta scripts ni llama a print().


## API backend del diseñador

La API está desactivada por defecto. Publicar o actualizar `config/print-kit.php`
y habilitarla explícitamente, después de elegir los middleware del consumidor:

```php
'api' => [
    'enabled' => true,
    'prefix' => 'print-kit',
    'middleware' => ['api'], // Añadir aquí autenticación, autorización y throttle del consumidor.
    'per_page' => 25,
    'max_per_page' => 100,
],
```

`api` no implica autenticación. No se requiere Sanctum, Spatie ni una sesión.
Se puede usar un prefijo como `internal/labels` y sustituir la lista completa de
middleware, incluso por `[]`. El prefijo no añade `/api` automáticamente.
La aplicación controla permisos, CORS, CSRF, límites de solicitudes y CSP.
Con middleware de sesión/web se aplicarán sus requisitos de CSRF.
Tras cambiar la configuración, reconstruir los caches de config/rutas del consumidor.
Desactivar `enabled` evita registrar rutas; también hay una comprobación en ejecución
que devuelve 404 si se conservó un cache de rutas creado con la API habilitada.
No se publican controllers ni se ejecutan migraciones automáticamente.

### Rutas y respuestas

Las rutas tienen el prefijo configurado. Los nombres siguientes llevan `print-kit.`:

| Método | Ruta predeterminada | Nombre | Éxito |
| --- | --- | --- | --- |
| GET | `/print-kit/formats` | `formats.index` | 200 JSON paginado |
| POST | `/print-kit/formats` | `formats.store` | 201 JSON detalle |
| GET | `/print-kit/formats/{format}` | `formats.show` | 200 JSON detalle |
| PUT | `/print-kit/formats/{format}` | `formats.update` | 200 JSON detalle |
| DELETE | `/print-kit/formats/{format}` | `formats.destroy` | 204 sin cuerpo |
| GET | `/print-kit/options/drivers` | `options.drivers` | 200 JSON |
| GET | `/print-kit/options/systems` | `options.systems` | 200 JSON |
| GET | `/print-kit/options/categories` | `options.categories` | 200 JSON |
| GET | `/print-kit/options/paper-sizes` | `options.paper-sizes` | 200 JSON |
| GET | `/print-kit/options/fields` | `options.fields` | 200 JSON |
| POST | `/print-kit/design/validate` | `design.validate` | 200 `{"valid":true}` |
| POST | `/print-kit/preview` | `preview` | 200 HTML completo |
| POST | `/print-kit/formats/{format}/preview` | `formats.preview` | 200 HTML completo |

Los cuerpos POST/PUT requieren `Content-Type: application/json` y un objeto JSON.
Los recursos y opciones usan `{"data": ...}`; los listados paginados incluyen
`data`, `links` y `meta` de Laravel. Los ejemplos curl asumen que la aplicación
ya configuró el acceso; añadir las cabeceras de autenticación que correspondan.

### CRUD, filtros y paginación

El listado permite `system`, `category=ET`, `disabled=0|1`, `search`, `page` y
`per_page` (1–100 por defecto). Incluye formatos deshabilitados si no se filtran.
Ordena por id ascendente; `search` busca en code/name con LIKE y bindings.
Los caracteres `%` y `_` conservan su significado de comodines SQL.
No admite un lenguaje genérico de consultas. Los parámetros GET no reconocidos
se ignoran. El tamaño configurado por defecto se limita al máximo configurado.

```bash
curl --get 'https://app.example/print-kit/formats' \
  -H 'Accept: application/json' \
  --data-urlencode 'system=INV' --data-urlencode 'category=ET' \
  --data-urlencode 'disabled=0' --data-urlencode 'search=Etiqueta' \
  --data-urlencode 'per_page=25' --data-urlencode 'page=1'
```

Cada resumen devuelve id, code, name, copies, preview, printer, paper_size_id,
orientation, system, category, version, margins, use_default_printer, disabled,
parameters, created_at y updated_at. No selecciona `detail` ni carga papeles.
Show/create/update agregan `detail`, `paper_size` (id, name, description, width,
height) y `designer` (`schema_version: 1`, `geometry_unit: cm`, `font_size_unit: pt`).
`version` es la versión del formato, distinta de `detail.schema_version`.
La API no permite escribir id/version/timestamps ni incrementa version al editar.

Guardar como `format.json` este ejemplo, sustituyendo paper_size_id por un papel
existente de 5 × 3 cm o mayor. El catálogo debe tener DESCRIPTION habilitado para
INV/ET (o wildcard) en al menos un driver:

```json
{
  "code": "INVETQ00",
  "name": "Etiqueta 5x3",
  "copies": 5,
  "preview": true,
  "printer": null,
  "paper_size_id": 1,
  "orientation": "V",
  "system": "INV",
  "category": "ET",
  "margins": {"top":"0.2","right":"0.2","bottom":"0.2","left":"0.2"},
  "use_default_printer": false,
  "disabled": false,
  "parameters": null,
  "detail": {
    "schema_version": 1,
    "elements": [{
      "id": "description", "type": "field", "field": "DESCRIPTION",
      "x": "0.2", "y": "0.3", "width": "4", "height": "0.6",
      "style": {"font_size": 10, "font_weight": "bold", "text_align": "left"}
    }]
  }
}
```

```bash
curl -X POST 'https://app.example/print-kit/formats' \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  --data-binary @format.json
curl 'https://app.example/print-kit/formats/1' -H 'Accept: application/json'
curl -X PUT 'https://app.example/print-kit/formats/1' \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  --data '{"name":"Etiqueta revisada","disabled":true,"parameters":{"note":"demo"}}'
curl -X DELETE 'https://app.example/print-kit/formats/1' -H 'Accept: application/json'
```

Crear requiere code/name no vacíos y paper_size_id. El resto usa defaults
configurados; detail puede omitirse al crear para usar el diseño vacío inicial.
PUT admite cambios parciales del mismo conjunto editable. Un objeto enviado en
detail, margins o parameters sustituye ese atributo completo; no es un JSON Patch.
Siempre se valida el diseño final y se guarda la instancia Eloquent. El borrado
no elimina el papel y no depende de disabled. No se añaden soft deletes.
Code es único en la tabla configurada, ignorando el propio id al actualizar.
La comprobación de unicidad omite scopes para coincidir con el índice global;
las lecturas, opciones y búsquedas para editar/borrar sí respetan scopes.
Mantener el índice único en esquemas existentes para cubrir escrituras concurrentes.

`parameters` acepta un objeto/array JSON o null, sin interpretar su contenido.
Se administran printer/use_default_printer, sin consultar impresoras instaladas.
Un papel inactivo se oculta en las opciones normales, pero puede seguir usándose
en un formato existente o indicado explícitamente. Solo se admite category=ET.
Las dimensiones, posiciones y márgenes están en cm; `style.font_size` es un
número finito mayor que cero en **pt**, excepción tipográfica explícita.

### Catálogos del diseñador

```bash
curl 'https://app.example/print-kit/options/drivers'
curl 'https://app.example/print-kit/options/systems'
curl 'https://app.example/print-kit/options/categories'
curl 'https://app.example/print-kit/options/paper-sizes?include_inactive=1'
curl 'https://app.example/print-kit/options/fields?driver=inventory&system=INV&category=ET'
```

Drivers/categories devuelven value, label, description; systems agrega categories.
No exponen tabla/clave física. Categories no incluye `*`; puede contener categorías
registradas por el consumidor, aunque el diseñador actual solo admite ET.
Paper sizes devuelve value, label, description, width y height (cadenas a cuatro
decimales), activos por defecto; `include_inactive=1` incluye los demás.
Fields requiere driver/system/category y devuelve value=placeholder, label=display_name,
description y data_type. La categoría exacta gana sobre `*`; después se excluyen
campos no utilizables. Un exacto no utilizable oculta su wildcard utilizable.
No se expone associated_field ni se repiten placeholders.

Todas las opciones van bajo `data`, ordenadas por label en minúsculas Unicode
y, en empate, por value convertido a cadena. Es un orden binario estable,
independiente de la inserción; no es una colación lingüística del navegador.
No hay cache de catálogos.

### Validación y preview de borradores

`POST /design/validate` recibe directamente los atributos de formato del ejemplo;
code/name son opcionales y detail es obligatorio. Construye un modelo temporal
configurado, aplica defaults y reutiliza LabelDesign/LabelDesignValidator, sin guardar.

```bash
curl -X POST 'https://app.example/print-kit/design/validate' \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  --data-binary @format.json
```

Un diseño válido responde `{"valid":true}`. Para preview, guardar en `preview.json`
un objeto con este contrato (format contiene el mismo diseño completo):

```json
{
  "format": {
    "paper_size_id": 1,
    "orientation": "V",
    "system": "INV",
    "category": "ET",
    "margins": {"top":"0.2","right":"0.2","bottom":"0.2","left":"0.2"},
    "detail": {
      "schema_version": 1,
      "elements": [{
        "id":"description", "type":"field", "field":"DESCRIPTION",
        "x":"0.2", "y":"0.3", "width":"4", "height":"0.6"
      }]
    }
  },
  "values": {"DESCRIPTION":"Martillo profesional"}
}
```

```bash
curl -X POST 'https://app.example/print-kit/preview' \
  -H 'Content-Type: application/json' -H 'Accept: text/html' \
  --data-binary @preview.json --output preview.html
curl -X POST 'https://app.example/print-kit/formats/1/preview' \
  -H 'Content-Type: application/json' -H 'Accept: text/html' \
  --data '{"values":{"DESCRIPTION":"Martillo profesional"}}' --output saved-preview.html
```

Ambos previews comparten el pipeline: papel → validación de diseño/catálogo →
ProvidedValuesResolver → contexto → LabelHtmlRenderer → HtmlDocumentRenderer.
Se devuelve HTML completo inline con `Content-Type: text/html; charset=UTF-8`,
sin Content-Disposition attachment. No se persisten borradores ni se modifican
formatos guardados. Incluso con copies=5 se genera una sola representación.

Values son valores **finales**, escalares finitos/texto UTF-8/null. No se aplica
formatter ni transformer y no se consulta el origen. Un placeholder faltante
produce 422 en `values.PLACEHOLDER`; null es válido y produce texto vacío.
El validador lee el JSON original para conservar espacios, cadenas vacías y
cadenas decimales frente a TrimStrings/ConvertEmptyStringsToNull globales.
No se admiten driver/key/record en preview ni se generan ejemplos automáticos.
El campo values debe estar presente; usar `{}` o `[]` en diseños de solo texto.

Una aplicación frontend puede enviar JSON con fetch, comprobar el estado HTTP y
usar el HTML exitoso como srcdoc o Blob en un iframe propio. Los errores siguen
siendo JSON aunque se solicite HTML. El paquete no incorpora ese iframe ni la UI.
El HTML usa estilos inline, escapa textos/valores y no contiene scripts ni print().
El consumidor define una CSP compatible; el paquete no añade cabeceras CSP.

### Errores, extensibilidad y consultas

| Estado | Caso |
| --- | --- |
| 404 | Formato o papel inexistente/incompatible |
| 422 | Datos, filtros, diseño, unicidad o valores inválidos |
| 415 | POST/PUT sin un Content-Type JSON |
| 500 | Fallo inesperado de una acción, registrado y con mensaje público genérico |

Ejemplo de validación geométrica:

```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": {"detail.elements.0.x": ["El elemento queda fuera del área permitida."]}
}
```

En preview de borrador las rutas del diseño llevan `format.`, por ejemplo
`format.detail.elements.0.x`. Las acciones no exponen traces ni SQL incluso con
debug activado. ApiController delimita la traducción de errores al paquete;
no modifica el exception handler global ni las respuestas de otras rutas.
La aplicación mantiene el manejo de sus middleware de autenticación/autorización.

FormatManager obtiene modelos mediante ModelResolver. No hay route binding a
PrintFormat ni nombres físicos de tablas/columnas en los controllers. Se respetan
modelos independientes que implementen el contrato, builders canónicos, alias,
scopes y conexión de almacenamiento. Los sustitutos deben conservar los casts
del contrato, incluidos detail/margins/parameters como arrays. PaperSizeResolver
verifica modelo, clave y conexión y permite compartir el papel ya leído entre
validación y render. Cambiar paper_size_id invalida una relación cargada anterior.

Conteos medidos con los modelos predeterminados en SQLite, sin middleware propio:

| Operación | Consultas |
| --- | --- |
| Opciones drivers/systems/categories | 0 |
| Opciones paper sizes | 1 |
| Opciones fields | 1 al catálogo |
| Listado no vacío | 2: count + página, sin detail ni papeles |
| Detalle de formato | 2: formato + papel |
| Validar o previsualizar borrador con fields | 2: papel + catálogo |
| Preview de borrador solo texto | 1: papel |
| Preview guardado con fields | 3: formato + papel + catálogo |
| FormatManager.preview con papel ya cargado y fields | 1: catálogo |

Origen de datos: **0 consultas en todos los previews HTTP**. ProvidedValuesResolver
agrega cero consultas. Crear/actualizar conserva las validaciones del modelo al
guardar; sus comprobaciones pueden releer papel/catálogo. Los modelos y middleware
del consumidor pueden añadir consultas propias.

## Seguridad y límites

driver y associated_field representan una conexión nombrada y un identificador
de columna. DriverRegistry autoriza las conexiones disponibles para resolución.
SystemRegistry valida tabla y clave; associated_field admite solo un identificador
simple. Las consultas usan Query Builder y bindings, sin SQL raw. El consumidor
debe aplicar sus propios permisos de usuario antes de invocar estos servicios.

No hay UI, secciones de reporte, canvas interactivo, drag/drop,
permisos propios del paquete, PDF, impresión directa, códigos de barras,
imágenes arbitrarias, figuras ni contenido HTML/SVG proporcionado por el usuario.
El estilo persistido solo incluye tamaño, peso y alineación. No hay familia configurable, colores,
rotaciones ni efectos tipográficos.

Las migraciones usan Schema de Laravel sin SQL específico de un motor.
La suite de integración utiliza SQLite; no se ejecutó contra MySQL,
PostgreSQL ni SQL Server. No hay CHECK personalizados por motor.

## Desarrollo y CI

```bash
composer install
composer test
composer format
composer format:check
composer validate --strict
```

El lock conserva un entorno de desarrollo Laravel 12/PHP 8.2.
No determina las dependencias instaladas por las aplicaciones consumidoras.
Para desarrollar con PHP 8.1/Laravel 10 hay que resolver las dependencias:

```bash
composer update --with "orchestra/testbench:8.*" --with "phpunit/phpunit:10.5.*"
```

Para Laravel 11 usar Testbench 9.*, y para Laravel 12 usar 10.*;
en ambas ramas puede emplearse PHPUnit 11.5.*.
En Windows puede invocarse el PHAR con PHP para evitar que cmd interprete
los caracteres de las restricciones de versión.

GitHub Actions comprueba estas combinaciones:

| Laravel | PHP |
| --- | --- |
| 10 | 8.1, 8.2, 8.3 |
| 11 | 8.2, 8.4 |
| 12 | 8.2, 8.4 |

La matriz principal usa BaconQrCode 3. Una combinación adicional de Laravel 12 /
PHP 8.2 fuerza BaconQrCode `^2.0.8` mediante Composer y ejecuta la suite completa.
Cada combinación ejecuta Composer validate, check-platform-reqs y PHPUnit.
Un trabajo separado ejecuta Pint con el lock de referencia.
No hay análisis estático configurado.

Las ramas antiguas de Laravel tienen avisos de seguridad conocidos detectados por
Composer audit. Solo su paso de resolución en CI permite esas dependencias para
medir compatibilidad; el paso audit sigue mostrando los avisos y es informativo
en Laravel 10/11. En Laravel 12 la auditoría debe pasar.
No se desactiva el bloqueo de seguridad en el composer.json del paquete ni
en la configuración de aplicaciones consumidoras.

## Administración de catálogos por HTTP

Con la API habilitada, las siguientes rutas comparten el prefijo y middleware
configurados. El consumidor debe protegerlas con su autenticación y autorización.
Los ejemplos usan el prefijo predeterminado `/print-kit`.

| Método | Ruta | Nombre (prefijo `print-kit.`) |
| --- | --- | --- |
| GET / POST | `/paper-sizes` | `paper-sizes.index` / `paper-sizes.store` |
| GET / PUT / DELETE | `/paper-sizes/{paperSize}` | `paper-sizes.show` / `paper-sizes.update` / `paper-sizes.destroy` |
| GET / POST | `/fields` | `fields.index` / `fields.store` |
| GET / PUT | `/fields/{field}` | `fields.show` / `fields.update` |

POST y PUT reciben un objeto JSON (`Content-Type: application/json`). Los campos
desconocidos se rechazan con 422; PUT permite cambios parciales y valida el estado
completo resultante. POST aplica los defaults configurados cuando se omiten.
Las respuestas individuales usan `data`, creación devuelve 201, actualización
200 y eliminación correcta 204. Los listados usan `data`, `links` y `meta`.

### Tamaños de papel

Ejemplo de POST `/print-kit/paper-sizes`:

```json
{
  "name": "Etiqueta 5 × 3",
  "description": "Etiqueta de inventario",
  "width": "5.0000",
  "height": "3.0000",
  "is_active": true
}
```

`name` es obligatorio (máximo 255), `description` admite null. Ancho y alto
se expresan en centímetros: rango 0.0001–999999.9999, hasta cuatro decimales.
Se serializan como strings de cuatro decimales. `is_active` es booleano.
La excepción tipográfica del diseño sigue siendo `style.font_size`: puntos
tipográficos (pt), con valor numérico mayor que cero.

GET `/paper-sizes?search=Etiqueta&is_active=1&page=1&per_page=25` busca en nombre y
descripción. El orden estable es nombre, id. La administración incluye activos e
inactivos; `/options/paper-sizes` sigue entregando los activos salvo su opción
explícita para incluir inactivos. Desactivar no invalida formatos existentes.

DELETE comprueba referencias de **todos** los formatos, incluso ocultos por scopes.
Un papel usado devuelve 409 con mensaje visible, sin eliminarlo. La comprobación
usa modelos/alias configurados, transacción y bloqueo del papel; una infracción
de clave foránea concurrente también se traduce a 409. La integridad concurrente
requiere conservar la FK del esquema, especialmente en modelos personalizados.

### Campos de impresión

Ejemplo de POST `/print-kit/fields` (los códigos deben estar registrados):

```json
{
  "driver": "sqlsrv",
  "system": "INV",
  "category": "ET",
  "placeholder": "PRICE",
  "display_name": "Precio",
  "description": null,
  "associated_field": "precio",
  "data_type": "D,2",
  "usable_in_detail": true
}
```

Se valida driver y sistema registrados; categoría registrada y habilitada para
el sistema, o `*`. La combinación driver/sistema/categoría/placeholder es única:
duplicados devuelven 422, excluyendo el propio id al editar y comprobando también
registros ocultos. Se conserva literalmente la capitalización del placeholder.
Un índice con collation insensible puede rechazar variantes que solo difieran
en mayúsculas; el consumidor controla la collation física.

`placeholder` acepta un identificador de hasta 100 caracteres y
`associated_field` uno simple de hasta 128, comenzando por letra o `_`; no acepta
SQL, puntos, espacios ni expresiones. `display_name` es obligatorio, máximo 255;
`description` admite null; `usable_in_detail` es booleano. `data_type` utiliza el
parser existente: `C`, `N`, `D`, `D,n` (n de 0 a 18) o `T`.

GET `/fields?driver=sqlsrv&system=INV&category=ET&usable_in_detail=1&search=precio`
filtra por valores exactos y busca en placeholder, nombre, descripción y campo
asociado. Orden estable: driver, sistema, categoría, nombre, id. `category=*`
selecciona los registros comodín; no ejecuta resolución de fallback.

Ambos listados aceptan `page` y `per_page` (25 predeterminado, máximo 100, ambos
configurables en `api`); páginas/límites inválidos devuelven 422. `search` admite
hasta 255 caracteres y las consultas usan bindings.

`/fields` es administrativo y expone `associated_field`. `/options/fields`
conserva su proyección segura para el diseñador: no expone ese atributo y aplica
precedencia exacta sobre comodín; un exacto no utilizable bloquea el comodín.
**No existe DELETE de PrintField**: se edita `usable_in_detail` para controlar su
disponibilidad. La administración solo consulta el catálogo interno; registrar
o editar un campo de `sqlsrv` no abre la conexión de origen.

## Campos representados como QR

Cada aparición de un campo decide su representación mediante `render_as`.
El catálogo `PrintField`, `DataType` y los resolvers conservan su responsabilidad
de obtener el valor final. Un mismo placeholder puede aparecer como texto y QR:

```json
{
  "schema_version": 1,
  "elements": [
    {"id":"product-text","type":"field","field":"PRODUCT","x":0,"y":0,"width":4,"height":1},
    {"id":"product-qr","type":"field","field":"PRODUCT","render_as":"qr","x":0,"y":1,"width":4,"height":2}
  ]
}
```

Los únicos valores admitidos son `text` y `qr`, en minúsculas. Omitir la
propiedad equivale a texto. La serialización canónica omite `render_as: text` y
conserva `render_as: qr`; los diseños anteriores no necesitan migración.
`TextElement` no admite esta propiedad. La API inmutable permite
`$qr = $field->withRenderAs('qr')` y `$text = $qr->withRenderAs('text')`;
`renderAs()` permite consultar la representación. No cambia `schema_version`.

El renderer genera SVG en memoria con BaconQrCode (`^2.0.8 || ^3.0.3`), mediante
un `img` con `data:image/svg+xml;base64,...` y `alt=""`. Requiere XMLWriter e
iconv (este último requerido por BaconQrCode), sin GD, Imagick, archivos
temporales ni servicios externos. El paquete mantiene PHP `^8.1`; la rama 2
permite integrarse con consumidores que ya la requieren.

El QR usa negro sobre blanco, UTF-8, corrección M y margen blanco de cuatro
módulos. Ocupa un cuadrado de lado `min(width, height)`, centrado dentro de la
caja, sin estirarse. Coordenadas y dimensiones continúan en centímetros.
`style` se conserva al cambiar de representación, pero el QR ignora la
tipografía; `style.font_size` sigue siendo un número positivo en puntos (pt).

Se codifica el valor final de `ResolvedLabel`: no se aplica `trim`, conversión
numérica ni una segunda transformación. Así, `001-012`, `001234` y los espacios
se conservan. `D,2` codifica, por ejemplo, `125.50` después de la resolución.
`null` y `''` dejan la caja vacía; cero genera QR. Los booleanos usan la misma
conversión que el renderer de texto: `true` → `1`, `false` → `0`.

El SVG solo contiene la geometría generada por la librería. El contenido del
campo nunca se inserta como HTML o SVG crudo. Un valor que excede la capacidad
QR o no es UTF-8 genera `QrCodeGenerationFailed`, con id y placeholder pero sin
contenido ni excepción interna. Los previews lo traducen a 422 con un mensaje
seguro en `errors["format.fields"]`.

Los previews de borrador y guardados usan el mismo renderer que el modo
Database. El QR no agrega consultas: los valores manuales no consultan el
origen, y placeholders repetidos se resuelven una sola vez por registro.
`ProvidedValuesResolver` mantiene cero consultas; `ProvidedRecordResolver`
puede leer el catálogo interno sin consultar el origen del registro.

La escaneabilidad física depende del tamaño, densidad de datos, impresora y lector.
No se garantiza la lectura de cualquier código diminuto.

Con una CSP restrictiva, `img-src` debe permitir `data:` para mostrar los QR.
Ajustar esa directiva donde se renderiza el documento, sin desactivar globalmente
la política de seguridad.

## API pública y estabilidad

La serie v1.x sigue SemVer para las superficies documentadas. Se consideran
contratos públicos soportados sus métodos documentados, firmas (incluidos nombres
de parámetros para argumentos nombrados), propiedades y formatos de resultado:

| Área | API pública soportada |
| --- | --- |
| Models | PrintField, PaperSize, PrintFormat |
| Design | LabelDesign, FieldElement, TextElement, ElementStyle |
| Resolution | ProvidedValuesResolver, ProvidedRecordResolver, DatabaseDataResolver, LabelResolver |
| Results | ResolvedLabel, ResolvedLabelCollection, ResolutionContext, ResolutionMode |
| Registries | DriverRegistry, SystemRegistry, CategoryRegistry, FieldTransformerRegistry |
| Contracts | FieldTransformer, PrintKitModel |
| Rendering | LabelRenderService, LabelHtmlRenderer, HtmlDocumentRenderer, RenderedLabel, RenderedDocument |

También forman parte del contrato las claves de configuración documentadas,
los tags `print-kit-config` y `print-kit-migrations`, schema JSON 1, unidades y
rutas, nombres y respuestas HTTP documentadas cuando la API está habilitada.

La API avanzada documentada incluye Decimal, LabelDesignValidator,
LabelRenderContext, LabelRenderContextFactory, PrintFieldResolver, DataType,
DataTypeFormatter, QrCodeGenerator, ModelResolver y las excepciones de dominio
indicadas en esta guía. Su uso requiere respetar las precondiciones documentadas;
los detalles no documentados no constituyen una promesa de estabilidad.

Controllers, FormRequests, API resources, managers HTTP y helpers internos no
forman parte del contrato primario salvo documentación explícita. Entre esos
detalles están FormatInput, ResolutionPlan, prepare()/resolvePrepared(),
HtmlEscaper y los métodos auxiliares fragment()/layout(). Que un método sea
público en PHP no lo convierte por sí solo en un punto de extensión soportado.
Las excepciones documentadas y sus propiedades públicas permiten manejar errores;
el texto literal de sus mensajes no es un identificador estable.

PATCH corrige fallos sin romper contratos. MINOR añade opciones o representaciones
compatibles. MAJOR cambia de forma incompatible schema, contratos, claves de
configuración o rutas. Las nuevas propiedades opcionales de schema 1 requieren
un parser que las soporte; un consumidor antiguo puede rechazarlas explícitamente.
