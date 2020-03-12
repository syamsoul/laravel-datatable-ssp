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
    
    private $query_object;
    private $query_from_what='model';
    private $table;
    private $table_prefix;
    private $request;
    private $cols_info=[];
    private $cols;
    //private $cols_db_k;
    private $cols_dt_k;
    private $cols_arr=[];
    private $cols_raw_arr=[];
    private $cols_filter_raw_arr=[];
    private $cols_exc_arr=[];
    private $normal_data;
    private $dt_arr;
    private $total_count;
    private $filter_count;
    private $where_query=[];
    private $join_query=[];
    private $custom_query=[];
    private $with_related_table;
    private $group_by;
	private $dbOrderBy=[];
    private $order;
    private $theSearchKeywordFormatter;
    private $variables=[];
    private $variableInitiator;
    
    function __construct($model, $cols)
    {
        $this->table_prefix = DB::getTablePrefix() ?? "";
                
        $this->request  = request()->all();
        
        if(is_string($model)){
            if(class_exists($model)){
                $this->query_object = $model;
                $this->table = (new $model())->getTable();
            }else{
                $this->table = $model;
                $this->query_from_what = 'table_name';
            }
        }else{
            if(is_a($model, 'Illuminate\Database\Query\Builder') || is_a($model, 'Illuminate\Database\Eloquent\Builder')){
                $this->query_object = $model;
                $this->table = 'temp_main_primary_table';
                $this->query_from_what = 'sub_query';
            }else{
                $this->error('Failed to initiate.');
            }
        }

        foreach($cols as $e_key => $e_col){
            if(isset($e_col['db'])){
                $e_searchable = (isset($e_col['searchable']) && is_bool($e_col['searchable'])) ? $e_col['searchable'] : true;
                
                if(is_a($e_col['db'], get_class(DB::raw('')))){
                    $e_col_db_arr = explode(" AS ", $e_col['db']->getValue());
                    
                    $column_alias_name = trim(sd_get_array_last($e_col_db_arr));
                    unset($e_col_db_arr[count($e_col_db_arr)-1]);
                    
                    array_push($this->cols_arr, $e_col['db']);
                    array_push($this->cols_raw_arr, trim(implode(" AS ", $e_col_db_arr)));
                    if($e_searchable) array_push($this->cols_filter_raw_arr, trim(implode(" AS ", $e_col_db_arr)));
                    
                    $cols[$e_key]['db'] = strtr($column_alias_name, ['`'=>'']);
                }else{
                    $e_col_arr = explode('.', $e_col['db']);
                    if(count($e_col_arr) > 1) {
                        $e_col_db_name = $e_col['db'];
                        if($e_col_arr[0] != $this->table) $e_col_db_name .= " AS ".$e_col_arr[0].".".$e_col_arr[1];
                    }
                    else $e_col_db_name = $this->table . '.' . $e_col['db'];
					
					$cols[$e_key]['db'] =  sd_get_array_last(explode(" AS ", $e_col_db_name));
            
                    if(!in_array($e_col_db_name, $this->cols_arr)) array_push($this->cols_arr, $e_col_db_name);
                    
                    $e_cdn_arr = explode('.', $cols[$e_key]['db']);
                    array_push($this->cols_raw_arr, '`' . $this->table_prefix.$e_cdn_arr[0] . '`.`' . $e_cdn_arr[1] . '`');
                    if($e_searchable) array_push($this->cols_filter_raw_arr, '`' . $this->table_prefix.$e_cdn_arr[0] . '`.`' . $e_cdn_arr[1] . '`');
                }
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
                array_push($this->cols_info, ['label'=>($e_col['label'] ?? ""), 'class'=>($e_col['class'] ?? ""), 'sortable'=>($e_col['sortable'] ?? true)]);
            }else unset($this->cols_dt_k[$e_key]);
        }
    }
    
    
    public function getInfo()
    {
        $ret = [
            'labels'    => [],
            'order'     => $this->order ?? [[0, 'asc']],
        ];
        foreach($this->cols_info as $key=>$val) array_push($ret['labels'], ['title'=>$val['label'], 'className'=>$val['class'], 'sortable'=>$val['sortable']]);
        
        return $ret;
    }
    
    
    public function getNormalData()
    {
        
        if(empty($this->normal_data)){
            $req = $this->request;
            $cdtk = $this->cols_dt_k;            
            
            if(isset($req['draw']) && isset($req['order']) && isset($req['start']) && isset($req['length'])){
                
                $extra_cols = [];
                if(!empty($req['search']['value'])){
                    $replaced_variables = [];
                    foreach($this->variables as $variable) $replaced_variables["@$variable"] = "@$variable"."2";
                    
                    $replaced_variables_fixed = [
                        //'COALESCE(`gn_users`.`sd_counter_value`,\'\')' => '@sd_counter_value',
                    ];
                    
                    $col_search_str = strtr("CONCAT(COALESCE(". strtr(implode(",''),' ',COALESCE(", $this->cols_filter_raw_arr), $replaced_variables) .",'')) AS `filter_col`", $replaced_variables_fixed);
                    
                    array_push($extra_cols, DB::raw($col_search_str));
                }
                
                $the_cols = array_merge($this->cols_arr, $extra_cols);
                if($this->query_from_what == 'model'){
                    if(empty($this->with_related_table)) $obj_model = ($this->query_object)::select($the_cols);
                    else $obj_model = ($this->query_object)::with($this->with_related_table)->select($the_cols);
                }elseif($this->query_from_what == 'table_name'){
                    $obj_model = DB::table($this->table)->select($the_cols);
                    if(!empty($this->join_query)) foreach($this->join_query as $e_jqry){
                        if($e_jqry[0] == "left") $obj_model = $obj_model->leftJoinSub($e_jqry[1], $e_jqry[2], $e_jqry[3]);
                    }
                }elseif($this->query_from_what == 'sub_query'){
                    $obj_model = DB::query()->select($the_cols)->fromSub($this->query_object, $this->table_prefix . $this->table);
                }else{
                    $this->error('Failed to query.');
                }
                
                if(!empty($this->where_query)) foreach($this->where_query as $e_qry){
                    if($e_qry[0] == "and") $obj_model = $obj_model->where($e_qry[1]);
                    elseif($e_qry[0] == "or") $obj_model = $obj_model->orWhere($e_qry[1]);
                }

                if(!empty($this->group_by)) $obj_model = $obj_model->groupBy($this->group_by);
                
                foreach($this->custom_query as $each_query) $each_query($obj_model);
				foreach($this->dbOrderBy as $e_dbob) $obj_model = $obj_model->orderBy($e_dbob[0], $e_dbob[1]);

                if($this->query_from_what == 'table_name'){
                    $obj_model = DB::query()->fromSub($obj_model, $this->table_prefix . $this->table);
                }

                $this->total_count = DB::select("SELECT count(*) AS `c` FROM (".$obj_model->toSql().") AS `temp_count_table`", $obj_model->getBindings())[0]->c;

                if(!empty($req['search']['value'])){
                    if(is_callable($this->variableInitiator)) ($this->variableInitiator)();
                    
                    $query_search_value = '%'.$req['search']['value'].'%';
                    if($this->query_from_what == 'table_name') $obj_model = $obj_model->where('filter_col', 'LIKE', $query_search_value);
                    else $obj_model = $obj_model->having('filter_col', 'LIKE', $query_search_value);
                
                    $sql_str = "SELECT count(*) AS `c` FROM (".$obj_model->toSql().") AS `temp_count_table`";
                    $sql_bindings_params = array_merge($obj_model->getBindings(), [$query_search_value]);
                    //dd(\Str::replaceArray('?', $sql_bindings_params, $sql_str));
                    $this->filter_count = DB::select($sql_str, $sql_bindings_params)[0]->c;
                    
                }else{
                    $this->filter_count = $this->total_count;
                }
                
                $clean_col_name = $this->getColumnNameWithoutOriTable($cdtk[$req['order'][0]['column']]['db']);
                
                $obj_model = $obj_model->orderBy(DB::raw('`' . $clean_col_name . '`'), $req['order'][0]['dir']);
                
                if($req['length'] > -1) $obj_model = $obj_model->offset($req['start'])->limit($req['length']);
                //dd($obj_model->toSql());
                
                DB::disconnect(config('database.default'));
                DB::reconnect(config('database.default'));
                
                if(is_callable($this->variableInitiator)) ($this->variableInitiator)();
                
                $this->normal_data = $obj_model->get();
            }else{
                $this->normal_data = false;
            }
        }
        
        return $this->normal_data;
    }
    
    
    public function getDtArr()
    {
        $ret_data = [];
        if(empty($this->dt_arr)){
            $req = $this->request;
            
            $m_data = $this->getNormalData();
            $e_cdtk = $this->cols_dt_k;

            if(!empty($m_data)){
                $n_data = $m_data->toArray();
                foreach($n_data as $e_key => $e_ndat){
                    $theVals    = [];
                    $theDatas   = [];
                    foreach($this->cols as $ee_key => $ee_val){
                        if(isset($ee_val['db'])){
                            $ee_val_db_arr = explode('.', $ee_val['db']);
                            $ee_val_db_name = ($ee_val_db_arr[0] != $this->table) ? $ee_val['db'] : sd_get_array_last($ee_val_db_arr);
                            $the_val = $m_data[$e_key]->{$ee_val_db_name};
                        }else{
                            $the_val = null;
                        }
                        
                        if(!empty($req['search']['value'])){
                            $search_val = $req['search']['value'];
                            
                            if(is_callable($this->theSearchKeywordFormatter)){
                                $formatted = sd_str_replace_nth($search_val, function($found){
                                    return ($this->theSearchKeywordFormatter)($found);
                                }, $the_val);
                                if(is_string($the_val) || is_numeric($the_val)) $the_val = $formatted[0];
                                //$formatted[1]; count of keywords found
                            }
                        }
                        
                        if(isset($ee_val['db'])) $theDatas[$ee_val_db_name] = $the_val;
                        
                        if(isset($ee_val['dt']) && is_numeric($ee_val['dt'])){
                            $theVals[$ee_val['dt']] = $the_val;
                            
                            if(isset($ee_val['formatter']) && is_callable($ee_val['formatter'])) $ret_data[$e_key][$ee_val['dt']] = $ee_val['formatter'];
                            else $ret_data[$e_key][$ee_val['dt']] = $the_val;
                        }
                    }
                   
                    foreach($ret_data[$e_key] as $key=>$e_rdek){
                        if(is_callable($e_rdek) && ($e_rdek instanceof \Closure) && !is_string($e_rdek) && !is_array($e_rdek)) $ret_data[$e_key][$key] = $e_rdek($theVals[$key], $m_data[$e_key], $theDatas);
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
    
    
    public function where(...$params)
    {
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
    
    
    public function orWhere(...$params)
    {
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
    
    
    public function leftJoin($table, ...$columns)
    {
        if(isset($columns[0]) && is_callable($columns[0])){
            $extend_query = $columns[0];
            unset($columns[0]); 
            $columns=array_values($columns);
        }
        
        if(count($columns) == 2){
            $table = explode(":", $table);
            
            $table_name = $table[0];
            $table_name_arr = explode(" AS ", $table_name);
            
            if(count($table_name_arr) > 1){
                $table_name = trim($table_name_arr[0]);
                $table_alias_name = trim($table_name_arr[1]);
                
                $full_table_alias_name = (config('sd-datatable-ssp.leftjoin.alias_has_prefix') ? $this->table_prefix : '') . $table_alias_name;          
            }
            $full_table_name = (config('sd-datatable-ssp.leftjoin.alias_has_prefix') ? $this->table_prefix : '') . $table_name;
            
            
            $db_table = DB::table($table_name);
            
            if(!empty($table[1])){
                $cols = explode(",", $table[1]);
                foreach($cols as $key=>$e_col){
                    $e_col = trim($e_col);
                    if(count(explode(".", $e_col)) > 1) $cols[$key] = $e_col;
                    else $cols[$key] = $table_name . '.' . $e_col;
                }
                $db_table = $db_table->select($cols);
            }
            
            if(isset($extend_query) && is_callable($extend_query)){
                $extend_query($db_table);
            }
            
            array_push($this->join_query, [
                'left', $db_table, ($full_table_alias_name ?? $full_table_name), function($join) use($columns){
                    $join->on($columns[0], '=', $columns[1]);
                }
            ]);
        }
        
        return $this;
    }
    
    
    public function customQuery($custom_query)
    {
        
        if(is_callable($custom_query)) array_push($this->custom_query, $custom_query);
        
        return $this;
    }
    
    
    public function with($related_table)
    {
        $this->with_related_table = $related_table;
        
        return $this;
    }
    
    
    public function groupBy($group_by)
    {
        $this->group_by = $group_by;
        
        return $this;
    }
	
    
	public function dbOrderBy($column, $sort)
    {
		array_push($this->dbOrderBy, [$column, $sort]);
		
		return $this;
	}
    
    
    public function order($dt, $sort)
    {
        $this->order = [[$dt, $sort]];
        
        return $this;
    }
    
    
    public function sort($dt, $sort)
    {
        return $this->order($dt, $sort);
    }
    
    
    public function searchKeywordFormatter($formatter)
    {
        if(is_callable($formatter)){
            $this->theSearchKeywordFormatter = $formatter;
        }
        
        return $this;
    }
    
    
    public function initVariable($keyValueArr)
    {
        
        if(is_array($keyValueArr)){
            $this->variables = array_keys($keyValueArr);
            $this->variableInitiator = function() use($keyValueArr){
                foreach($keyValueArr as $key=>$value) $keyValueArr[$key."2"] = $value;
                $this->setDBVariable($keyValueArr);
            };
        }
        
        return $this;
    }
    
    
    private function setDBVariable($keyValueArr)
    {
        if(is_array($keyValueArr)){
            try {
                DB::transaction(function() use($keyValueArr){
                    foreach($keyValueArr as $key=>$value) DB::unprepared(DB::raw("SET @$key:=$value"));
                });
            
                DB::commit();
            } catch (\Exception $e){ 
                DB::rollback();
                dd($e->getMessage());
            }
        }
    }
    
    
    private function getColumnNameWithoutOriTable($column_name)
    {
        $cn_arr = explode('.', $column_name);
        
        if(count($cn_arr) > 1){
            if($cn_arr[0] === $this->table) return $cn_arr[1];
        }
        
        return $column_name;
    }
    
    
    private function error($error_text)
    {
        dd("syamsoul/laravel-datatable-ssp: $error_text");
    }
}

?>
