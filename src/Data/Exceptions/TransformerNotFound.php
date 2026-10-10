<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data\Exceptions;

/** Un campo de tipo T carece de transformer en los cuatro niveles de precedencia. */
class TransformerNotFound extends \InvalidArgumentException {}
