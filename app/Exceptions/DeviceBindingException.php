<?php

namespace App\Exceptions;

use RuntimeException;

class DeviceBindingException extends RuntimeException
{
    public function __construct(
        string $message,
        public int $status = 409,
    ) {
        parent::__construct($message);
    }
}