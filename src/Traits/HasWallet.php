<?php

namespace Finller\Mangopay\Traits;

use Finller\Mangopay\Exceptions\CouldNotFindMangoUser;
use Finller\Mangopay\Models\BillableMangopay;
use MangoPay\Libraries\Exception;
use MangoPay\Libraries\ResponseException;
use MangoPay\MangoPayApi;
use MangoPay\User;
use MangoPay\Wallet;

trait HasWallet
{
    use HasLegalUser;
    use HasNaturalUser;
    use HasKycDocuments;
    use HasBankAccount;

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
            $Wallet->Owners = [$mangoId];
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

    public function getWallets()
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

    public function createTransfert(array $data = [])
    {
        $mangoId = $this->getMangoUserId();

        if (!$mangoId) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }

        $api = app(MangoPayApi::class);
        try {
            $Transfer = new \MangoPay\Transfer();
            $Transfer->AuthorId = $mangoId;
            $Transfer->CreditedUserId = $data['CreditedUserId'] ?? null;
            $Transfer->DebitedFunds = new \MangoPay\Money();
            $Transfer->DebitedFunds->Currency = $data['DebitedFunds']['Currency'];
            $Transfer->DebitedFunds->Amount = $data['DebitedFunds']['Currency'];
            $Transfer->Fees = new \MangoPay\Money();
            $Transfer->Fees->Currency = $data['Fees']['Currency'];
            $Transfer->Fees->Amount = $data['Fees']['Amount'];
            $Transfer->DebitedWalletId = $data['DebitedWalletId'];
            $Transfer->CreditedWalletId = $data['CreditedWalletId'];
            $Transfer->Tag = $data['Tag'] ?? null;
            $mangoTransfer = $api->Transfers->Create($Transfer);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangoTransfer;
    }

    /**
     * Define the link between your database and mangopay
     */
    public function buildMangoUserData(): array
    {
        return [];
    }
}
