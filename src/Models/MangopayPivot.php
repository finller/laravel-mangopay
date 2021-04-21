<?php

namespace Finller\Mangopay\Models;

use Illuminate\Database\Eloquent\Model;

class MangopayPivot extends Model
{
    protected $table = 'mangopay_users';

    protected $fillable = [
        'mangopay_id',
    ];
}
