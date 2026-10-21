<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Exceptions;

use RuntimeException;

final class UnknownBlockTypeException extends RuntimeException
{
    public function __construct(string $type)
    {
        parent::__construct("No page builder block type is registered for key [{$type}].");
    }
}
