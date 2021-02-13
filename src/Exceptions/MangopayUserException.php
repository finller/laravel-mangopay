<?php

namespace Finller\Mangopay\Exceptions;

use Exception;

class MangopayUserException extends Exception
{
    public static function mangopayUserIdNotFound(string $className)
    {
        return new static("The mangopay User Id can't be find for $className");
    }

    public static function mangopayUserAlreadyExists(string $className)
    {
        return new static("The mangopay User already exists for this $className");
    }
}
