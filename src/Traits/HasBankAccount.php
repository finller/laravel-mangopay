<?php

namespace Finller\Mangopay\Traits;

use Finller\Mangopay\Exceptions\CouldNotFindMangoUser;
use Finller\Mangopay\Models\BillableMangopay;
use MangoPay\BankAccount;
use MangoPay\BankAccountDetailsIBAN;
use MangoPay\Libraries\Exception;
use MangoPay\Libraries\ResponseException;
use MangoPay\Mandate;
use MangoPay\MangoPayApi;
use MangoPay\Sorting;

trait HasBankAccount
{

    public function getBankAccounts()
    {
        $pivot = BillableMangopay::where(['billable_type' => get_class($this), 'billable_id' => $this->id])->first();
        if (!$pivot) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }

        $api = app(MangoPayApi::class);


        try {
            $pagination = null;
            $sorting = new Sorting();
            $sorting->AddField('CreationDate', 'DESC');

            $mangoUser = collect($api->Users->GetBankAccounts($pivot->mangopay_id, $pagination, $sorting));
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

    public function createMandate(array $data = []): Mandate
    {
        $mangoId = $this->getMangoUserId();
        if (!$mangoId) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }

        $api = app(MangoPayApi::class);

        try {
            $Mandate = new Mandate();
            $Mandate->BankAccountId = $data['BankAccountId'];
            $Mandate->Culture = $data['Culture'] ?? "EN";
            $Mandate->ReturnURL = $data['ReturnURL'] ?? secure_url('/');
            $mangoMandate = $api->Mandates->Create($Mandate);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangoMandate;
    }

    public function getMandate(int $mandateId)
    {
        $mangoId = $this->getMangoUserId();
        if (!$mangoId) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }

        $api = app(MangoPayApi::class);

        try {
            $mangoMandate = $api->Mandates->Get($mandateId);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangoMandate;
    }

    public function cancelMandate(int $mandateId)
    {
        $mangoId = $this->getMangoUserId();
        if (!$mangoId) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }

        //only the owner can cancel his mandates
        $mandate = $this->getMandate($mandateId);
        if ($mandate->UserId != $mangoId) {
            return false;
        }

        $api = app(MangoPayApi::class);

        try {
            $mangoMandate = $api->Mandates->Cancel($mandateId);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangoMandate;
    }

    public function getMandates()
    {
        $mangoId = $this->getMangoUserId();
        if (!$mangoId) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }

        $api = app(MangoPayApi::class);

        try {
            $mangoMandates = $api->Users->GetMandates($mangoId);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return collect($mangoMandates);
    }

    public function getBankAccountMandates($bankAccountId)
    {
        $mangoId = $this->getMangoUserId();
        if (!$mangoId) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }

        $api = app(MangoPayApi::class);

        try {
            $pagination = null;
            $filter = null;
            $sorting = new Sorting();
            $sorting->AddField('CreationDate', 'DESC');
            $mangoMandates = $api->Users->GetMandatesForBankAccount($mangoId, $bankAccountId, $pagination, $filter, $sorting);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return collect($mangoMandates);
    }
}
