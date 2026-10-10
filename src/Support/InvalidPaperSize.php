<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Support;

/** Indica que el papel solicitado falta o no corresponde al contexto de almacenamiento. */
class InvalidPaperSize extends \InvalidArgumentException {}
