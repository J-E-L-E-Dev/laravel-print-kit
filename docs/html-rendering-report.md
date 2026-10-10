# Informe: renderizado HTML y preview de etiquetas

> Documento histórico de desarrollo; README y documentación vigente prevalecen.
> Describe el estado en su fecha, no el contrato actual de la versión 1.0.0.


Fecha: 6 de octubre de 2026. Paquete ahinest/laravel-print-kit.
La documentación de uso está en [README](../README.md#renderizado-html-y-preview).
Ejemplo ejecutable: [render-label.php](../examples/render-label.php).
Salida: [label-preview.html](../examples/label-preview.html).

## 1. Archivos creados y modificados

19 archivos nuevos, de los cuales 17 son PHP:

```text
src/Design/LabelGeometry.php
src/Rendering/LabelRenderContext.php
src/Rendering/LabelRenderContextFactory.php
src/Rendering/HtmlEscaper.php
src/Rendering/LabelHtmlRenderer.php
src/Rendering/HtmlDocumentRenderer.php
src/Rendering/LabelRenderService.php
src/Rendering/RenderedLabel.php
src/Rendering/RenderedDocument.php
src/Rendering/Exceptions/InvalidRenderContext.php
src/Rendering/Exceptions/UnsupportedRenderCategory.php
src/Rendering/Exceptions/IncompatibleRenderedLabels.php
tests/Rendering/RenderingTestCase.php
tests/Rendering/LabelHtmlRendererTest.php
tests/Rendering/HtmlDocumentRendererTest.php
tests/Rendering/RenderServiceTest.php
examples/render-label.php
examples/label-preview.html
docs/html-rendering-report.md
```

Dos archivos modificados: src/Design/LabelDesignValidator.php y README.md.
LabelDesignValidator delega sus reglas geométricas a LabelGeometry; mantiene
su validación de catálogo independiente del renderer. No hay cambios de schema,
migraciones, configuración pública ni nuevas dependencias.
Los locks temporales y logs quedan en .compat/, excluido. El lock final se
restaura al mismo Laravel 12 de referencia. consult-web permanece sin cambios.

## 2. Arquitectura

Resolución → ResolvedLabel → contexto físico → renderer puro → resultado HTML.
LabelRenderService coordina; LabelRenderContextFactory es el único adaptador
Eloquent de render. LabelHtmlRenderer y HtmlDocumentRenderer no consultan datos
ni dependen de Blade, HTTP o del contenedor. No hay interfaces adicionales
innecesarias. Funcionan desde comandos, jobs, tests o controllers.

## 3. LabelRenderContext

Instantánea inmutable de diseño esperado, sistema, categoría, orientación,
dimensiones efectivas Decimal y márgenes canónicos en cm. Construible directamente
sin Laravel ni DB. Valida geometría con LabelGeometry compartido y compara
diseño canónico/sistema/categoría del ResolvedLabel antes de renderizar.

## 4. Resolución de PaperSize

La fábrica reutiliza papel explícito o paperSize cargado. Verifica modelo
configurado, contrato, id y conexión. Sin papel precargado hace una consulta
mediante ModelResolver, respetando alias, scopes y conexión configurable.
No escribe relaciones en el formato. Un papel cargado null o incompatible
se rechaza sin consulta de reemplazo. El consumidor puede recargar explícitamente.

## 5. LabelHtmlRenderer

Produce un fragmento embebible con contenedor relativo y cajas absolutas.
Acepta LabelRenderContext y ResolvedLabel; entrega RenderedLabel.
No ejecuta resolución, transformers, formatter ni consultas.

## 6. HtmlDocumentRenderer

Compone RenderedLabel o una lista ordenada en HTML completo UTF-8.
Valida uniformidad de dimensiones efectivas y rechaza HTML crudo.
Lista vacía requiere contexto físico explícito.

## 7. RenderedLabel

Inmutable: html(), width, height, orientation, margins; toArray/jsonSerialize.
Dimensiones serializadas como strings canónicos en cm. Solo se construye desde
contexto y etiqueta resuelta, sin aceptar strings HTML arbitrarios.
No conserva modelos mutables ni modifica los objetos recibidos.

## 8. RenderedDocument

Inmutable: html(), count, width, height, mediaType=text/html, charset=utf-8.
Serialización con unit=cm. Su constructor solo admite fragmentos del paquete.

## 9. CSS

Clases estructurales print-kit-label/print-kit-element y estilos inline completos.
Geometría en cm, tipografía en pt. Relative/absolute, box-sizing:border-box,
overflow:hidden, sin dependencias Tailwind/Bootstrap ni JavaScript.
El documento incluye reset html/body de margin/padding y CSS de impresión.
Los resets del fragmento están limitados a sus propias cajas.

## 10. Orientación

V conserva ancho/alto del papel. H los intercambia. No rota coordenadas ni texto.
@page utiliza dimensiones efectivas, no palabras portrait/landscape.

## 11. Márgenes

Son metadatos/reglas geométricas; no generan padding ni offsets adicionales.
margin.left=0.2 y x=0.2 producen left:0.2000cm, no 0.4.
@page margin:0 evita duplicarlos.

## 12. Defaults visuales

10pt, normal, left, sans-serif y line-height:1.2.
El texto comienza arriba por flujo normal. No se modifica el diseño persistido
ni se agregan propiedades al schema. Las cajas no incluyen decoración de debug.

## 13. Overflow

white-space:normal, overflow-wrap:break-word, word-break:normal y overflow:hidden.
El texto envuelve; lo que excede su caja queda recortado. No hay ajuste automático,
scroll, ellipsis ni alineación vertical configurable.

## 14. Escaping y seguridad

HtmlEscaper usa htmlspecialchars con ENT_QUOTES, ENT_SUBSTITUTE y ENT_HTML5,
UTF-8 y doble escape de entidades literales. Se escapan texto, valores e ids.
Solo se convierten propiedades conocidas del schema a CSS; números geométricos
provienen de Decimal validado. No se admiten HTML/SVG/script o styles arbitrarios.
Los resultados no tienen un constructor de HTML crudo.

## 15. NULL

Produce cadena vacía dentro de su caja. No imprime la palabra null.
El valor resuelto original permanece NULL.

## 16. Boolean

true → "1"; false → "0", explícitamente. Si una etapa previa de negocio produjo
una cadena vacía, el renderer conserva esa cadena, no reinterpreta su origen.

## 17. Colecciones

Se conserva orden y repeticiones. El servicio prepara un contexto por colección.
Cada entrada produce una etiqueta; no multiplica por PrintFormat.copies.
Papeles efectivos distintos en un documento se rechazan. Una colección vacía
genera count=0 y body vacío con medida física conocida.

## 18. Page breaks

En impresión: break-after:page y page-break-after:always; se evita dividir cajas
con break-inside/page-break-inside. La última etiqueta usa auto para no forzar
una página adicional. Sin herramientas, fondos o sombras de preview.

## 19. Consultas durante render

| Caso | Papel | Catálogo/origen |
| --- | --- | --- |
| Renderers con contexto explícito | 0 | 0 |
| Fábrica con papel explícito o cargado | 0 | 0 |
| Servicio sin papel cargado | 1 por llamada | 0 |
| Colección sin papel cargado, vacía o no | 1 total | 0 |

Sin transformers ni formatter. Cifras excluyen carga inicial y consultas que
introduzcan accessors/scopes propios del consumidor.

## 20. Pruebas nuevas

71 casos nuevos y 191 aserciones adicionales: orientaciones, medidas, estilos,
overflow, márgenes sin duplicación, texto/valores, NULL/booleanos, UTF-8,
escaping de etiquetas/atributos, seguridad, orden, DOCTYPE/@page/meta charset,
saltos/última página, medidas mezcladas, inmutabilidad, contextos inválidos,
papel cargado/ausente/obsoleto, cero consultas o una por colección, modelos
independientes, alias, scopes y conexiones. Las pruebas puras no inicializan Laravel.
El ejemplo PHP se ejecutó y produjo tres etiquetas sin iniciar Laravel ni DB.

## 21. Total

348 pruebas y 716 aserciones. Se conservan las 277 pruebas anteriores.

## 22. Laravel y verificaciones

Runtime local: PHP 8.2.28, SQLite en memoria.

| Framework | PHPUnit | Resultado |
| --- | --- | --- |
| Laravel 10.48.0 | 10.5.66 | 348 pruebas / 716 aserciones, correcto |
| Laravel 11.57.0 | 11.5.57 | 348 pruebas / 716 aserciones, correcto |
| Laravel 12.69.3 | 11.5.57 | 348 pruebas / 716 aserciones, correcto |

Composer validate --strict y check-platform-reqs correctos en las tres instalaciones.
Pint final: passed. Sintaxis PHP 8.1: 93 archivos válidos, incluido el ejemplo.
Matriz CI revisada y YAML válido: siete combinaciones existentes, sin cambios.
La ejecución local utilizó PHP 8.2.28; el parser de sintaxis no equivale a ejecutar
PHP 8.1. No se disparó GitHub Actions desde este entorno.
El ejemplo generado pasó la comprobación DOM de tres etiquetas, UTF-8, tamaño
CSS 5x3cm y contenido escapado. No contiene scripts.
El lock final coincide por hash con el original de Laravel 12.69.3.
Composer audit final: sin avisos de vulnerabilidad.

La prueba de ausencia de resolución elimina primero el binding anterior:
Laravel 10 ejecuta inmediatamente la sustitución de servicios ya resueltos.
El ajuste afecta solo a la preparación de pruebas.
La resolución temporal de Laravel 10/11 conserva la excepción de seguridad
existente para comprobar compatibilidad con ramas que tienen avisos conocidos;
no se modificó la política de seguridad de composer.json.

## 23. Límites actuales

No hay diseñador, UI de iframe/modal, PDF, headless Chrome, impresión directa,
códigos de barras, QR, gráficos, colores o fuentes configurables.
No se probó hardware, motores SQL comerciales ni diálogo real de impresión.
La verificación HTML usa DOM y assertions de CSS, no rasterización o PDF.

La medida CSS requiere papel coincidente, escala 100% y encabezados/pies del
navegador desactivados para impresión física. No se garantiza calibración
idéntica entre navegadores, drivers o impresoras.

Fragmentos embebidos no ofrecen aislamiento absoluto frente a CSS externo
!important. El documento completo está libre de estilos del sitio consumidor;
una CSP externa debe permitir los estilos inline.

Un papel cargado es una instantánea; refrescarlo si cambió DB o sus scopes.
ResolvedLabel no conserva identidad del formato/papel de origen: formatos con
idéntico diseño y sistema/categoría son indistinguibles; el contexto explícito
define la medida de render. No se agregaron consultas para reconstruir esa
identidad. Los lotes se componen en memoria y solo soportan un tamaño efectivo.

white-space:normal puede colapsar espacios visualmente; el texto original
se conserva. Un documento vacío no fuerza saltos, pero la conducta de imprimir
una página vacía depende del navegador. Sin decisiones funcionales pendientes.
