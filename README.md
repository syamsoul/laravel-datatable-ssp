# DataTable SSP (PHP) for Laravel



[![Latest Version on Packagist](https://img.shields.io/packagist/v/syamsoulcc/laravel-datatable-ssp.svg?style=flat-square)](https://packagist.org/packages/syamsoulcc/laravel-datatable-ssp)



This package allows you to manage your DataTable from server-side in Laravel app (inspired by [original DataTable SSP](https://github.com/DataTables/DataTablesSrc/blob/master/examples/server_side/scripts/ssp.class.php)).


You can refer [here](https://datatables.net/examples/data_sources/server_side) about the implementation of original DataTable SSP.


&nbsp;
&nbsp;
## Content List
* [Requirement](#requirement)
* [Installation](#installation)
* [Usage & Reference](#usage--reference)
* [Example](#example)


&nbsp;
&nbsp;
## Requirement

* Currently only tested in Laravel 5.8 (and it works perfectly)


&nbsp;
&nbsp;
## Installation


This package can be used in Laravel 5.8 or higher. If you are using an older version of Laravel, there's might be some problem. If there's any problem, you can [create new issue](https://github.com/syamsoulcc/laravel-datatable-ssp/issues) and I will fix it as soon as possible.

You can install the package via composer:

``` bash
composer require syamsoulcc/laravel-datatable-ssp
```

&nbsp;
&nbsp;
## Usage & Reference

\* Before you read this section, you can take a look [the example below](#example) to clearly understand.

&nbsp;
### How to use it?

Firstly, you must add this line to your Controller:
```php
use SoulDoit\DataTable\SSP;
```
&nbsp;

And then create a new SSP instance:
```php
$my_ssp = new SSP(String $model, Array $datatable);
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
    
	private function dtSsp(){
        $dt = [
            ['label'=>'ID',         'db'=>'id',            'dt'=>0, 'formatter'=>function($obj){ return str_pad($$obj['value'], 5, '0', STR_PAD_LEFT); }],
            ['label'=>'Email',      'db'=>'email',         'dt'=>2],
            ['label'=>'Username',   'db'=>'name',          'dt'=>1],
            ['label'=>'Created At', 'db'=>'created_at',    'dt'=>3],
            ['label'=>'Action',     'db'=>'id',            'dt'=>4, 'formatter'=>function($obj){ 
                $btns = [
                    '<button onclick="edit(\''.$obj['value'].'\');">Edit</button>',
                    '<button onclick="delete(\''.$obj['value'].'\');">Delete</button>',
                ];
                return implode($btns, " "); 
            }],
            ['db'=>'email_verified_at'],
        ];
        return (new SSP('\App\User', $dt))->order(0, 'desc');
    }
}
```

&nbsp;
### In Blade
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
&#35;HidupMelayu

&nbsp;
&nbsp;
## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.