<?php
namespace SoulDoit\DataTable;

use DB;
use App;

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
    private $request;
    private $cols_info=[];
    private $cols;
    //private $cols_db_k;
    private $cols_dt_k;
    private $cols_arr=[];
    private $cols_exc_arr=[];
    private $normal_data;
    private $dt_arr;
    private $col_search;
    private $total_count;
    private $filter_count;
    private $order;
    
    function __construct($model, $cols){
        $this->model    = $model;
        $this->request  = request()->all();
    
        foreach($cols as $e_key => $e_col){
            if(isset($e_col['db'])) array_push($this->cols_arr, $e_col['db']);
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
        
        $this->col_search = "CONCAT(`".implode($this->cols_exc_arr, "`,' ',`")."`)";// AS `sd_dt_search_col`";
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
                $obj_model = ($this->model)::select($this->cols_arr);
                $this->total_count = $obj_model->count();
                

                if(!empty($req['search']['value'])){
                    $obj_model = $obj_model->where(DB::raw($this->col_search), 'LIKE', '%'.$req['search']['value'].'%');
                    $this->filter_count = $obj_model->count();
                }else{
                    $this->filter_count = $this->total_count;
                }
                
                $obj_model = $obj_model->orderBy($cdtk[$req['order'][0]['column']]['db'], $req['order'][0]['dir']);
                
                $obj_model = $obj_model->offset($req['start'])->limit($req['length']);
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
                            if(isset($ee_val['db'])) $the_val = $e_ndat[$ee_val['db']];
                            if(isset($ee_val['formatter']) && is_callable($ee_val['formatter'])) $ret_data[$e_key][$ee_key] = $ee_val['formatter'](['value'=>$the_val, 'data'=>$e_ndat, 'model'=>$m_data[$e_key]]);
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
    
    public function order($dt, $sort){
        $this->order = [[$dt, $sort]];
        
        return $this;
    }
    
    public function sort($dt, $sort){
        return $this->order($dt, $sort);
    }
}

?>