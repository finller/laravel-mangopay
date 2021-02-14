<?php

namespace Finller\Mangopay\Traits;

use Finller\Mangopay\Exceptions\MangopayUserException;
use Finller\Mangopay\Models\BillableMangopay;
use Illuminate\Support\Facades\Validator;
use MangoPay\FilterKycDocuments;
use MangoPay\KycDocument;
use MangoPay\KycPage;
use MangoPay\Libraries\Exception;
use MangoPay\Libraries\ResponseException;
use MangoPay\MangoPayApi;
use MangoPay\User;
use MangoPay\UserLegal;
use MangoPay\UserNatural;

trait HasMangopayUser
{
    use HasWallet;

    /**
     * Define if you want to create a natural or legal user by default
     */
    protected $mangopayUserIsLegal = true;

    public function mangopayApi(): MangopayApi
    {
        return app(MangoPayApi::class);
    }

    //USER ----------------------------------------

    /**
     * Check if a mangopay user exists in the database
     */
    public function hasMangopayUser(): bool
    {
        return BillableMangopay::where(['billable_type' => get_class($this), 'billable_id' => $this->id])->exists();
    }

    public function mangopayUserPivot()
    {
        $pivot = BillableMangopay::where(['billable_type' => get_class($this), 'billable_id' => $this->id])->first();

        return $pivot ?? false;
    }

    public function mangopayUserId()
    {
        $pivot = $this->mangopayUserPivot();

        return $pivot ? $pivot->mangopay_id : false;
    }

    public function createOrUpdateMangopayUser(array $data = [])
    {
        return $this->hasMangopayUser() ? $this->updateMangopayUser($data) : $this->createMangopayUser($data);
    }

    public function mangopayUser(): User
    {
        $api = $this->mangopayApi();
        $userId = $this->mangopayUserId();
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

    public function createMangopayUser(array $data = [])
    {
        if ($this->hasMangopayUser()) {
            throw MangopayUserException::mangopayUserAlreadyExists(get_class($this));
        }
        $data = array_merge($this->buildMangopayUserData(), $data);

        //create the mangopay user
        $user = $this->mangopayUserIsLegal ? $this->createLegalMangoUser($data) : $this->createNaturalMangoUser($data);

        //save the mangopay user id in database
        $pivot = BillableMangopay::create(['mangopay_id' => $user->Id, 'billable_id' => $this->id, 'billable_type' => get_class($this)]);

        return $user;
    }

    public function updateMangopayUser(array $data = [])
    {
        $pivot = $this->mangopayUserPivot();
        if (! $pivot) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }

        $mangopayUserId = $pivot->mangopay_id;

        $data['Id'] = $mangopayUserId;
        $data = array_merge($this->buildMangopayUserData(), $data);

        $user = $this->mangopayUserIsLegal ? $this->updateLegalMangopayUser($data) : $this->updateNaturalMangopayUser($data);

        $pivot->touch();

        return $user;
    }

    /**
     * Define the link between your database and mangopay
     */
    public function buildMangopayUserData(): array
    {
        return [];
    }

    protected function createNaturalMangopayUser(array $data = []): UserNatural
    {
        $api = $this->mangopayApi();
        $user = $this->buildNaturalMangopayUserObject();

        //Send the request
        $mangopayUser = $api->Users->Create($user);

        return $mangopayUser;
    }

    /**
     * create the legal user in mangopay
     */
    protected function createLegalMangoUser(array $data = []): UserLegal
    {
        $api = $this->mangopayApi();

        $UserLegal = $this->buildLegalMangopayUserObject($data);

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
     * update the legal user in mangopay
     */
    protected function updateLegalMangopayUser(array $data = []): UserLegal
    {
        $api = $this->mangopayApi();

        $UserLegal = $this->buildLegalMangopayUserObject($data);

        try {
            $mangoUser = $api->Users->Update($UserLegal);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            return $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            return $e;
        }

        return $mangoUser;
    }

    protected function updateNaturalMangopayUser(array $data = []): UserNatural
    {
        $api = $this->mangopayApi();

        $UserNatural = $this->buildNaturalMangopayUserObject($data);

        try {
            $mangopayUser = $api->Users->Update($UserNatural);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            return $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            return $e;
        }

        return $mangopayUser;
    }

    protected function buildLegalMangopayUserObject(array $data = []): UserLegal
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

    protected function buildNaturalMangopayUserObject(array $data = []): UserNatural
    {
        $user = new UserNatural();

        $user->PersonType = "NATURAL";
        $user->FirstName = $data['FirstName'];
        $user->LastName = $data['LastName'];
        $user->Birthday = $data['Birthday'];
        $user->Nationality = $data['Nationality'];
        $user->CountryOfResidence = $data['CountryOfResidence'];
        $user->Email = $data['Email'];

        return $user;
    }

    /**
     * Validate mangopay api requirements for natural user creation
     */
    protected function validateNaturalMangopayUser(): bool
    {
        return ! Validator::make($this->buildMangopayUserData(), [
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

    /**
     * Validate mangopay api requirements for legal user creation
     */
    protected function validateLegalMangopayUser(): bool
    {
        $data = $this->buildMangopayUserData();

        $rules = [
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
            $rules = array_merge($rules, [
                'LegalRepresentativeAddress.AddressLine1' => 'string',
                'LegalRepresentativeAddress.AddressLine2' => ['nullable', 'string'],
                'LegalRepresentativeAddress.City' => 'string',
                'LegalRepresentativeAddress.Region' => ['nullable', 'string'],
                'LegalRepresentativeAddress.PostalCode' => 'string',
                'LegalRepresentativeAddress.Country' => ['string', 'max:2'],
            ]);
        }

        return ! Validator::make($data, $rules)->fails();
    }

    public function validateMangopayUser(): bool
    {
        return $this->mangopayUserIsLegal ? $this->validateLegalMangopayUser() : $this->validateNaturalMangopayUser();
    }

    //KYC -------------------------------------------

    public function mangopayKycDocuments($type = null, $status = null)
    {
        $mangopayUserId = $this->mangopayUserId();
        if (! $mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        if (isset($type) or isset($status)) {
            $kycFilter = new  FilterKycDocuments();
            $kycFilter->Status = $status;
            $kycFilter->Type = $type;
        }


        try {
            $mangoKycDocuments = $api->Users->GetKycDocuments($mangopayUserId, null, null, $kycFilter);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangoKycDocuments;
    }

    /**
     * Available types are: IDENTITY_PROOF, REGISTRATION_PROOF, ARTICLES_OF_ASSOCIATION, SHAREHOLDER_DECLARATION, ADDRESS_PROOF
     */
    public function createMangopayKycDocument(string $type): KycDocument
    {
        $mangopayUserId = $this->mangopayUserId();
        if (! $mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();
        $KycDocument = new KycDocument();
        $KycDocument->Type = $type;

        try {
            $mangopayKycDocument = $api->Users->CreateKycDocument($mangopayUserId, $KycDocument);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangopayKycDocument;
    }

    public function createMangopayKycPage(int $kycDocumentId, $file): bool
    {
        $mangopayUserId = $this->mangopayUserId();
        if (! $mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        $KycPage = new KycPage();
        $KycPage->File = $file;

        try {
            $mangopayKycPage = $api->Users->CreateKycPageFromFile($mangopayUserId, $kycDocumentId, $KycPage);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangopayKycPage;
    }

    public function submitMangopayKycDocument(int $kycDocumentId): KycDocument
    {
        $mangopayUserId = $this->mangopayUserId();
        if (! $mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        //submit the doc for validation
        $KycDocument = new KycDocument();
        $KycDocument->Id = $kycDocumentId;
        $KycDocument->Status = KycDocumentStatus::ValidationAsked; // VALIDATION_ASKED

        try {
            $mangopayKycDocument = $api->Users->UpdateKycDocument($mangopayUserId, $KycDocument);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangopayKycDocument;
    }
}
