<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data\Exceptions;

/** El registro no contiene el atributo físico requerido; no equivale a un valor NULL. */
class AssociatedFieldNotFound extends \InvalidArgumentException {}
