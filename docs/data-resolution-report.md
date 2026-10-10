# Informe de la fase de resolución de datos

> Documento histórico de desarrollo; README y documentación vigente prevalecen.
> Describe el estado en su fecha, no el contrato actual de la versión 1.0.0.


Fecha: 5 de octubre de 2026. Paquete: ahinest/laravel-print-kit.
La API de uso y ejemplos completos están en [README](../README.md#resolución-de-datos).
No se modificó consult-web ni se implementó renderizado.

## 1. Archivos creados y modificados

43 archivos nuevos: 42 PHP y este informe.

```text
src/Contracts/FieldTransformer.php
src/Data/DatabaseDataResolver.php
src/Data/DataType.php
src/Data/DataTypeFormatter.php
src/Data/FieldTransformerRegistry.php
src/Data/FormatInput.php
src/Data/LabelResolver.php
src/Data/PrintFieldResolver.php
src/Data/ProvidedRecordResolver.php
src/Data/ProvidedValuesResolver.php
src/Data/RecordAccessor.php
src/Data/ResolutionContext.php
src/Data/ResolutionMode.php
src/Data/ResolutionPlan.php
src/Data/ResolvedLabel.php
src/Data/ResolvedLabelCollection.php
src/Data/ResolvedValue.php
src/Data/Exceptions/AssociatedFieldNotFound.php
src/Data/Exceptions/DuplicatePrintFieldDefinition.php
src/Data/Exceptions/InvalidDataType.php
src/Data/Exceptions/InvalidRegistryConfiguration.php
src/Data/Exceptions/InvalidResolvedValue.php
src/Data/Exceptions/MissingProvidedValue.php
src/Data/Exceptions/PrintFieldNotFound.php
src/Data/Exceptions/RecordNotFound.php
src/Data/Exceptions/TransformerNotFound.php
src/Data/Exceptions/UnknownCategory.php
src/Data/Exceptions/UnknownDriver.php
src/Data/Exceptions/UnknownSystem.php
src/Data/Registries/CategoryRegistry.php
src/Data/Registries/ConfiguredRegistry.php
src/Data/Registries/DriverRegistry.php
src/Data/Registries/RegistryEntry.php
src/Data/Registries/RegistryValidation.php
src/Data/Registries/SystemDefinition.php
src/Data/Registries/SystemRegistry.php
tests/Data/DataTypeTest.php
tests/Data/RegistryTest.php
tests/Data/ResolutionTest.php
tests/Data/TransformerRegistryTest.php
tests/Fixtures/IndependentField.php
tests/Fixtures/PrefixTransformer.php
docs/data-resolution-report.md
```

9 archivos modificados:

```text
composer.json
composer.lock
config/print-kit.php
database/migrations/2026_01_01_000001_create_print_fields_table.php
src/Models/PrintField.php
src/Design/LabelDesignValidator.php
src/PrintKitServiceProvider.php
tests/InfrastructureTest.php
README.md
```

Los logs y locks temporales de comprobación están en .compat/, ya excluido.
La matriz GitHub Actions se revisó y conserva sus siete combinaciones.

## 2. PrintField

Default C; parser de tipos aplicado en validación estructural, asignaciones y
actualizaciones por el builder del paquete. Driver es conexión nombrada; system
es código lógico. Category admite código de dos caracteres o *. Associated_field
sigue limitado a una columna simple. No se almacenan valores ni clases PHP.
LabelDesignValidator entiende wildcard y precedencia por driver.

## 3. Restricción UNIQUE

Índice print_fields_identity_unique sobre driver/system/category/placeholder
en la migración inicial. Category pasó a VARCHAR(2), evitando relleno de *.
Sin migración incremental. Tablas existentes requieren adaptación explícita.
PrintFieldResolver detecta duplicados en catálogos externos sin índice.

## 4. DriverRegistry

Allowlist validada antes de resolver campos o abrir conexiones. Expone get/all,
code y serialización value/label/description. Una lista explícita sustituye
los defaults, sin ampliar permisos silenciosamente. No requiere abrir la
conexión para trabajar con un registro proporcionado.

## 5. SystemRegistry

Expone code/label/description/table/key/categories. Valida identificadores
simples y categorías registradas. Rechaza driver dentro del sistema.
INV puede consultar I00001/producto mediante cualquiera de los drivers permitidos.

## 6. CategoryRegistry

Expone get/all y metadatos para selectores futuros. No admite * como categoría
registrada; * pertenece a definiciones y transformers. El motor actual exige ET.

## 7. PrintFieldResolver

resolve, resolveMany y fieldsFor; una consulta al catálogo por conjunto.
Mapas deduplicados por placeholder. Conserva scopes, modelos independientes,
tablas, alias y conexión mediante ModelResolver. La categoría exacta deshabilitada
bloquea fallback. fieldsFor conserva usable_in_detail para consumidores/UI futura.

## 8. DataType

Parser inmutable con kind y decimalPlaces nullable. C/N/D/T o D,n con n entre 0
y 18. Rechaza formatos ambiguos, escalas negativas o no enteras y tipos legacy.

## 9. DataTypeFormatter

C produce texto seguro; N normaliza; D conserva escala recibida; D,n fija escala.
N/D producen strings sin localización. T no puede formatearse directamente.
brick/math es dependencia directa, independiente del Decimal geométrico.

## 10. FieldTransformerRegistry

Registro mediante clases en config o clases/instancias programáticas.
Resolución mediante contenedor. Singleton para conservar registros de arranque.
La configuración se valida; no hay nombres de clase en el catálogo SQL.

## 11. Contrato FieldTransformer

transform(value, record, Model & PrintKitModel field, ResolutionContext context).
Recibe valor base y registro completo original; la definición se clona antes
de entregarla. C/N/D transforman antes del formatter; T entrega resultado final.

## 12. ResolutionContext

Inmutable: driver nullable, system, category, mode y key nullable.
ResolutionMode enum distingue ProvidedValues, ProvidedRecord y Database.
No incorpora modelos o registros mutables.

## 13. ProvidedValuesResolver

Cero consultas. Preserva valores canónicos finales; no usa catálogo,
associated_field, registros, transformers ni formatter. Faltantes son error,
NULL explícito se conserva y claves no utilizadas se ignoran.

## 14. ProvidedRecordResolver

Una consulta al catálogo y cero consultas al origen. RecordAccessor soporta
array, ArrayAccess, propiedades públicas y atributos Eloquent sin lazy loading
de relaciones. El plan interno se reutiliza por lote.

## 15. DatabaseDataResolver

Valida contexto, usa conexión permitida, tabla/clave del sistema y bindings.
Obtiene SELECT * para conservar el registro completo. Usa claves únicas y
whereIn en bloques de 500. Detecta faltantes y claves no únicas en el origen.
RecordNotFound guarda la clave en propiedad, pero la omite del mensaje.

## 16. ResolvedLabel

Inmutable, no persistente, con diseño, contexto y valores escalares/NULL.
toArray/jsonSerialize agregan value solo a elementos field.
Conserva orden, geometría, style y textos. No modifica el formato original.

## 17. ResolvedLabelCollection

Lista inmutable, iterable, contable y serializable. Conserva orden de las
claves originales y etiquetas repetidas. Cada etiqueta conserva su key en contexto.

## 18. NULL

C: cadena vacía. N/D/D,n: error. T: recibe NULL si existe la columna y puede
devolver NULL final. ProvidedValues: NULL explícito permitido; ausencia es error.
ArrayAccess debe implementar offsetExists distinguiendo presencia de NULL.

## 19. Redondeo

HALF_UP, mitades alejándose de cero. D,2: 125.555 → 125.56;
-125.555 → -125.56. Escala 0..18. Cadenas calculadas sin float intermedio.
Se aceptan floats finitos con la precisión que ya traigan; preferir strings.

## 20. Precedencia wildcard de category

Driver y system exactos: category exacta primero, * segundo. Una definición
exacta no utilizable nunca se sustituye por *. La validación de plantilla busca
una definición efectiva utilizable por algún driver; la ejecución valida
el driver seleccionado y su allowlist.

## 21. Precedencia de transformers

1. Driver/system/category exacta/placeholder.
2. Driver/system/*/placeholder.
3. System/category exacta/placeholder genérico.
4. System/*/placeholder genérico.

Registro programático gana a config solo en el mismo nivel. No hay wildcard
en driver ni system. El placeholder siempre es exacto.

## 22. Seguridad

Allowlist, identificadores simples, bindings y ausencia de SQL raw.
Validación también de metadatos cargados de tablas externas. Escalares finales
finitos/UTF-8. Registro de clases exclusivamente en código/config.
Scopes, accessors y transformers del consumidor siguen siendo código confiable;
el consumidor conserva responsabilidad de autorización por usuario/tenant.

## 23. Número de consultas

| Ejecución | Catálogo | Origen |
| --- | --- | --- |
| Valores finales | 0 | 0 |
| Registro proporcionado | 1 | 0 |
| Clave única | 1 | 1 |
| Lista | 1 | ceil(claves únicas / 500) |
| Lista vacía | 0 | 0 |

Sin FieldElement se omite catálogo. No se incluyen carga previa del formato,
revalidación geométrica explícita ni consultas de código externo.
No se consulta papel durante la resolución de datos.

## 24. Pruebas agregadas

144 casos nuevos frente a 133 previos; 221 aserciones adicionales.
Cubren parser/formatter, precisión/NULL/negativos, registros/config inválida,
allowlists, unicidad, wildcard, drivers, cuatro precedencias de transformers,
contexto y registro completo, modos, faltantes, inmutabilidad, alias, scopes,
modelos independientes, conexiones, bindings, duplicados y lotes de 501 claves.
Se adaptó la aserción existente del default de PrintField a C.

## 25. Total final

277 pruebas y 525 aserciones, correctas en las tres ramas verificadas.

## 26. Compatibilidad y verificación

Runtime local PHP 8.2.28, SQLite en memoria.

| Framework | PHPUnit | Resultado |
| --- | --- | --- |
| Laravel 10.48.0 | 10.5.66 | 277 pruebas / 525 aserciones, correcto |
| Laravel 11.57.0 | 11.5.57 | 277 pruebas / 525 aserciones, correcto |
| Laravel 12.69.3 | 11.5.57 | 277 pruebas / 525 aserciones, correcto |

Composer validate --strict y check-platform-reqs pasaron en las tres instalaciones.
Pint final: passed. Parser PHP 8.1: 76 archivos válidos. YAML: siete combinaciones.
Un manifiesto temporal con platform.php=8.1.0 resolvió Laravel 10.50.3 y
brick/math 0.12.3 sin instalar ni ejecutar PHP 8.1.
La matriz existente mantiene Laravel 10/PHP 8.1, 8.2, 8.3 y
Laravel 11/12 con PHP 8.2 y 8.4.
El lock y vendor finales se restauraron a Laravel 12.69.3; Composer audit
final no encontró avisos de vulnerabilidad.

## 27. Límites y decisiones pendientes

Sin decisiones funcionales pendientes. Alcance ET, sin HTML, preview, UI, PDF
ni impresión. No hay caché persistente, streaming, JOIN, localización ni fórmulas.
Los lotes quedan en memoria. Claves de origen deben coincidir exactamente:
no se normalizan mayúsculas, espacios o ceros según collation.

El diseño se valida estructuralmente en resolución; papel y límites se validan
al guardar o al llamar explícitamente design()->validateFor().
No hay transacción distribuida entre catálogo y origen. Transformers deben ser
deterministas y evitar modificar el registro.

No se ejecutaron motores MySQL/SQL Server/PostgreSQL reales. Las conexiones
nombradas se probaron mediante SQLite. PHP 8.1 se comprueba por sintaxis y
resolución de dependencias, no mediante ejecución local de ese runtime.
GitHub Actions no se ejecutó desde este entorno. Las ramas Laravel 10/11
conservan avisos de seguridad conocidos; la excepción de resolución usada
para medir compatibilidad no modifica la política del composer.json.
