<?php

namespace Finller\Mangopay\Models;

use Finller\Mangopay\Exceptions\MangopayUserException;
use Illuminate\Database\Eloquent\Model;
use MangoPay\Libraries\Exception;
use MangoPay\Libraries\ResponseException;
use MangoPay\MangoPayApi;
use MangoPay\User;

class MangopayPivot extends Model
{
    protected $table = 'mangopay_users';

    protected $fillable = [
        'mangopay_id',
        'kyc_level',
        'person_type',
    ];

    public function billable()
    {
        return $this->morphTo();
    }

    public static function findByMangopayId($Id)
    {
        return MangopayPivot::where(['mangopay_id' => $Id])->first();
    }

    public function mangopayApi(): MangopayApi
    {
        return app(MangoPayApi::class);
    }

    public function mangopayUser(): User
    {
        $api = $this->mangopayApi();
        $userId = $this->mangopay_id;
        if (! $userId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }

        try {
            $user = $api->Users->Get($userId);
        } catch (ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $user;
    }
}
