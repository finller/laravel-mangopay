<?php

namespace Finller\Mangopay\Traits;

use Finller\Mangopay\Models\BillableMangopay;
use Illuminate\Support\Facades\Validator;
use MangoPay\Libraries\Exception;
use MangoPay\Libraries\ResponseException;
use MangoPay\MangoPayApi;
use MangoPay\User;
use MangoPay\UserLegal;
use MangoPay\UserNatural;

trait HasNaturalUser
{

    protected function createNaturalMangoUser(array $data = []): UserNatural
    {
        $api = app(MangoPayApi::class);
        $mangoUser = new \MangoPay\UserNatural();
        $mangoUser->PersonType = "NATURAL";
        $mangoUser->FirstName = 'John';
        $mangoUser->LastName = 'Doe';
        $mangoUser->Birthday = 1409735187;
        $mangoUser->Nationality = "FR";
        $mangoUser->CountryOfResidence = "FR";
        $mangoUser->Email = 'john.doe@mail.com';

        //Send the request
        $mangoUser = $api->Users->Create($mangoUser);

        return $mangoUser;
    }

    /**
     * Validate mangopay api requirements for natural user creation
     */
    public function isNaturalMangoValid(): bool
    {
        return !Validator::make($this->buildMangoUserData(), [
            'Name' => 'string',
            'Email' => 'email',
            'HeadquartersAddress.AddressLine1' => 'string',
            'HeadquartersAddress.AddressLine2' => ['nullable', 'string'],
            'HeadquartersAddress.City' => 'string',
            'HeadquartersAddress.Region' => ['nullable', 'string'],
            'HeadquartersAddress.PostalCode' => 'string',
            'HeadquartersAddress.Country' => ['string', 'max:2'],

            "LegalRepresentativeEmail" => ['nullable', 'email'],
            "LegalRepresentativeBirthday" => 'numeric',
            "LegalRepresentativeCountryOfResidence" => ['string', 'max:2'],
            "LegalRepresentativeNationality" => ['string', 'max:2'],
            "LegalRepresentativeFirstName" => 'string',
            "LegalRepresentativeLastName" => 'string',

            'LegalRepresentativeAddress.AddressLine1' => 'string',
            'LegalRepresentativeAddress.AddressLine2' => ['nullable', 'string'],
            'LegalRepresentativeAddress.City' => 'string',
            'LegalRepresentativeAddress.Region' => ['nullable', 'string'],
            'LegalRepresentativeAddress.PostalCode' => 'string',
            'LegalRepresentativeAddress.Country' => ['string', 'max:2'],
        ])->fails();
    }

}
