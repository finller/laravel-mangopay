<?php

namespace Finller\Mangopay\Exceptions;

use Exception;

class CouldNotFindMangoUser extends Exception
{
    public static function mangoUserIdNotFound(string $className)
    {
        return new static("The mangopay User Id can't be find for $className");
    }
}