<?php

namespace Finller\Mangopay\Models;

use Illuminate\Database\Eloquent\Model;

class MangopayPivot extends Model
{
    protected $table = 'mangopay_users';

    protected $fillable = [
        'mangopay_id',
        'kyc_level',
        'person_type'
    ];

    public function billable()
    {

        return $this->morphTo();
    }

    public function findByMangopayId($Id)
    {
        return MangopayPivot::where(['mangopay_id' => $Id])->first();
    }
}
