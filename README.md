# Mangopay package for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/finller/laravel-mangopay.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-mangopay)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/finller/laravel-mangopay/run-tests?label=tests)](https://github.com/finller/laravel-mangopay/actions?query=workflow%3Arun-tests+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/finller/laravel-mangopay.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-mangopay)

This package allow you to use mangopay api with your Model. The goal is to makes the api more natural and user friendly to use.
Under the hood, it uses the mangopay official php sdk.

**IMPORTANT: This package only provide buildin Direct Debit PayIn with SEPA mandate for the moment. If you want to do credit card PayIn you have to use the service provider wich is the php sdk**

```PHP
class User extends Authenticatable
{
    use HasMangopayUser;
}
```

And then, you have plenty of function to work easily.

```PHP
$user->updateOrCreateMangopayUser();
$user->createMangopayBankAccount();
$user->createMangopayTransfer();
```

It provide a Service : MangopayServiceProvider, so you can have access to the mangopay sdk if you want.

## Installation

You can install the package via composer:

```bash
composer require finller/laravel-mangopay
```

You have to publish and run the migrations with:

```bash
php artisan vendor:publish --provider="Finller\Mangopay\MangopayServiceProvider" --tag="migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --provider="Finller\Mangopay\MangopayServiceProvider" --tag="config"
```

This is the contents of the published config file:
A temporary folder has to be specified.

```php
return [
    'api' => [
        'id' => '',
        'secret' => '',
    ],
    'folder' => storage_path('mangopay'),
    'defaultCurrency' => 'EUR',
];
```

## Usage

### Setup your Model

This package works with a Trait, the trait gives you plenty of function and most of all makes a link between your database user and the mangopay user.

You can use the trait on any Model, not just User.

```php
use Finller\Mangopay\Traits\HasMangopayUser;

class User extends Authenticatable
{
    use HasMangopayUser;
}
// or
class Company extends Model
{
    use HasMangopayUser;
}
```

By default, the mangopay user is LEGAL. You can define if your user is NATURAL (a person) or LEGAL (a company or organization) like that:

```PHP
class Company extends Model
{
    use HasMangopayUser;

    protected function mangopayUserIsLegal(){
        return true; //or use some logic to dertimine the value
    };
}
```

If you already store user data in your database and you want to sync it with mangopay, just add:

```php
use Finller\Mangopay\Traits\HasMangopayUser;

class User extends Authenticatable
{
    use HasMangopayUser;

    public function buildMangopayUserData(): array
    {
        return [
            'Name' => $this->company_name,
            'Email' => $this->email,
            'HeadquartersAddress' => [
                'AddressLine1' => $this->address->street,
                'AddressLine2' => null,
                'City' => $this->address->city,
                'Region' => null,
                'PostalCode' => $this->address->postal_code,
                'Country' => $this->address->country_code,
            ],
            "LegalRepresentativeEmail" => $this->representative->email,
            "LegalRepresentativeBirthday" => $this->representative->birthdate->getTimestamp(),
            "LegalRepresentativeCountryOfResidence" => $this->representative->country_code,
            "LegalRepresentativeNationality" => $this->representative->nationality_code,
            "LegalRepresentativeFirstName" => $this->representative->first_name,
            "LegalRepresentativeLastName" => $this->representative->last_name,
        ];
    }
}
```

In the exemple, all personnal data needed by Mangopay are fetch from your Model.
Please note that the only data stored by this package in the database is the mangopay user id and the mangopay user KYC level.

### Create and update your mangopay user

Then you can just create and update your mangopay user like that:

```PHP
$user->createMangopayUser();
//or
$user->updateMangopayUser();
//or
$user->updateOrCreateMangopayUser();
```

If you do not use `buildMangopayUserData` method, or if you want to override it, you can pass an array of data:

```PHP
$user->createMangopayUser([
            'Name' => $this->company_name,
            'Email' => 'put your email here',
            'HeadquartersAddress' => [
                'AddressLine1' => $this->address->street,
                'AddressLine2' => null,
                'City' => $this->address->city,
                'Region' => null,
                'PostalCode' => $this->address->postal_code,
                'Country' => $this->address->country_code,
            ],
            "LegalRepresentativeEmail" => $this->representative->email,
            "LegalRepresentativeBirthday" => $this->representative->birthdate->getTimestamp(),
            "LegalRepresentativeCountryOfResidence" => $this->representative->country_code,
            "LegalRepresentativeNationality" => $this->representative->nationality_code,
            "LegalRepresentativeFirstName" => $this->representative->first_name,
            "LegalRepresentativeLastName" => $this->representative->last_name,
        ]);
```

please note, that some fields are mandatory to be able to create a mangopay User (please see to the mangopay docs).

### Manage your mangopay wallets

```PHP
$user->createMangopayWallet([
    'Description'=>'Main Wallet',
    'Currency'=>'EUR',
    'Tag'=>'a name or any info'
]);

//get the list of the user's wallets
$user->mangopayWallets();
```

### Add Bank account and mandate

```PHP
$bankAccount = $company->createMangopayBankAccount([
            'IBAN' => 'an IBAN',
            'Tag' => 'any name or tag',
            'BIC' => 'BIC is optional',
            'OwnerName' => 'the name',
            'OwnerAddress' => [
                'AddressLine1' => 'street',
                'AddressLine2' => null,
                'City' => 'the city name',
                'Region' => 'region is required for some countries',
                'PostalCode' => ' a postal code',
                'Country' => 'country code like FR, ...',
            ],
        ]);

//retreive all users bank accounts
$bankAccounts = $company->mangopayBankAccounts();

$mandate = $company->createMangopayMandate([
    'BankAccountId'=> "xxxx",
    'Culture'=> 'FR',
    'ReturnURL'=>'your-website.com'
]);

```

There is a lot of function to manage everything, so don't hesitate to explore the trait (methods names are pretty clear).

### Do PayIn and PayOut

**IMPORTANT: This package only support Direct Debit PayIn with SEPA mandate for the moment. If you want to do credit card PayIn you have to use the service provider and so the php sdk**

```php
//SEPA PayIn
$payIn = $user->createMangopayMandatePayIn([
    'DebitedFunds'=>[
        'Amount'=>1260,//12.60€
        'Currency'=>'EUR',
    ],
    'Fees'=>[
        'Amount'=>0,//0€
        'Currency'=>'EUR',
    ],
    'BankAccountId'=>123456,
    'CreditedWalletId'=>123456,
    'CreditedUserId'=>123456,//by default it's the owner of the wallet
    'MandateId'=>123456,
    'StatementDescriptor'=>'Your company name or a ref',
]);

$payout = $user->createMangopayPayOut([
    'DebitedFunds'=>[
        'Amount'=>1260,//12.60€
        'Currency'=>'EUR',
    ],
    'Fees'=>[
        'Amount'=>0,//0€
        'Currency'=>'EUR',
    ],
    'BankAccountId'=>123456,
    'DebitedWalletId'=>7891011,
    'BankWireRef'=>'Your company name or a ref',
]);

```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [Quentin Gabriele](https://github.com/QuentinGabriele)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
