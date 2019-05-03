# DataTable SSP (PHP) for Laravel



[![Latest Version on Packagist](https://img.shields.io/packagist/v/syamsoul/laravel-datatable-ssp.svg?style=flat-square)](https://packagist.org/packages/syamsoul/laravel-datatable-ssp)



This package allows you to manage your DataTable from server-side in Laravel app (inspired by [original DataTable SSP](https://github.com/DataTables/DataTablesSrc/blob/master/examples/server_side/scripts/ssp.class.php)).


You can refer [here (click here)](https://datatables.net/examples/data_sources/server_side) about the implementation of original DataTable SSP.


&nbsp;
* [Requirement](#requirement)
* [Installation](#installation)
* [Usage & Reference](#usage--reference)
  * [How to use it?](#how-to-use-it)
  * [Ordering/Sorting](#orderingsorting)
  * [Where/OrWhere](#whereorwhere)
  * [With Relationship](#with-relationship)
  * [Left Join](#left-join)
* [Example](#example)
  * [In PHP (Controller)](#in-php-controller)
  * [In Blade (Views)](#in-blade-views)


&nbsp;
&nbsp;
## Requirement

* Currently only tested in Laravel 5.8 (and it works perfectly)


&nbsp;
&nbsp;
## Installation


This package can be used in Laravel 5.8 or higher. If you are using an older version of Laravel, there's might be some problem. If there's any problem, you can [create new issue](https://github.com/syamsoul/laravel-datatable-ssp/issues) and I will fix it as soon as possible.

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

And then create a new SSP instance:
```php
$my_ssp = new SSP(String $model, Array $dt_cols_opt);
```

Which is:
* `$model` is a string of your model name or table name, for example:
  ```php
  $model = '\App\User';
  //or
  $model = 'users'; // NOTE: you should not include the prefix
  ```
* `$dt_cols_opt` is an array of your columns' options, for example:
   ```php
   $dt_cols_opt = [
       ['label'=>'ID',         'db'=>'id',            'dt'=>0, 'formatter'=>function($value, $model){ return str_pad($value, 5, '0', STR_PAD_LEFT); }],
       ['label'=>'Username',   'db'=>'uname',         'dt'=>1],
       ['label'=>'Email',      'db'=>'email',         'dt'=>2],
   ];
   ```
&nbsp;

The available columns' options are as below:
```php
[   
    'label'         => $dt_col_header,
    'db'            => $db_col_name,
    'dt'            => $dt_col_position,
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
* `$dt_col_position` is the position of the column (at the table in views/blade), start with 0, for example:
    ```php
    $dt_col_position = 2;
    ```
* `$dt_class` is a class/classes name which will be added to the table (in views/blade), for example:
    ```php
    $dt_class = 'text-center text-bold';
    ```
* `$dt_formatter` is like a modifier that can modify the data from DB to be shown in views/blade, for example:
    ```php
    $dt_formatter = function($value, $model){
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
### Ordering/Sorting
This is the default ordering for the first time the page is loaded.
```
$my_ssp->order($dt_col_position, $ordering);
```

Which is:
* `$dt_col_position` is based on the `dt` value that you set in `$dt_cols_opt` (Please refer above).
* `$ordering` only have two value whether `asc` or `desc`.

&nbsp;
### Where/OrWhere
```
$my_ssp->where($column_name, $column_value);

// or

$my_ssp->where($column_name, $column_value)->orWhere($column_name, $column_value);

// or

$my_ssp->where($query_function);
```
Which is:
* `$column_name` is the column name based on the database.
* `$column_value` is the value of the column.
* `$query_function` is a normal Laravel's query function.


&nbsp;
### With Relationship
```
$my_ssp->with($model_relationship);
```
Which is:
* `$model_relationship` is the relationship name applied to the model.

The usage of `with` feature is 100% same with the Laravel's `with`. FYI, `with` is used to `eager load` the relationship's data. You can refer [here (click here)](https://laravel.com/docs/5.8/eloquent-relationships#eager-loading) for more details.

***NOTE:*** `with` feature will **NOT** working if you use `table's name` as the first parameter in constructor. [e.g: `new SSP('users', $dt_cols_opt)` will IGNORE the `with`, but `new SSP('App\User', $dt_cols_opt)` will execute the `with`]

&nbsp;
### Left Join
```
$my_ssp->leftJoin($table_name, $table_one_column, $table_two_column);
```
Which is:
* `$table_name` is the string of the table's name.
* `$table_one_column` is the column's name from table #1.
* `$table_two_column` is the column's name from table #2.


For example:
```
$my_ssp->leftJoin('countries', 'users.country_id', 'countries.id'); // left join `countries` on `users`.`country_id` = `countries`.`id`

// OR

$my_ssp->leftJoin('countries AS ctry', 'users.country_id', 'ctry.id');
```

***NOTE:*** `leftJoin` feature will **NOT** working if you use `model's name` as the first parameter in constructor. [e.g: `new SSP('App\Countries', $dt_cols_opt)` will IGNORE the `leftJoin`, but `new SSP('countries', $dt_cols_opt)` will execute the `leftJoin`]


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
    public function list()
    {        
        $dt_obj = $this->dtSsp();
        
        return view('admin-panel.users-list', [
            'dt_info'       => $dt_obj->getInfo(),
        ]);
    }
    
    
    public function get($id=null)
    {
        $dt_obj = $this->dtSsp();
        
        return response()->json($dt_obj->getDtArr());
    }
    
    
    private function dtSsp()
    {
        $dt = [
            ['label'=>'ID',         'db'=>'id',            'dt'=>0, 'formatter'=>function($value, $model){ return str_pad($value, 5, '0', STR_PAD_LEFT); }],
            ['label'=>'Email',      'db'=>'email',         'dt'=>2],
            ['label'=>'Username',   'db'=>'uname',         'dt'=>1],
            ['label'=>'Created At', 'db'=>'created_at',    'dt'=>3],
            ['label'=>'Action',     'db'=>'id',            'dt'=>4, 'formatter'=>function($value, $model){ 
                $btns = [
                    '<button onclick="edit(\''.$value.'\');">Edit</button>',
                    '<button onclick="delete(\''.$value.'\');">Delete</button>',
                ];
                return implode($btns, " "); 
            }],
            ['db'=>'email_verified_at'],
        ];
        return (new SSP('\App\User', $dt))->where('status','active')->where(function($query){
            $query->where('id', '!=', 1);
            $query->orWhere('uname', '!=', 'superadmin');
        })->order(0, 'desc');
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
                    ajax: 'http://your-website.com/users/get',
                    columns: {!!json_encode($dt_info['labels'])!!},
                    order: {!!json_encode($dt_info['order'])!!},
                });
            });
            
            function edit(id){
                alert('edit for user with id '+id);
            }
            
            function delete(id){
                alert('delete user with id '+id);
            }
        </script>
    </body>
</html>    
```

&nbsp;
&nbsp;
## Support me

I am a passionate programmer. Please support me and I will continue to contribute my code to the world to make the  world better. :')

Please [make a donation](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=syamsoulazrien.miat@gmail.com&lc=US&item_name=Support%20me%20and%20I%20will%20contribute%20more&no_note=0&cn=&curency_code=USD&bn=PP-DonationsBF:btn_donateCC_LG.gif:NonHosted). :')

&#35;MalaysiaBoleh

&nbsp;
&nbsp;
## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.