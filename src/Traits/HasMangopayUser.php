<?php

namespace Finller\Mangopay\Traits;

use Finller\Mangopay\Exceptions\MangopayUserException;
use Finller\Mangopay\Models\MangopayPivot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use MangoPay\Address;
use MangoPay\Birthplace;
use MangoPay\FilterKycDocuments;
use MangoPay\KycDocument;
use MangoPay\KycDocumentStatus;
use MangoPay\Libraries\Exception;
use MangoPay\Libraries\ResponseException;
use MangoPay\MangoPayApi;
use MangoPay\Ubo;
use MangoPay\UboDeclaration;
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

    protected function mangopayLegal(): bool
    {
        return method_exists($this, 'mangopayUserIsLegal') ? $this->mangopayUserIsLegal() : $this->mangopayUserIsLegal;
    }

    public function mangopayApi(): MangopayApi
    {
        return app(MangoPayApi::class);
    }

    //USER ----------------------------------------

    public function mangopayPivot()
    {
        return $this->morphOne(MangopayPivot::class, 'billable');
    }

    /**
     * Check if a mangopay user exists in the database
     */
    public function hasMangopayUser(): bool
    {
        return !!$this->mangopayPivot;
    }

    public function scopeHasMangopayUser(Builder $query, $value): Builder
    {
        return $query->has('mangopayPivot');
    }

    public function mangopayUserId()
    {
        $pivot = $this->mangopayPivot;

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
        if (!$userId) {
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
        $user = $this->mangopayLegal() ? $this->createLegalMangoUser($data) : $this->createNaturalMangoUser($data);

        //save the mangopay user id in database
        $pivot = $this->mangopayPivot()->create(['mangopay_id' => $user->Id]);

        return $user;
    }

    public function updateMangopayUser(array $data = [])
    {
        $pivot = $this->mangopayPivot;
        if (!$pivot) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }

        $mangopayUserId = $pivot->mangopay_id;

        $data['Id'] = $mangopayUserId;
        $data = array_merge($this->buildMangopayUserData(), $data);

        $user = $this->mangopayLegal() ? $this->updateLegalMangopayUser($data) : $this->updateNaturalMangopayUser($data);

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

        $UserLegal->HeadquartersAddress = new Address();
        $UserLegal->HeadquartersAddress->AddressLine1 = $data['HeadquartersAddress']['AddressLine1'];
        $UserLegal->HeadquartersAddress->AddressLine2 = $data['HeadquartersAddress']['AddressLine2'];
        $UserLegal->HeadquartersAddress->City = $data['HeadquartersAddress']['City'];
        $UserLegal->HeadquartersAddress->Region = $data['HeadquartersAddress']['Region'];
        $UserLegal->HeadquartersAddress->PostalCode = $data['HeadquartersAddress']['PostalCode'];
        $UserLegal->HeadquartersAddress->Country = $data['HeadquartersAddress']['Country'];

        //representative
        if (isset($data['LegalRepresentativeAddress'])) {
            $UserLegal->LegalRepresentativeAddress = new Address();
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
        return !Validator::make($this->buildMangopayUserData(), [
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

        return !Validator::make($data, $rules)->fails();
    }

    public function validateMangopayUser(): bool
    {
        return $this->mangopayLegal() ? $this->validateLegalMangopayUser() : $this->validateNaturalMangopayUser();
    }

    //KYC -------------------------------------------

    public function mangopayKycDocuments($type = null, $status = null): Collection
    {
        $mangopayUserId = $this->mangopayUserId();
        if (!$mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        if (isset($type) or isset($status)) {
            $kycFilter = new  FilterKycDocuments();
            $kycFilter->Status = $status;
            $kycFilter->Type = $type;
        } else {
            $kycFilter = null;
        }

        $pagination = null;


        try {
            $mangoKycDocuments = collect($api->Users->GetKycDocuments($mangopayUserId, $pagination, null, $kycFilter));
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangoKycDocuments;
    }

    public function getMangopayKycDocument($kycDocumentId): KycDocument
    {
        $mangopayUserId = $this->mangopayUserId();
        if (!$mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        try {
            $mangoKycDocuments = $api->Users->GetKycDocument($mangopayUserId, $kycDocumentId);
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
        if (!$mangopayUserId) {
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

    public function createMangopayKycPage(int $kycDocumentId, string $filePath): bool
    {
        $mangopayUserId = $this->mangopayUserId();
        if (!$mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        try {
            $mangopayKycPage = $api->Users->CreateKycPageFromFile($mangopayUserId, $kycDocumentId, $filePath);
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
        if (!$mangopayUserId) {
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

    public function createMangopayUboDeclaration(): UboDeclaration
    {
        $mangopayUserId = $this->mangopayUserId();
        if (!$mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        try {
            $uboDeclaration = $api->UboDeclarations->Create($mangopayUserId);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $uboDeclaration;
    }

    public function createMangopayUbo($uboDeclarationId, array $data): Ubo
    {
        $mangopayUserId = $this->mangopayUserId();
        if (!$mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        $ubo = new Ubo();
        $ubo->FirstName = $data['FirstName'];
        $ubo->LastName = $data['LastName'];
        $ubo->Address = new Address();
        $ubo->Address->AddressLine1 = $data['Address']['AddressLine1'];
        $ubo->Address->AddressLine2 = $data['Address']['AddressLine2'] ?? null;
        $ubo->Address->City = $data['Address']['City'];
        if (isset($data['Address']['Region'])) {
            $ubo->Address->Region = $data['Address']['Region'];
        }
        $ubo->Address->PostalCode = $data['Address']['PostalCode'];
        $ubo->Address->Country = $data['Address']['Country'];
        $ubo->Nationality = $data['Nationality'];
        $ubo->Birthday = $data['Birthday'];
        $ubo->Birthplace = new Birthplace();
        $ubo->Birthplace->City = $data['Birthplace']['City'];
        $ubo->Birthplace->Country = $data['Birthplace']['Country'];

        try {
            $ubo = $api->UboDeclarations->CreateUbo($mangopayUserId, $uboDeclarationId, $ubo);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $ubo;
    }

    public function updateMangopayUbo($uboDeclarationId, array $data): Ubo
    {
        $mangopayUserId = $this->mangopayUserId();
        if (!$mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        $ubo = new Ubo();
        $ubo->Id = $data['Id'];

        $ubo->FirstName = $data['FirstName'];
        $ubo->LastName = $data['LastName'];

        $ubo->Address = new Address();
        $ubo->Address->AddressLine1 = $data['Address']['AddressLine1'];
        $ubo->Address->AddressLine2 = $data['Address']['AddressLine2'] ?? null;
        $ubo->Address->City = $data['Address']['City'];
        if (isset($data['Address']['Region'])) {
            $ubo->Address->Region = $data['Address']['Region'];
        }
        $ubo->Address->PostalCode = $data['Address']['PostalCode'];
        $ubo->Address->Country = $data['Address']['Country'];

        $ubo->Nationality = $data['Nationality'];
        $ubo->Birthday = $data['Birthday'];

        $ubo->Birthplace = new Birthplace();
        $ubo->Birthplace->City = $data['Birthplace']['City'];
        $ubo->Birthplace->Country = $data['Birthplace']['Country'];

        if (isset($data['isActive'])) {
            $ubo->isActive = $data['isActive'];
        }

        try {
            $ubo = $api->UboDeclarations->UpdateUbo($mangopayUserId, $uboDeclarationId, $ubo);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $ubo;
    }

    public function submitMangopayUboDeclaration($uboDeclarationId): UboDeclaration
    {
        $mangopayUserId = $this->mangopayUserId();
        if (!$mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        try {
            $uboDeclaration = $api->UboDeclarations->SubmitForValidation($mangopayUserId, $uboDeclarationId);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $uboDeclaration;
    }

    public function getMangopayUboDeclaration($uboDeclarationId): UboDeclaration
    {
        $api = $this->mangopayApi();

        try {
            $uboDeclaration = $api->UboDeclarations->GetById($uboDeclarationId);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $uboDeclaration;
    }

    public function mangopayUboDeclarations(): Collection
    {
        $mangopayUserId = $this->mangopayUserId();
        if (!$mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        try {
            $uboDeclarations = collect($api->UboDeclarations->GetAll($mangopayUserId));
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $uboDeclarations;
    }
}
