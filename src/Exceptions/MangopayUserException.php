<?php

namespace Finller\Mangopay\Exceptions;

use Exception;

class MangopayUserException extends Exception
{
    public static function mangopayUserIdNotFound(string $className)
    {
        return new static(__("The mangopay user ID can't be found."));
    }

    public static function mangopayUserAlreadyExists(string $className)
    {
        return new static(__("A mangopay user already exists."));
    }

}
