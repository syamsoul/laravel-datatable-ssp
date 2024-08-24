<?php

namespace SoulDoit\DataTable\Handler;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use SoulDoit\DataTable\Exceptions\DbFakeMustHaveFormatter;

class DataHandler
{
    public function __construct(
        private Handler $handler
    ) {}

    public function getData(bool $is_for_csv = false, ?callable $chunkCallback = null): array|null
    {
        $request = request();

        $frontend_framework = $this->handler->frontend()->getFramework();

        $arranged_cols_details = $this->handler->columns()->getArrangedColsDetails($is_for_csv);
        $dt_cols = $arranged_cols_details['dt_cols'];
        $db_cols = $arranged_cols_details['db_cols'];
        $db_cols_final = $arranged_cols_details['db_cols_final'];
        $db_cols_final_clean = $arranged_cols_details['db_cols_final_clean'];

        $query = $this->handler->ssp->callQuery($db_cols);

        $query_count = 0;
        $query_filtered_count = 0;

        if (! $is_for_csv) {
            if ($this->handler->ssp->isCountEnabled()) $query_count = $this->handler->ssp->callQueryCount($query);
        }

        $before_filtered_sql_query = $query->toSql();

        $this->handler->query()->querySearch($query);
        $this->handler->ssp->callQueryCustomFilter($query);

        $after_filtered_sql_query = $query->toSql();

        $has_filter_query = $before_filtered_sql_query !== $after_filtered_sql_query;

        if (! $is_for_csv) {
            if ($this->handler->ssp->isCountEnabled()) $query_filtered_count = $has_filter_query ? $this->handler->ssp->callQueryCount($query) : $query_count;
        }

        $this->handler->query()->queryOrder($query);
        $this->handler->query()->queryPagination($query, $is_for_csv);
        
        $processData = function ($query_data) use ($is_for_csv, $dt_cols, $db_cols_final, $db_cols_final_clean, $frontend_framework, $request, $query_count, $query_filtered_count) {
            if ($is_for_csv) {
                // prepend headers label for each column to the very first row
                array_unshift($query_data, collect($dt_cols)->filter(function ($dt_col) {
                    return $dt_col['is_show_in_csv'] ?? ($dt_col['is_show'] ?? true);
                })->map(function ($dt_col, $index) use ($db_cols_final_clean) {
                    $dt_col['label'] = $this->handler->columns()->getDtLabel($dt_col, $db_cols_final_clean[$index]);
                    return $dt_col;
                })->pluck('label')->toArray());
                
                return $query_data;
            }
    
            $ret = [];
    
            if ($frontend_framework == "datatablejs") {
    
                $pair_key_column_index = [];
                foreach ($dt_cols as $key => $dt_col) {
                    if (isset($dt_col['db'])) $pair_key_column_index[$db_cols_final[$key]] = $key;
                    else $pair_key_column_index[$dt_col['db_fake']] = $key;
                }
    
                $new_query_data = [];
                foreach ($query_data as $key => $e_tqdata) {
                    $e_new_cols_data = [];
                    foreach ($e_tqdata as $e_e_col_name => $e_e_col_value) {
                        $e_new_cols_data[$pair_key_column_index[$e_e_col_name]] = $e_e_col_value;
                    }
                    $new_query_data[$key] = $e_new_cols_data;
                }
    
                $ret['draw'] = $request->draw ?? 0;
                $ret['data'] = $new_query_data;
    
                if ($this->handler->ssp->isCountEnabled()) {
                    $ret['recordsTotal'] = $query_count;
                    $ret['recordsFiltered'] = $query_filtered_count;
                } else {
                    // TEMP: current workaround if count disabled
                    $paged_data_count = count($ret['data']);
                    $ret['recordsTotal'] = $ret['recordsFiltered'] = $paged_data_count < $request->length ? ($request->start + $paged_data_count) : pow(10, 9);
                }

            } else if (in_array($frontend_framework, ["vuetify", "others"])) {

                $ret = [];

                $pagination_data = $this->handler->query()->getPaginationData();

                if (!empty($pagination_data)) {
                    $current_page_item_count = count($query_data);
                    $current_item_position_start = $current_page_item_count == 0 ? 0 : ($pagination_data['offset'] + 1);
                    $current_item_position_end = $current_page_item_count == 0 ? 0 : ($current_item_position_start + $current_page_item_count) - 1;
    
                    $ret = array_merge($ret, [
                        'current_item_position_start' => $current_item_position_start,
                        'current_item_position_end' => $current_item_position_end,
                        'current_page_item_count' => $current_page_item_count,
                        'items_per_page' => $pagination_data['items_per_page'],
                    ]);
                }

                if ($this->handler->ssp->isCountEnabled()) {
                    $ret['total_item_count'] = $query_count;
                    $ret['total_filtered_item_count'] = $query_filtered_count;
                    if (!empty($pagination_data)) {
                        $ret['current_page'] = $pagination_data['current_page'];
                        $ret['total_pages'] = $pagination_data['items_per_page'] < 0 ? 1 : intval(ceil($ret['total_filtered_item_count'] / $pagination_data['items_per_page']));
                    }
                }

                $ret['items'] = $query_data;
            }

            return $ret;
        };

        if (! is_callable($chunkCallback)) {
            $query_data = $this->getFormattedData($query, $is_for_csv);
            return $processData($query_data);
        } else {
            $this->getFormattedData($query, $is_for_csv, function ($query_data) use ($processData, $chunkCallback) {
                $ret = $processData($query_data);
                $chunkCallback($ret);
            });
        }

        return null;
    }

    private function getFormattedData(EloquentBuilder|QueryBuilder $query, bool $is_for_csv = false, ?callable $chunkCallback = null): array|null
    {
        $arranged_cols_details = $this->handler->columns()->getArrangedColsDetails($is_for_csv);
        $dt_cols = $arranged_cols_details['dt_cols'];
        $db_cols = $arranged_cols_details['db_cols'];
        $db_cols_final = $arranged_cols_details['db_cols_final'];
        $db_cols_final_clean = $arranged_cols_details['db_cols_final_clean'];
        $formatter = $arranged_cols_details['formatter'];

        $processData = function ($query_data_eloq) use ($is_for_csv, $db_cols_final, $dt_cols, $db_cols_final_clean, $formatter) {
            $query_data = [];
            foreach ($query_data_eloq as $key => $e_tqde) {
                $query_data[$key] = [];
    
                foreach ($db_cols_final as $key_2 => $e_db_col) {
                    $dt_col = $dt_cols[$key_2];
    
                    $is_show = $dt_col['is_show'] ?? true;
    
                    if ($is_for_csv) {
                        if (! ($dt_col['is_show_in_csv'] ?? $is_show)) continue;
                    } else {
                        if (! $is_show) continue;
                    }
    
                    $e_db_col_clean = $db_cols_final_clean[$key_2];
    
                    if ($this->handler->columns()->isDbFake($e_db_col)) {
                        if (isset($formatter[$key_2])) $query_data[$key][$e_db_col_clean] = $formatter[$key_2]($e_tqde);
                        else throw DbFakeMustHaveFormatter::create($e_db_col_clean);
                    } else {
                        if (isset($formatter[$key_2])) {
                            if (is_callable($formatter[$key_2])) $query_data[$key][$e_db_col_clean] = $formatter[$key_2]($e_tqde->{$e_db_col_clean}, $e_tqde);
                            else if (is_string($formatter[$key_2])) $query_data[$key][$e_db_col_clean] = strtr($formatter[$key_2], ["{value}"=>$e_tqde->{$e_db_col_clean}]);
                        } else {
                            $query_data[$key][$e_db_col_clean] = $e_tqde->{$e_db_col_clean};
                        }
                    }
    
                    if ($is_for_csv) {
                        $value = $query_data[$key][$e_db_col_clean];
                        
                        if (is_array($value)) {
                            $query_data[$key][$e_db_col_clean] = json_encode($value);
                        } else if (is_bool($value)) {
                            $query_data[$key][$e_db_col_clean] = $value ? 'true' : 'false';
                        } else if (is_string($value)) {
                            $query_data[$key][$e_db_col_clean] = strip_tags($value);
                        }
                    }
                }
            }
    
            return $query_data;
        };

        if (! is_callable($chunkCallback)) {
            $query_data_eloq = $query->get();
            return $processData($query_data_eloq);
        } else {
            $query->chunk(20000, function ($query_data_eloq) use ($processData, $chunkCallback) {
                $query_data = $processData($query_data_eloq);
                $chunkCallback($query_data);
            });
        }
        
        return null;
    }
}