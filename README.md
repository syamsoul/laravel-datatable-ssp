# DataTable SSP (PHP) for Laravel



[![Latest Version on Packagist](https://img.shields.io/packagist/v/syamsoul/laravel-datatable-ssp.svg?style=flat-square)](https://packagist.org/packages/syamsoul/laravel-datatable-ssp)


## Documentation, Installation and Usage Instructions

See the [documentation](https://info.souldoit.com/projects/laravel-datatable-ssp) for detailed installation and usage instructions.


&nbsp;
&nbsp;
## Introduction

This package allows you to manage your DataTable from server-side in Laravel app (inspired by [original DataTable SSP](https://github.com/DataTables/DataTablesSrc/blob/master/examples/server_side/scripts/ssp.class.php)).


You can refer [here (click here)](https://datatables.net/examples/data_sources/server_side) about the implementation of original DataTable SSP.


&nbsp;
* [Requirement](#requirement)
* [Installation](#installation)
* [Usage & Reference](#usage--reference)
* [How to use it?](#how-to-use-it)
* [Example](#example)
* [In PHP (Controller)](#in-php-controller)
* [In Blade (Views)](#in-blade-views)


&nbsp;
&nbsp;
## Requirement

* Laravel 8.0 and above


&nbsp;
&nbsp;
## Installation


This package can be used in Laravel 8.0 or higher. If you are using an older version of Laravel, there's might be some problem. If there's any problem, you can [create new issue](https://github.com/syamsoul/laravel-datatable-ssp/issues) and I will fix it as soon as possible.

You can install the package via composer:

``` bash
composer require syamsoul/laravel-datatable-ssp
```
&nbsp;
***NOTE***: Please see [CHANGELOG](CHANGELOG.md) for more information about what has changed recently.

&nbsp;
&nbsp;
## Usage & Reference

\* Before you read this section, you can take a look [the example below](#example) to make it more clear to understand.

&nbsp;
### How to use it?

First, you must add this line to your Controller:
```php
use SoulDoit\DataTable\SSP;
```
&nbsp;

And then inject SSP service to Controller's method (or create instance using PHP `new` keyword):
```php
use SoulDoit\DataTable\SSP;

class MyController extends Controller
{
    public function get(SSP $ssp)
    {
        // or using `new` keyword:
        // $ssp = new SSP();

        $ssp->setColumns($dt_cols_opt);

        $ssp->setQuery($dt_query);

        return $ssp->response()->json();
    }
}
```

Which is:
* `$dt_query` is a QueryBuilder/EloquentBuilder or callable function that will return QueryBuilder/EloquentBuilder, for example:
    ```php
    $ssp->setQuery(function ($selected_columns) {
        return \App\Models\User::select($selected_columns);
    });
    ```

* `$dt_cols_opt` is an array of your columns' options, for example:
    ```php
    $ssp->setColumns([
        ['label'=>'ID',         'db'=>'id',            'formatter' => function ($value, $model) {
            return str_pad($value, 5, '0', STR_PAD_LEFT); 
        }],
        ['label'=>'Username',   'db'=>'uname'],
        ['label'=>'Email',      'db'=>'email'],
    ]);
    ```
    &nbsp;

    The available columns' options are as below:
    ```php
    [   
        'label'         => $dt_col_header,
        'db'            => $db_col_name,
        'class'         => $dt_class,
        'formatter'     => $dt_formatter,
    ],
    ```

    Which is:
    * `$dt_col_header` is the header of the column (at the table in views/blade), for example:
    ```php
    $dt_col_header = 'Username';
    ```
    * `$db_col_name` is column name based on the DB, for example:
    ```php
    $db_col_name = 'uname';
    ```
    * `$dt_class` is a class/classes name which will be added to the table (in views/blade), for example:
    ```php
    $dt_class = 'text-center';

    // or use array for multiple classes

    $dt_class = ['text-center', 'text-bold'];
    ```
    * `$dt_formatter` is like a modifier that can modify the data from DB to be shown in views/blade, for example:
    ```php
    $dt_formatter = function ($value, $model) {
        return ucwords($value);
        // which is 'value' is the value of the column

        // or
        return $model->name;
        // which is 'model' is the model of the current row

        // or
        return $value . '(#' .$model->id. ')';
    };
    ```

&nbsp;
&nbsp;
## Example


### In PHP (Controller)
```php
namespace App\Http\Controllers\AdminPanel;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use SoulDoit\DataTable\SSP;

class UsersController extends Controller
{
    private $ssp;
    
    public function __construct()
    {
        $ssp = new SSP();

        $ssp->enableSearch();
        $ssp->allowExportAllItemsInCsv();
        $ssp->setAllowedItemsPerPage([5, 10, 20, -1]);
        $ssp->setFrontendFramework('datatablejs');

        $ssp->setColumns([
            ['label'=>'ID',         'db'=>'id',            'formatter' => function ($value, $model) {
                return str_pad($value, 5, '0', STR_PAD_LEFT); 
            }],
            ['label'=>'Email',      'db'=>'email',         ],
            ['label'=>'Username',   'db'=>'uname',         ],
            ['label'=>'Created At', 'db'=>'created_at',    ],
            ['label'=>'Action',     'db'=>'id',            'formatter' => function ($value, $model) {
                $btns = [
                    '<button onclick="edit(\''.$value.'\');">Edit</button>',
                    '<button onclick="delete(\''.$value.'\');">Delete</button>',
                ];
                return implode($btns, " ");
            }],
            ['db'=>'email_verified_at'],
        ]);

        $ssp->setQuery(function ($selected_columns) {
            return \App\Models\User::select($selected_columns)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('id', '!=', 1);
                $query->orWhere('uname', '!=', 'superadmin');
            });
        });
        
        $this->ssp = $ssp;
    }
    
    public function page()
    {   
        return view('admin-panel.users-list', [
            'columns' => $this->ssp->getFrontEndColumns(),
            'is_search_enable' => $this->ssp->isSearchEnabled(),
            'allowed_items_per_page' => $this->ssp->getAllowedItemsPerPage(),
            'initial_items_per_page' => 10,
            'initial_order' => $this->ssp->getFrontEndInitialSorting('created_at', true),
        ]);
    }


    public function get()
    {
        return $this->ssp->response()->json();
    }
}
```

&nbsp;
### In Blade (Views)
```blade
<html>
    <head>
        <title>Laravel DataTable SSP</title>
    </head>
    <body>
        <table id="datatable_1" class="table table-striped table-bordered" style="width:100%;"></table>
        <script>
        $(document).ready(function(){
            $('#datatable_1').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('users.get') }}',
                columns: {!! json_encode($columns) !!},
                lengthMenu: [
                    {!! json_encode($allowed_items_per_page) !!},
                    {!! json_encode($allowed_items_per_page) !!}.map(x => (x == -1 ? 'All' : x) ),
                ],
                pageLength: {{ $initial_items_per_page }},
                searching: {{ $is_search_enable ? 'true' : 'false' }},
                order: {!! json_encode($initial_order) !!},
            });
        });

        function edit (id) {
            alert(`edit for user with id ${id}`);
        }

        function delete (id) {
            alert(`delete user with id ${id}`);
        }
        </script>
    </body>
</html>
```

&nbsp;
&nbsp;
## Support me

If you find this package helps you, kindly support me by donating some BNB (BSC) to the address below.

```
0x364d8eA5E7a4ce97e89f7b2cb7198d6d5DFe0aCe
```

<img src="https://info.souldoit.com/img/wallet-address-bnb-bsc.png" width="150">

&nbsp;
&nbsp;
## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.