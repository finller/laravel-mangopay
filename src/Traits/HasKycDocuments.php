<?php

namespace Finller\Mangopay\Traits;

use MangoPay\KycDocument;
use MangoPay\KycDocumentStatus;
use MangoPay\KycPage;
use MangoPay\MangoPayApi;

trait HasKycDocuments
{
    public function getKycDocuments()
    {
        $mangoUserId = $this->getMangoUserId();

        if (! $mangoUserId) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }

        $api = app(MangoPayApi::class);

        try {
            $mangoKycDocuments = $api->Users->GetKycDocuments($mangoUserId);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangoKycDocuments;
    }

    public function createKycDocument(): KycDocument
    {
        $mangoUserId = $this->getMangoUserId();

        if (! $mangoUserId) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }

        $api = app(MangoPayApi::class);

        try {
            $KycDocument = new KycDocument();
            $KycDocument->Type = "IDENTITY_PROOF";
            $mangoKycDocument = $api->Users->CreateKycDocument($mangoUserId, $KycDocument);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangoKycDocument;
    }

    public function createKycPage(int $kycDocumentId, $file)
    {
        $mangoUserId = $this->getMangoUserId();

        if (! $mangoUserId) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }

        $api = app(MangoPayApi::class);

        try {
            $KycPage = new KycPage();
            $KycPage->File = $file;
            $mangoKycPage = $api->Users->CreateKycPageFromFile($mangoUserId, $kycDocumentId, $KycPage);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangoKycPage;
    }

    public function submitKycDocument(int $kycDocumentId)
    {
        $mangoUserId = $this->getMangoUserId();

        if (! $mangoUserId) {
            throw CouldNotFindMangoUser::mangoUserIdNotFound(get_class($this));
        }

        $api = app(MangoPayApi::class);

        try {
            //submit the doc for validation
            $KycDocument = new KycDocument();
            $KycDocument->Id = $kycDocumentId;
            $KycDocument->Status = KycDocumentStatus::ValidationAsked; // VALIDATION_ASKED
            $mangoKycDocument = $api->Users->UpdateKycDocument($KycDocument);
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s) $e->GetErrorDetails()
            throw $e;
        } catch (MangoPay\Libraries\Exception $e) {
            // handle/log the exception $e->GetMessage()
            throw $e;
        }

        return $mangoKycDocument;
    }
}
