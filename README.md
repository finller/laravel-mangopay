# Mangopay package for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/finller/laravel-mangopay.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-mangopay)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/finller/laravel-mangopay/run-tests?label=tests)](https://github.com/finller/laravel-mangopay/actions?query=workflow%3Arun-tests+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/finller/laravel-mangopay.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-mangopay)

This package allow you to use mangopay api with your Model. The goal is to makes the api more natural and user friendly to use.
Under the hood, it uses the mangopay official php sdk.

**IMPORTANT: This package only provide Direct Debit PayIn with SEPA mandate for the moment. If you want to do credit card PayIn you can to use the service provider which is the php mangopay sdk**

Just had a trait to your model
```PHP
class User extends Authenticatable
{
    use HasMangopayUser;
}
```

And then, you have plenty of functions to work easily with mangopay.

```PHP
$user->updateOrCreateMangopayUser();
$user->createMangopayBankAccount();
$user->createMangopayTransfer();
```

It also provide a Service : MangopayServiceProvider, so you can have access to the mangopay sdk if you need.

## Installation

Install the package via composer:

```bash
composer require finller/laravel-mangopay
```

You have to publish and run the migrations with:

```bash
php artisan vendor:publish --provider="Finller\Mangopay\MangopayServiceProvider" --tag="migrations"
php artisan migrate
```

You have to publish the config file with:

```bash
php artisan vendor:publish --provider="Finller\Mangopay\MangopayServiceProvider" --tag="config"
```

This is the content of the published config file:
A temporary folder has to be specified as well as api credentials.

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

This package works with a Trait, the trait gives you plenty of functions and most of all it makes a link between your database and the mangopay data.

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

By default, the mangopay user is LEGAL. You can define if your user is NATURAL (a person) or LEGAL (a company or an organization) like that:

```PHP
class Company extends Model
{
    use HasMangopayUser;

    protected function mangopayUserIsLegal(){
        return true; //or use some logic to determine the value
    };
}
```

If you already store the data of your users in your database and you want to sync it with mangopay, just add:

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
These data will be used when you call `$user->createMangopayUser();` or `$user->updateMangopayUser();`.

In the exemple, all personnal data needed by Mangopay are fetch from your Model.
**Please note that the only information stored by this package in the database are the mangopay user id and the mangopay user KYC level.**

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
array from the method `buildMangopayUserData` and array passed as variable will be merged.

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

please note that some fields are mandatory to be able to create a mangopay User (please see to the mangopay docs).

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

There are a lot of functions to manage everything, so don't hesitate to explore the trait (methods names are pretty clear).

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

### Retreive Laravel User and Mangopay User from Mangopay User Id

This situation will happen very often when dealing with Mangopay hooks. You can use the `MangopayPivot` Model.

```PHP
use Finller\Mangopay\Models\MangopayPivot;

$mangopayUserId = "123456";

$pivot = MangopayPivot::findByMangopayId($mangopayUserId);

$laravelUser = $pivot->billable;//this will give you the laravel User or whatever Model you use

$mangopayUser = $pivot->mangopayUser();//this will give you the Mangopay User Object

```

## Managing Mangopay Hooks

This is just an example of how you can deal with Mangopay hooks in Laravel.

### Setup the route

```PHP
Route::namespace('Mangopay')->group(function () {
    Route::get('hook/mangopay/payin', 'MangopayHookController@payin');

    Route::get('hook/mangopay/payout', 'MangopayHookController@payout');

    Route::get('hook/mangopay/kyc', [MangopayHookController::class, "kyc"]);
});
```

## Define the controller

```PHP
use App\Events\Mangopay\PayInFailed;
use App\Events\Mangopay\PayInSucceeded;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use MangoPay\EventType;


class MangopayHookController extends Controller
{
    /**
     * Handle mangopay hook
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function payin(Request $request)
    {
        $eventType = $request->input('EventType');
        $Id = $request->input('RessourceId');
        $date = $request->input('Timestamp');

        if (!!$Id and !!$eventType) {
            switch ($eventType) {
                case EventType::PayinNormalSucceeded:
                    event(new PayInSucceeded($Id, $date));
                    break;
                case EventType::PayinNormalFailed:
                    event(new PayInFailed($Id, $date));
                    break;
            }
        }

        //you have to respond in less than 2 secondes with a 200 status code
        return response();
    }
}
```

## Define the event

And you can listen to mangopay Hooks !

```PHP
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MangoPay\PayIn;

class PayInFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $Id;
    public $date;
    public $type = PayIn::class;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($Id, $date)
    {
        $this->Id = $Id;
        $this->date = $date;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}

```

## Deploy in production

By default the mangopay sdk use the sandbox api url. If you want to go in production you have to define the production api url in the config file like this:

```PHP
return [
    'api' => [
        'id' => env('MANGOPAY_ID'),
        'secret' => env('MANGOPAY_KEY'),
        'url' => env('MANGOPAY_URL', "https://api.mangopay.com") //<-- this is the production base url
    ],
    'folder' => storage_path('mangopay'),
    'defaultCurrency' => 'EUR',
];
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
