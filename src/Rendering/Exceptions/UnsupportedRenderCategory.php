<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Rendering\Exceptions;

/** La categoría solicitada no tiene renderer en esta fase de etiquetas ET. */
class UnsupportedRenderCategory extends \InvalidArgumentException {}
