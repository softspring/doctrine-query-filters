<?php

namespace Softspring\Component\DoctrineQueryFilters\Exception;

class MissingFromInQueryBuilderException extends \Exception
{
    public function __construct($message = "Before running Filters apply you must add a from", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}