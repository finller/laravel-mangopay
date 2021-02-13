<?php

namespace Finller\Mangopay\Traits;

use Finller\Mangopay\Exceptions\MangopayUserException;
use Illuminate\Support\Collection;
use MangoPay\BankAccount;
use MangoPay\BankAccountDetailsIBAN;
use MangoPay\Libraries\Exception;
use MangoPay\Libraries\ResponseException;
use MangoPay\Mandate;
use MangoPay\MangoPayApi;
use MangoPay\Money;
use MangoPay\PayIn;
use MangoPay\PayInExecutionDetailsDirect;
use MangoPay\PayInPaymentDetailsDirectDebit;
use MangoPay\PayOut;
use MangoPay\Sorting;

trait HasBankAccount
{
    public function mangopayApi(): MangoPayApi
    {
        return app(MangoPayApi::class);
    }

    public function mangopayBankAccounts()
    {
        $mangopayUserId = $this->mangopayUserId();
        if (! $mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        try {
            $pagination = null;
            $sorting = new Sorting();
            $sorting->AddField('CreationDate', 'DESC');

            $mangopayUser = collect($api->Users->GetBankAccounts($mangopayUserId, $pagination, $sorting));
        } catch (ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangopayUser;
    }

    public function createMangopayBankAccount(array $data): BankAccount
    {
        $mangopayUserId = $this->mangopayUserId();
        if (! $mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

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
            $mangopayBankAccount = $api->Users->CreateBankAccount($mangopayUserId, $bankAccount);
        } catch (ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangopayBankAccount;
    }

    public function createMangopayMandate(array $data = []): Mandate
    {
        $mangopayUserId = $this->mangopayUserId();
        if (! $mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        try {
            $Mandate = new Mandate();
            $Mandate->BankAccountId = $data['BankAccountId'];
            $Mandate->Culture = $data['Culture'] ?? "EN";
            $Mandate->ReturnURL = $data['ReturnURL'] ?? secure_url('/');

            $mangopayMandate = $api->Mandates->Create($Mandate);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangopayMandate;
    }

    public function getMangopayMandate(int $mandateId)
    {
        $api = $this->mangopayApi();

        try {
            $mangopayMandate = $api->Mandates->Get($mandateId);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangopayMandate;
    }

    public function cancelMangopayMandate(int $mandateId)
    {
        $mangopayUserId = $this->mangopayUserId();
        if (! $mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        //only the owner can cancel his mandates
        $mandate = $this->getMandate($mandateId);
        if ($mandate->UserId != $mangopayUserId) {
            return false;
        }


        try {
            $mangopayMandate = $api->Mandates->Cancel($mandateId);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangopayMandate;
    }

    public function mangopayMandates(): Collection
    {
        $mangopayUserId = $this->mangopayUserId();
        if (! $mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        try {
            $mangoMandates = $api->Users->GetMandates($mangopayUserId);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return collect($mangoMandates);
    }

    public function getMangopayBankAccountMandates($bankAccountId): Collection
    {
        $mangopayUserId = $this->mangopayUserId();
        if (! $mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        try {
            $pagination = null;
            $filter = null;
            $sorting = new Sorting();
            $sorting->AddField('CreationDate', 'DESC');
            $mangoMandates = $api->Users->GetMandatesForBankAccount($mangopayUserId, $bankAccountId, $pagination, $filter, $sorting);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return collect($mangoMandates);
    }

    /**
     * Credite a wallet from SEPA PayIn
     * https://docs.mangopay.com/endpoints/v2.01/payins#e282_create-a-direct-debit-direct-payin
     */
    public function createMangopayMandatePayIn(array $data)
    {
        $mangopayUserId = $this->mangopayUserId();
        if (! $mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        $payIn = new PayIn();
        $payIn->AuthorId = $mangopayUserId;

        $payIn->CreditedWalletId = $data['CreditedWalletId'];
        if ($data['CreditedUserId']) {
            //defaults is the owner of the wallet
            $payIn->CreditedUserId = $data['CreditedUserId'];
        }

        $payIn->DebitedFunds = new Money();
        $payIn->DebitedFunds->Amount = $data['DebitedFunds']['Amount'];
        $payIn->DebitedFunds->Currency = $data['DebitedFunds']['Currency'] ?? config('mangopay.defaultCurrency');
        $payIn->Fees = new Money();
        $payIn->Fees->Amount = $data['Fees']['Amount'];
        $payIn->Fees->Currency = $data['Fees']['Currency'] ?? config('mangopay.defaultCurrency');

        $payIn->PaymentDetails = new PayInPaymentDetailsDirectDebit();
        $payIn->PaymentDetails->MandateId = $data['MandateId'];
        // execution type as DIRECT
        $payIn->ExecutionDetails = new PayInExecutionDetailsDirect();

        if (isset($data['StatementDescriptor'])) {
            //A custom description to appear on the user's bank statement.
            //It can be up to 100 characters long, and can only include alphanumeric characters or spaces.
            $payIn->StatementDescriptor = $data['StatementDescriptor'];
        }

        try {
            $mangopayPayIn = $api->PayIns->Create($payIn);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangopayPayIn;
    }

    public function createMangopayPayOut(array $data): PayOut
    {
        $mangopayUserId = $this->mangopayUserId();
        if (!$mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        $payOut = new PayOut();
        $payOut->AuthorId = $mangopayUserId;

        $payOut->DebitedFunds = new Money();
        $payOut->DebitedFunds->Amount = $data['DebitedFunds']['Amount'];
        $payOut->DebitedFunds->Currency = $data['DebitedFunds']['Currency'] ?? config('mangopay.defaultCurrency');

        $payOut->Fees = new Money();
        $payOut->Fees->Amount = $data['Fees']['Amount'];
        $payOut->Fees->Currency = $data['Fees']['Currency'] ?? config('mangopay.defaultCurrency');

        $payOut->BankAccountId = $data['BankAccountId'];
        $payOut->DebitedWalletId = $data['DebitedWalletId'];

        if (isset($data['BankWireRef'])) {
            $payOut->BankWireRef = $data['BankWireRef'];
        }
        try {
            $mangopayPayOut = $api->PayOuts->Create($payOut);
        } catch (ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangopayPayOut;
    }
}
