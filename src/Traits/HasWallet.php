<?php

namespace Finller\Mangopay\Traits;

use Finller\Mangopay\Exceptions\CouldNotFindMangoUser;
use Finller\Mangopay\Models\BillableMangopay;
use MangoPay\BankAccount;
use MangoPay\BankAccountDetailsIBAN;
use MangoPay\Libraries\Exception;
use MangoPay\Libraries\ResponseException;
use MangoPay\MangoPayApi;
use MangoPay\User;
use MangoPay\Wallet;

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
        if (!$pivot) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }

        try {
            $mangoUser = $api->Users->Get($pivot->mangopay_id);
        } catch (ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangoUser;
    }


    public function getMangoBankAccounts()
    {
        $pivot = BillableMangopay::where(['billable_type' => get_class($this), 'billable_id' => $this->id])->first();
        if (!$pivot) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }

        $api = app(MangoPayApi::class);


        try {
            $mangoUser = collect($api->Users->GetBankAccounts($pivot->mangopay_id));
        } catch (ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangoUser;
    }

    public function createBankAccount(array $data): BankAccount
    {
        $pivot = BillableMangopay::where(['billable_type' => get_class($this), 'billable_id' => $this->id])->first();
        if (!$pivot) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }

        $api = app(MangoPayApi::class);

        $bankAccount = new BankAccount();
        $bankAccount->Type = 'IBAN';
        $bankAccount->Tag = $data['Tag'] ?? $data['OwnerName'];
        $bankAccount->OwnerName = $data['OwnerName'];
        $bankAccount->OwnerAddress = new \MangoPay\Address();
        $bankAccount->OwnerAddress->AddressLine1 = $data['OwnerAddress']['AddressLine1'];
        $bankAccount->OwnerAddress->City = $data['OwnerAddress']['City'];
        $bankAccount->OwnerAddress->PostalCode = $data['OwnerAddress']['PostalCode'];
        $bankAccount->OwnerAddress->Country = $data['OwnerAddress']['Country'];

        $bankAccount->Details = new BankAccountDetailsIBAN();
        $bankAccount->Details->IBAN = $data['IBAN'];
        $bankAccount->Details->BIC = $data['BIC'] ?? null;

        try {
            $mangoBankAccount = $api->Users->CreateBankAccount($pivot->mangopay_id, $bankAccount);
        } catch (ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangoBankAccount;
    }

    public function createMangoUser(array $data = [])
    {
        if ($this->hasMangoUser()) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }
        $data = array_merge($this->buildMangoUserData(), $data);

        $user = $this->isLegal ? $this->createLegalMangoUser($data) : $this->createNaturalMangoUser($data);

        BillableMangopay::create(['mangopay_id' => $user->Id, 'billable_id' => $this->id, 'billable_type' => get_class($this)]);

        return $user;
    }

    public function updateMangoUser(array $data = [])
    {
        if (!$this->hasMangoUser()) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
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

    public function createMangoWallet(array $data = []): Wallet
    {
        $mangoId = $this->getMangoUserId();

        if (!$mangoId) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }

        $api = app(MangoPayApi::class);

        try {
            $Wallet = new Wallet();
            $Wallet->Owners = array($mangoId);
            $Wallet->Description = $data['Description'] ?? "main wallet";
            $Wallet->Currency = $data['Currency'] ?? "EUR";
            $Wallet->Tag = $data['Tag'] ?? "main";
            $mangoWallet = $api->Wallets->Create($Wallet);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage() 
            throw $e;
        }

        return $mangoWallet;
    }

    public function getMangoWallets()
    {
        $mangoId = $this->getMangoUserId();

        if (!$mangoId) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }

        $api = app(MangoPayApi::class);

        try {
            $mangoWallets = collect($api->Users->GetWallets($mangoId));
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage() 
            throw $e;
        }

        return $mangoWallets;
    }

    /**
     * Define the link between your database and mangopay
     */
    public function buildMangoUserData(): array
    {
        return [];
    }
}
