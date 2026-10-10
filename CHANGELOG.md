# Changelog

## [1.0.0] - Unreleased

### Added

- Diseño y validación de etiquetas con campos y textos.
- Catálogos configurables de formatos, papeles y campos.
- Resolución desde valores, registros proporcionados y bases de datos, con transformers y procesamiento por lotes.
- Renderizado HTML para preview e impresión desde navegador.
- Representación QR de campos mediante SVG.
- API HTTP opcional para formatos, catálogos, validación y preview.

### Compatibility

- PHP 8.1+ dentro de la rama 8, según los requisitos de cada versión compatible del framework.
- Laravel 10.48+, 11 y 12.
- BaconQrCode 2 y 3.

### Notes

- Geometría en centímetros y tipografía en puntos.
- API deshabilitada por defecto.
- Autenticación y autorización administradas por el consumidor.
- Las copias no se expanden automáticamente en el renderer.
- No incluye interfaz visual, PDF ni impresión directa.
