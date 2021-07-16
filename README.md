# Mangopay package for Laravel
[![Latest Version on Packagist](https://img.shields.io/packagist/v/finller/laravel-mangopay.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-mangopay)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/finller/laravel-mangopay/run-tests?label=tests)](https://github.com/finller/laravel-mangopay/actions?query=workflow%3Arun-tests+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/finller/laravel-mangopay.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-mangopay)

**This package is in alpha and should no be use in production until v1 release**

This package allow you to use mangopay api with your Model.
Under the hood, it use the mangopay official php sdk.

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
    'folder' => '',
    'defaultCurrency' => 'EUR',
];
```

## Usage

### Setup your Model

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

If you already store user data in your database and you want to sync it with mangopay, just add:
The only data stored by this package in the database is the mangopay user id.

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

### Manage your mangopay user

Then you can just create and update your mangopay user like that:

```PHP
$user->createMangopayUser();
//or
$user->updateMangopayUser();
//or
$user->updateOrCreateMangopayUser();
```

### Manage your mangopay wallets

```PHP
$user->createMangopayWallet([
    'Description'=>'Main Wallet',
    'Currency'=>'EUR',
    'Tag'=>'main'
]);

//get the list of the user's wallets
$user->mangopayWallets();
```

### Do PayIn and PayOut

```php
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
    'CreditedUserId'=>123456,//default is the owner of the wallet
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
