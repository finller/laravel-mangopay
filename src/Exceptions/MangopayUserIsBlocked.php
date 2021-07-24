<?php

namespace Finller\Mangopay\Exceptions;

use Exception;

class MangopayUserIsBlocked extends Exception
{
    public function __construct()
    {
        parent::__construct(__('The mangopay user is blocked'));
    }
}
