<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data\Exceptions;

/** La conexión seleccionada no pertenece a la lista configurada de drivers permitidos. */
class UnknownDriver extends \InvalidArgumentException {}
