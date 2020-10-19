<?php

namespace Finller\Mangopay\Traits;

use Finller\Mangopay\Models\BillableMangopay;
use MangoPay\Libraries\Exception;
use MangoPay\Libraries\ResponseException;
use MangoPay\MangoPayApi;
use MangoPay\User;

trait HasWallet
{
    use HasLegalUser;
    use HasNaturalUser;

    protected $isLegal = true;

    public function getMangoPayApi(): MangopayApi
    {
        return app(MangoPayApi::class);
    }

    public function hasMangoUser(): bool
    {
        return BillableMangopay::where(['billable_type' => get_class($this), 'billable_id' => $this->id])->exists();
    }

    public function getMangoUserId()
    {
        $pivot = $this->getMangoUserPivot();
        if ($pivot) {
            return $pivot->mangopay_id;
        }

        return false;
    }

    public function getMangoUserPivot()
    {
        $pivot = BillableMangopay::where(['billable_type' => get_class($this), 'billable_id' => $this->id])->first();
        if ($pivot) {
            return $pivot;
        }

        return false;
    }

    public function getMangoUser(): User
    {
        $api = app(MangoPayApi::class);
        $pivot = BillableMangopay::where(['billable_type' => get_class($this), 'billable_id' => $this->id])->first();
        if (! $pivot) {
            return null;
        }

        try {
            $mangoUser = $api->Users->Get($pivot->mangopay_id);
        } catch (ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
        } catch (Exception $e) {
            // handle/log the exception $e->GetMessage()
        }

        return $mangoUser;
    }

    public function mangoWallet()
    {
        # code...
    }

    public function createMangoUser(array $data = [])
    {
        if ($this->hasMangoUser()) {
            return null;
        }
        $data = array_merge($this->buildMangoUserData(), $data);

        $user = $this->isLegal ? $this->createLegalMangoUser($data) : $this->createNaturalMangoUser($data);

        BillableMangopay::create(['mangopay_id' => $user->Id, 'billable_id' => $this->id, 'billable_type' => get_class($this)]);

        return $user;
    }

    public function updateMangoUser(array $data = [])
    {
        if (! $this->hasMangoUser()) {
            return null;
        }

        $pivot = $this->getMangoUserPivot();
        $mangopay_id = $pivot->mangopay_id;

        $data = array_merge($data, ['Id' => $mangopay_id]);
        $data = array_merge($this->buildMangoUserData(), $data);

        $user = $this->isLegal ? $this->updateLegalMangoUser($data) : $this->updateNaturalMangoUser($data);

        $pivot->touch();

        return $user;
    }

    public function isMangoValid(): bool
    {
        return $this->isLegal ? $this->isLegalMangoValid() : $this->isNaturalMangoValid();
    }

    /**
     * Define the link between your database and mangopay
     */
    public function buildMangoUserData(): array
    {
        return [];

        // [
        //     'Name' => '',
        //     'HeadquartersAddress' => [
        //         'AddressLine1' => '',
        //         'AddressLine2' => '',
        //         'City' => '',
        //         'Region' => '',
        //         'PostalCode' => '',
        //         'Country' => '',
        //     ],

        //     "LegalRepresentativeEmail" => '',
        //     "LegalRepresentativeBirthday" => '',
        //     "LegalRepresentativeCountryOfResidence" => '',
        //     "LegalRepresentativeNationality" => '',
        //     "LegalRepresentativeFirstName" => '',
        //     "LegalRepresentativeLastName" => '',

        //     'LegalRepresentativeAddress' => [
        //         'AddressLine1' => '',
        //         'AddressLine2' => '',
        //         'City' => '',
        //         'Region' => '',
        //         'PostalCode' => '',
        //         'Country' => '',
        //     ],
        // ];
    }
}
