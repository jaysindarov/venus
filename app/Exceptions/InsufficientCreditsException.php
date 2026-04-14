<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InsufficientCreditsException extends RuntimeException
{
    public function __construct(string $message = 'Insufficient credits to complete this action.')
    {
        parent::__construct($message);
    }
}
