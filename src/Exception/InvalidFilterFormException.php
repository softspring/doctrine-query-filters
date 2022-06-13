<?php

namespace Softspring\Component\DoctrineQueryFilters\Exception;

use Softspring\Component\DoctrineQueryFilters\FilterFormInterface;

class InvalidFilterFormException extends \Exception
{
    public function __construct($message = '', $code = 0, \Throwable $previous = null)
    {
        $message = $message ?: sprintf('$filterForm type must implement %s', FilterFormInterface::class);
        parent::__construct($message, $code, $previous);
    }
}
