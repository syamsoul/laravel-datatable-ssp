# DataTable SSP (PHP) for Laravel



[![Latest Version on Packagist](https://img.shields.io/packagist/v/syamsoulcc/laravel-datatable-ssp.svg?style=flat-square)](https://packagist.org/packages/syamsoulcc/laravel-datatable-ssp)



This package allows you to manage your DataTable from server-side in Laravel app (inspired by [original DataTable SSP](https://github.com/DataTables/DataTablesSrc/blob/master/examples/server_side/scripts/ssp.class.php)).


You can refer [here](https://datatables.net/examples/data_sources/server_side) about the implementation of original DataTable SSP.


## Content List
* [Requirement](#requirement)
* [Installation](#installation)
* [Usage & Example](#usage--example)


## Requirement

* Currently only tested in Laravel 5.8 (and it works perfectly)



## Installation


This package can be used in Laravel 5.8 or higher. If you are using an older version of Laravel, there's might be some problem. If there's any problem, you can [create new issue](https://github.com/syamsoulcc/laravel-datatable-ssp/issues) and I will fix it as soon as possible.

You can install the package via composer:

``` bash
composer require syamsoulcc/laravel-datatable-ssp
```


## Usage & Example

First, add the `use SoulDoit\DataTable\SSP;` to your Controller:

```php
namespace App\Http\Controllers\AdminPanel;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use SoulDoit\DataTable\SSP;

class UsersController extends Controller
{
```


## Support me

I am a passionate programmer. Please support me and I will continue to contribute my code to the world to make the  world better. :')

Please [make a donation](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=syamsoulazrien.miat@gmail.com&lc=US&item_name=Support%20me%20and%20I%20will%20contribute%20more&no_note=0&cn=&curency_code=USD&bn=PP-DonationsBF:btn_donateCC_LG.gif:NonHosted). :')

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.