<?php

namespace Finller\Mangopay\Traits;

use Finller\Mangopay\Exceptions\MangopayUserException;
use MangoPay\Wallet;

trait HasWallet
{
    use HasBankAccount;

    public function createMangopayWallet(array $data = []): \MangoPay\Wallet
    {
        $mangopayUserId = $this->mangopayUserId();
        if (! $mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }
        $api = $this->mangopayApi();

        try {
            $Wallet = new Wallet();
            $Wallet->Owners = [$mangopayUserId];
            $Wallet->Description = $data['Description'] ?? "main wallet";
            $Wallet->Currency = $data['Currency'] ?? config('mangopay.defaultCurrency');
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

    /**
     * Get all wallets owned by the user
     */
    public function mangopayWallets(): \Illuminate\Support\Collection
    {
        $mangopayUserId = $this->mangopayUserId();

        if (! $mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }

        $api = $this->mangopayApi();

        try {
            $mangopayWallets = collect($api->Users->GetWallets($mangopayUserId));
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangopayWallets;
    }

    /**
     * Get all wallets owned by the user
     */
    public function getMangopayWallet($walletId): \MangoPay\Wallet
    {
        $api = $this->mangopayApi();

        try {
            $mangopayWallet = $api->Wallets->Get($walletId);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangopayWallet;
    }

    /**
     * Transfer money from a wallet to another
     */
    public function createMangopayTransfer(array $data = []): \MangoPay\Transfer
    {
        $mangopayUserId = $this->mangopayUserId();

        if (! $mangopayUserId) {
            throw MangopayUserException::mangopayUserIdNotFound(get_class($this));
        }

        $api = $this->mangopayApi();

        try {
            $Transfer = new \MangoPay\Transfer();
            $Transfer->AuthorId = $mangopayUserId;
            $Transfer->CreditedUserId = $data['CreditedUserId'] ?? null;
            $Transfer->DebitedFunds = new \MangoPay\Money();
            $Transfer->DebitedFunds->Currency = $data['DebitedFunds']['Currency'];
            $Transfer->DebitedFunds->Amount = $data['DebitedFunds']['Amount'];
            $Transfer->Fees = new \MangoPay\Money();
            $Transfer->Fees->Currency = $data['Fees']['Currency'];
            $Transfer->Fees->Amount = $data['Fees']['Amount'];
            $Transfer->DebitedWalletId = $data['DebitedWalletId'];
            $Transfer->CreditedWalletId = $data['CreditedWalletId'];
            $Transfer->Tag = $data['Tag'] ?? null;

            $mangopayTransfer = $api->Transfers->Create($Transfer);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangopayTransfer;
    }
}
