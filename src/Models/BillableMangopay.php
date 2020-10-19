<?php

namespace Finller\Mangopay\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class BillableMangopay extends Pivot
{
    protected $table = 'mangopay_users';
}