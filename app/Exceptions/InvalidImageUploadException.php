<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidImageUploadException extends RuntimeException
{
    public function __construct(string $message = 'Invalid file type.')
    {
        parent::__construct($message);
    }
}
