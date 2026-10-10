<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data\Exceptions;

/** El catálogo contiene dos filas con la misma identidad lógica dentro de una resolución. */
class DuplicatePrintFieldDefinition extends \InvalidArgumentException {}
