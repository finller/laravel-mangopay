<?php

namespace Finller\Mangopay\Traits;

use Illuminate\Support\Facades\Validator;
use MangoPay\MangoPayApi;
use MangoPay\UserLegal;

trait HasLegalUser
{
    /**
     * create the legal user in mangopay
     */
    protected function createLegalMangoUser(array $data = []): UserLegal
    {
        $api = app(MangoPayApi::class);

        $UserLegal = $this->buildLegalMangoUserObject($data);

        try {
            $mangoUser = $api->Users->Create($UserLegal);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            return $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            return $e;
        }
        //Send the request

        return $mangoUser;
    }

    /**
     * create the legal user in mangopay
     */
    protected function updateLegalMangoUser(array $data = []): UserLegal
    {
        $api = app(MangoPayApi::class);

        $UserLegal = $this->buildLegalMangoUserObject($data);

        try {
            $mangoUser = $api->Users->Update($UserLegal);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            return $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            return $e;
        }
        //Send the request

        return $mangoUser;
    }

    protected function buildLegalMangoUserObject(array $data = []): UserLegal
    {
        $UserLegal = new UserLegal();
        $UserLegal->LegalPersonType = "BUSINESS";
        $UserLegal->Name = $data['Name'];

        $UserLegal->HeadquartersAddress = new \MangoPay\Address();
        $UserLegal->HeadquartersAddress->AddressLine1 = $data['HeadquartersAddress']['AddressLine1'];
        $UserLegal->HeadquartersAddress->AddressLine2 = $data['HeadquartersAddress']['AddressLine2'];
        $UserLegal->HeadquartersAddress->City = $data['HeadquartersAddress']['City'];
        $UserLegal->HeadquartersAddress->Region = $data['HeadquartersAddress']['Region'];
        $UserLegal->HeadquartersAddress->PostalCode = $data['HeadquartersAddress']['PostalCode'];
        $UserLegal->HeadquartersAddress->Country = $data['HeadquartersAddress']['Country'];

        //representative
        if (isset($data['LegalRepresentativeAddress'])) {
            $UserLegal->LegalRepresentativeAddress = new \MangoPay\Address();
            $UserLegal->LegalRepresentativeAddress->AddressLine1 = $data['LegalRepresentativeAddress']['AddressLine1'];
            $UserLegal->LegalRepresentativeAddress->AddressLine2 = $data['LegalRepresentativeAddress']['AddressLine2'] ?? null;
            $UserLegal->LegalRepresentativeAddress->City = $data['LegalRepresentativeAddress']['City'];
            $UserLegal->LegalRepresentativeAddress->Region = $data['LegalRepresentativeAddress']['Region'];
            $UserLegal->LegalRepresentativeAddress->PostalCode = $data['LegalRepresentativeAddress']['PostalCode'];
            $UserLegal->LegalRepresentativeAddress->Country = $data['LegalRepresentativeAddress']['Country'];
        }

        $UserLegal->LegalRepresentativeEmail = $data['LegalRepresentativeEmail'] ?? null;
        $UserLegal->LegalRepresentativeBirthday = $data['LegalRepresentativeBirthday'];
        $UserLegal->LegalRepresentativeCountryOfResidence = $data['LegalRepresentativeCountryOfResidence'];
        $UserLegal->LegalRepresentativeNationality = $data['LegalRepresentativeNationality'];
        $UserLegal->LegalRepresentativeFirstName = $data['LegalRepresentativeFirstName'];
        $UserLegal->LegalRepresentativeLastName = $data['LegalRepresentativeLastName'];

        $UserLegal->Email = $data['Email'];
        $UserLegal->CompanyNumber = $data['CompanyNumber'] ?? null;

        if (isset($data['Id'])) {
            $UserLegal->Id = $data['Id'];
        }

        return $UserLegal;
    }

    // Validation

    /**
     * Validate mangopay api requirements for legal user creation
     */
    protected function isLegalMangoValid(): bool
    {
        $data = $this->buildMangoUserData();

        $validate = [
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
        ];

        if (isset($data['LegalRepresentativeAddress'])) {
            $validate = array_merge($validate, [
                'LegalRepresentativeAddress.AddressLine1' => 'string',
                'LegalRepresentativeAddress.AddressLine2' => ['nullable', 'string'],
                'LegalRepresentativeAddress.City' => 'string',
                'LegalRepresentativeAddress.Region' => ['nullable', 'string'],
                'LegalRepresentativeAddress.PostalCode' => 'string',
                'LegalRepresentativeAddress.Country' => ['string', 'max:2'],
            ]);
        }

        return ! Validator::make($data, $validate)->fails();
    }
}
