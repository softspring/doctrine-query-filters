<?php

namespace Softspring\Component\DoctrineQueryFilters\Exception;

use Exception;
use Throwable;

class MissingFromInQueryBuilderException extends Exception
{
    public function __construct($message = 'Before running Filters apply you must add a from', $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
