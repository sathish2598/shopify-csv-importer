<?php

namespace App\Exceptions;

use Exception;

class ShopifyApiException extends Exception
{
    public function __construct(string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
