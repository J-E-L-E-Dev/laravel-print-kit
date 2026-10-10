# Representación QR por aparición de campo

> Documento histórico de desarrollo; README y documentación vigente prevalecen.
> Describe el estado en su fecha, no el contrato actual de la versión 1.0.0.


Fecha: 2026-10-09. La representación pertenece a `FieldElement`. Un único
`PrintField` PRODUCT, de tipo C, puede aparecer como texto y como QR dentro del
mismo diseño. Se mantiene `schema_version: 1`, sin migraciones ni cambios de
catálogo, tipos de datos o resolvers.

## Baseline y compatibilidad

Antes de implementar QR se ejecutaron las suites completas con PHP 8.2.28 y
SQLite: Laravel 10.48.0, 11.57.0 y 12.69.3 pasaron 454 pruebas y 1.475 aserciones
cada uno. PHPUnit fue 10.5.66 para Laravel 10 y 11.5.57 para Laravel 11/12.

El requisito PHP sigue siendo `^8.1`. El parser de PHP 8.1 acepta los 137 archivos
PHP del paquete, incluyendo las pruebas, y la matriz CI conserva sus siete
combinaciones. La resolución aislada de Composer con `config.platform.php=8.1.0`
seleccionó Laravel 10.50.3 y BaconQrCode 3.1.1. Esto verifica resolución y
sintaxis; no se ejecutó un binario PHP 8.1 en este equipo.

## Dependencia

Se eligió `bacon/bacon-qr-code: ^2.0.8 || ^3.0.3`. El lock final del paquete usa
3.1.1. El consumidor ya tenía BaconQrCode 2.0.8, requerido por Fortify y
Simple QrCode; admitir ambas ramas evita actualizar esos componentes para esta
función. Las APIs utilizadas (`Writer`, `ImageRenderer`, `SvgImageBackEnd`,
`RendererStyle`, corrección M) existen en ambas ramas.

Los requisitos se contrastaron con [Packagist](https://packagist.org/packages/bacon/bacon-qr-code)
y el [changelog oficial](https://github.com/Bacon/BaconQrCode/blob/main/CHANGELOG.md),
además de inspeccionar el código instalado. La rama 3 admite PHP 8.1.
Se declara `ext-xmlwriter` explícitamente; BaconQrCode requiere iconv.
Una ejecución con `php -n` generó SVG correctamente sin GD ni Imagick.

## Modelo, parser y serialización

`FieldElement::renderAs()` devuelve `text` o `qr`.
`withRenderAs()` devuelve una nueva instancia validada, conservando geometría,
id, placeholder y estilo. Se acepta exclusivamente una cadena exacta `text`
o `qr`; null, tipos diferentes, mayúsculas y representaciones desconocidas
generan `InvalidDesign` en la ruta del elemento correspondiente.

La ausencia de la propiedad equivale a texto. `toArray()` omite `render_as`
para texto, incluso si se recibió explícitamente, y conserva `qr`. El
round-trip mantiene la versión 1. `TextElement` rechaza `render_as`.
`style` se sigue validando y almacenando, aunque QR no aplica tipografía.
`font_size` mantiene su unidad pt y su restricción numérica mayor que cero.

## Generación y renderizado

`QrCodeGenerator` encapsula BaconQrCode sin añadir contratos, registros ni
fábricas. Genera SVG completamente en memoria y devuelve una data URI base64.
Configuración fija: UTF-8, corrección M, módulos negros, fondo blanco y quiet
zone de cuatro módulos. No escribe archivos ni llama a servicios externos.
Los colores RGB se fijan explícitamente: la comprobación con la rama 2 detectó
que su conversión del gris predeterminado producía `#fefefe` en lugar de blanco.

`LabelHtmlRenderer` incorpora una rama QR para `FieldElement`. Mantiene la caja
absoluta en cm y centra una imagen cuadrada cuyo lado físico es el mínimo del
ancho y alto. El QR no se estira: usa dimensiones iguales, `object-fit:contain`
y `flex-shrink:0`. El margen blanco forma parte del propio SVG. `alt` está vacío.
Los estilos tipográficos almacenados no pasan a la caja QR.

El flujo de texto conserva su HTML anterior. `HtmlEscaper::text()` centraliza la
conversión escalar que ya se utilizaba: strings intactos, números mediante su
conversión a cadena, true como `1`, false como `0` y null como cadena vacía.
Solo null y `''` omiten la imagen; espacios y cero producen QR.

Se usa el valor final de `ResolvedLabel`, después de transformers y DataType.
No se recorta ni reinterpreta: `001-012`, `001234`, espacios y Unicode llegan
intactos al generador. D,2 genera `125.50` a partir del valor final formateado.
No se añade información de presentación a `ResolvedLabel`.

`QrCodeGenerationFailed` expone id de elemento y placeholder, sin incluir el
valor ni encadenar la excepción de la librería. Un exceso de capacidad o UTF-8
inválido produce un fallo controlado. El middleware devuelve 422 con mensaje
genérico en `errors["format.fields"]`; no devuelve HTML parcial ni datos crudos.
El valor se convierte en geometría QR, nunca en SVG/HTML suministrado por el
usuario. La imagen se incorpora mediante un atributo escapado.

## Resolución y APIs

Los previews de borrador y guardados siguen usando ProvidedValues y el renderer
común. Las pruebas bloquean la conexión de origen y aun así generan el QR.
ProvidedValues conserva cero consultas propias; el endpoint puede consultar
el catálogo para validar el diseño. ProvidedRecord conserva su consulta al
catálogo y cero consultas al origen.

En Database, dos apariciones del mismo placeholder usan el mismo valor
resuelto: una consulta al catálogo y una consulta al origen en la prueba.
Tras resolver y precargar el papel, renderizar agrega cero consultas. El QR
no conoce conexiones, SQL ni copies; el consumidor mantiene su expansión de
copias existente.

## Archivos de esta fase

Creados:

- `src/Rendering/QrCodeGenerator.php`.
- `src/Rendering/Exceptions/QrCodeGenerationFailed.php`.
- `tests/Design/FieldRepresentationTest.php`.
- `tests/Rendering/QrRenderingTest.php`.
- `tests/Http/QrApiTest.php`.
- Este informe.

Modificados:

- `composer.json` y `composer.lock`: dependencia y requisito XMLWriter.
- `.github/workflows/tests.yml`: extensiones iconv y xmlwriter, misma matriz.
- `src/Design/DesignElement.php` y `FieldElement.php`: parser, API y canonicalización.
- `src/Rendering/HtmlEscaper.php`, `LabelHtmlRenderer.php` y `RenderedLabel.php`:
  conversión compartida e integración del generador opcional.
- `src/Http/Middleware/ApiErrors.php`: traducción del error QR a 422.
- `README.md`: contrato, ejemplo PRODUCT, dependencia, unidades y límites.

No se alteraron modelos, migraciones, PrintField, DataType ni resolvers.
Se preservaron los cambios y el staging existentes; no se creó ningún commit.

## Cobertura nueva

Las tres clases nuevas aportan 32 pruebas y 95 aserciones: serialización legacy,
texto explícito, API inmutable, estilo persistente, 12 valores inválidos,
TextElement, strings exactos, Unicode, booleanos, cero, null/vacío, SVG válido y
seguro, quiet zone, caja rectangular, HTML de texto idéntico, capacidad excedida,
preview de borrador/guardado, persistencia y resolución Database D,2 sin
consultas adicionales. Se prueba el valor en el límite del generador sin
incorporar una librería de decodificación.

## Resultados de verificación

| Suite completa | Pruebas | Aserciones | Duración |
| --- | --- | --- | --- |
| Laravel 10.48.0 / PHPUnit 10.5.66 | 486 | 1.570 | 57,944 s |
| Laravel 11.57.0 / PHPUnit 11.5.57 | 486 | 1.570 | 49,454 s |
| Laravel 12.69.3 / PHPUnit 11.5.57 | 486 | 1.570 | 57,978 s |

Las tres suites usan BaconQrCode 3.1.1. Composer validate estricto y los
requisitos de plataforma pasan. Pint verifica los 11 archivos PHP de esta fase;
`git diff --check` no detecta errores. El parser PHP 8.1 también comprueba el
YAML de las siete combinaciones CI y el ejemplo HTML anterior.

Después de fijar los colores, también pasó la suite completa con Laravel
11.57.0 y BaconQrCode 2.0.8: 486 pruebas y 1.570 aserciones, en 32,295 s.
Esta ejecución incluye la comprobación de blanco y quiet zone que detectó la
diferencia inicial entre las versiones de la librería.
Laravel 10 y 12 también se comprobaron de nuevo con los colores explícitos.
El lock y vendor finales quedaron restaurados a Laravel 12.69.3 y BaconQrCode
3.1.1. Frente al baseline de Laravel 12 solo se añaden BaconQrCode y su
dependencia `dasprid/enum` 1.0.7; las versiones anteriores se conservan.

Las instalaciones de compatibilidad conservaron las versiones baseline de
Laravel. Para resolver las ramas históricas se usó la excepción de seguridad
temporal que ya contempla CI (`COMPOSER_NO_SECURITY_BLOCKING=1`), sin cambiar
la configuración pública de Composer. Composer reportó seis avisos para Laravel
10.48.0 y cuatro para Laravel 11.57.0, todos sobre un paquete por matriz.
La resolución PHP 8.1/Laravel 10.50.3 también reportó cuatro avisos en un paquete.
No se presentan estas comprobaciones como un saneamiento de dependencias.

Los logs de baseline, instalaciones, tests y resolución PHP 8.1 se conservan
localmente en `.compat/qr-*`, excluidos por Git. El README incluye el contrato
público y un ejemplo con PRODUCT en ambas representaciones.

## Límites

No se levantaron servidores, abrieron navegadores, tomaron capturas, usaron
cámaras/decodificadores ni realizaron impresiones. La estructura SVG/CSS y los
flujos se comprobaron automáticamente; la lectura física del código depende
del tamaño elegido, cantidad de datos, impresora y lector. No hay garantía de
escaneabilidad para cualquier caja diminuta.

No se añadieron barcode, opciones de color/corrección, generación Vue, PDF,
cache global, archivos temporales ni soporte de nuevos tipos de elemento.
