<?php
namespace SoulDoit\DataTable;

use DB;
use App;
use Arr;

class SSP{
    
    /*
    |--------------------------------------------------------------------------
    | DataTable SSP for Laravel
    |--------------------------------------------------------------------------
    |
    | Author    : Syamsoul Azrien Muda (+60139584638)
    | Website   : https://github.com/syamsoulcc
    |
    */
    
    private $model;
    private $table;
    private $is_model=true;
    private $table_prefix;
    private $request;
    private $cols_info=[];
    private $cols;
    //private $cols_db_k;
    private $cols_dt_k;
    private $cols_arr=[];
    private $cols_raw_arr=[];
    private $cols_exc_arr=[];
    private $normal_data;
    private $dt_arr;
    private $total_count;
    private $filter_count;
    private $where_query=[];
    private $join_query=[];
    private $with_related_table;
    private $order;
    
    function __construct($model, $cols){
        $this->table_prefix = DB::getTablePrefix() ?? "";
                
        $this->request  = request()->all();
             
        if(class_exists($model)){
            $this->model    = $model;
            $this->table = (new $model())->getTable();
        }else{
            $this->table = $model;
        }
    
        foreach($cols as $e_key => $e_col){
            if(isset($e_col['db'])){
                if(count(explode('.', $e_col['db'])) > 1) $e_col_db_name = $e_col['db'];
                else $e_col_db_name = $this->table . '.' . $e_col['db'];
                array_push($this->cols_arr, $e_col_db_name);
                
                $e_cdn_arr = explode('.', $e_col_db_name);
                array_push($this->cols_raw_arr, '`' . $this->table_prefix.$e_cdn_arr[0] . '`.`' . $e_cdn_arr[1] . '`');
            }
            if(!isset($e_col['dt'])) $cols[$e_key]['dt'] = null; 
        }
        
        $this->cols     = $cols;
        //$this->cols_db_k   = array_combine(array_column($cols, 'db'), $cols);
        $this->cols_dt_k   = array_combine(array_column($cols, 'dt'), $cols);
        
        ksort($this->cols_dt_k);
        
        foreach($this->cols_dt_k as $e_key => $e_col){
            if(is_numeric($e_col['dt'])){
                if(isset($e_col['db'])) array_push($this->cols_exc_arr, $e_col['db']);
                array_push($this->cols_info, ['label'=>($e_col['label'] ?? ""), 'class'=>($e_col['class'] ?? "")]);
            }else unset($this->cols_dt_k[$e_key]);
        }
    }
    
    public function getInfo(){
        $ret = [
            'labels'    => [],
            'order'     => $this->order ?? [[0, 'asc']],
        ];
        foreach($this->cols_info as $key=>$val) array_push($ret['labels'], ['title'=>$val['label'], 'className'=>$val['class']]);
        
        return $ret;
    }
    
    public function getNormalData(){
        
        if(empty($this->normal_data)){
            $req = $this->request;
            $cdtk = $this->cols_dt_k;            
            
            if(isset($req['draw']) && isset($req['order']) && isset($req['start']) && isset($req['length'])){
                if($this->is_model){
                    if(empty($this->with_related_table)) $obj_model = ($this->model)::select($this->cols_arr);
                    else $obj_model = ($this->model)::with($this->with_related_table)->select($this->cols_arr);
                }else{
                    $obj_model = DB::table($this->table)->select($this->cols_arr);
                    if(!empty($this->join_query)) foreach($this->join_query as $e_jqry){
                        if($e_jqry[0] == "left") $obj_model = $obj_model->leftJoinSub($e_jqry[1], $e_jqry[2], $e_jqry[3]);
                    }
                }

                
                if(!empty($this->where_query)) foreach($this->where_query as $e_qry){
                    if($e_qry[0] == "and") $obj_model = $obj_model->where($e_qry[1]);
                    elseif($e_qry[0] == "or") $obj_model = $obj_model->orWhere($e_qry[1]);
                }
                
                $this->total_count = $obj_model->count();
                

                if(!empty($req['search']['value'])){
                    $col_search_str = "CONCAT(COALESCE(".implode($this->cols_raw_arr, ",''),' ',COALESCE(").",''))";
                    $obj_model = $obj_model->where(DB::raw($col_search_str), 'LIKE', '%'.$req['search']['value'].'%');
                    $this->filter_count = $obj_model->count();
                }else{
                    $this->filter_count = $this->total_count;
                }
                
                $obj_model = $obj_model->orderBy($cdtk[$req['order'][0]['column']]['db'], $req['order'][0]['dir']);
                
                if($req['length'] > -1) $obj_model = $obj_model->offset($req['start'])->limit($req['length']);
                //dd($obj_model->toSql());
                $this->normal_data = $obj_model->get();
            }else{
                $this->normal_data = false;
            }
        }
        
        return $this->normal_data;
    }
    
    public function getDtArr(){
        $ret_data = [];
        if(empty($this->dt_arr)){
            $req = $this->request;
            
            $m_data = $this->getNormalData();
            $e_cdtk = $this->cols_dt_k;
            
            if(!empty($m_data)){
                $n_data = $m_data->toArray();
                foreach($n_data as $e_key => $e_ndat){
                    foreach($e_cdtk as $ee_key => $ee_val){
                        if(is_numeric($ee_key)){
                            if(isset($ee_val['db'])) $the_val = $m_data[$e_key]->{Arr::last(explode('.', $ee_val['db']))};
                            if(isset($ee_val['formatter']) && is_callable($ee_val['formatter'])) $ret_data[$e_key][$ee_key] = $ee_val['formatter']($the_val, $m_data[$e_key]);
                            else $ret_data[$e_key][$ee_key] = $the_val;
                        }
                    }
                }
            }
            
            $this->dt_arr = [
                'draw' => $req['draw'] ?? 0,
                'recordsTotal' => $this->total_count,
                'recordsFiltered' => $this->filter_count,
                'data' => $ret_data,
            ];
        }
        
        return $this->dt_arr;
    }
    
    public function where(...$params){
        $ret_query = false;
        
        if(is_callable($params[0])){
            $ret_query = $params[0];
        }elseif(count($params) == 2){
            $ret_query = function($query) use($params){
                $query->where($params[0], $params[1]);
            };
        }elseif(count($params) == 3){
            $ret_query = function($query) use($params){
                $query->where($params[0], $params[1], $params[2]);
            };
        }
        
        if($ret_query !== false) array_push($this->where_query, ['and', $ret_query]);
        
        return $this;
    }
    
    public function orWhere(...$params){
        $ret_query = false;
        
        if(is_callable($params[0])){
            $ret_query = $params[0];
        }elseif(count($params) == 2){
            $ret_query = function($query) use($params){
                $query->where($params[0], $params[1]);
            };
        }elseif(count($params) == 3){
            $ret_query = function($query) use($params){
                $query->where($params[0], $params[1], $params[2]);
            };
        }
        
        if($ret_query !== false) array_push($this->where_query, ['or', $ret_query]);
        
        return $this;
    }
    
    public function leftJoin($table, ...$columns){
        if(count($columns) == 2){
            $table = explode(":", $table);
            
            $full_table_name = $this->table_prefix . $table[0];
            
            $db_table = DB::table($table[0]);
            
            if(!empty($table[1])){
                $cols = explode(",", $table[1]);
                foreach($cols as $key=>$e_col) $cols[$key] = $table[0] . '.' .trim($e_col);
                $db_table = $db_table->select($cols);
            }
            
            array_push($this->join_query, [
                'left', $db_table, $full_table_name, function($join) use($columns){
                    $join->on($columns[0], '=', $columns[1]);
                }
            ]);
        }else $is_model = true;
        
        $this->is_model = $is_model ?? false;
        
        return $this;
    }
    
    public function with($related_table){
        $this->with_related_table = $related_table;
        
        return $this;
    }
    
    public function order($dt, $sort){
        $this->order = [[$dt, $sort]];
        
        return $this;
    }
    
    public function sort($dt, $sort){
        return $this->order($dt, $sort);
    }
}

?>