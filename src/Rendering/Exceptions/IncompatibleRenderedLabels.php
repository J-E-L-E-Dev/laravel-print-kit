<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Rendering\Exceptions;

/** El documento requiere fragmentos del paquete con una sola medida física. */
class IncompatibleRenderedLabels extends \InvalidArgumentException {}
