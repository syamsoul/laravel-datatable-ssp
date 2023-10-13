<?php
namespace SoulDoit\DataTable;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
use SoulDoit\DataTable\Exceptions\MustHaveDbOrDbFake;
use SoulDoit\DataTable\Exceptions\DbFakeMustHaveFormatter;
use SoulDoit\DataTable\Exceptions\RawExpressionMustHaveAliasName;
use SoulDoit\DataTable\Exceptions\ValueInCsvColumnsMustBeString;
use SoulDoit\DataTable\Exceptions\InvalidDbName;
use SoulDoit\DataTable\Query;
use SoulDoit\DataTable\Response;
use ReflectionMethod;

class SSP
{
    use Query;

    private $dt_columns;
    private $arranged_cols_details;
    private $db_fake_identifier = '||-----FAKE-----||';
    
    private $frontend_framework = null;

    protected function columns(): array
    {
        return $this->dt_columns;
    }

    public function setColumns(array $columns)
    {
        $this->dt_columns = $columns;

        return $this;
    }

    private function getColumns(): array
    {
        return array_map(function($dt_col) {
            if (! is_array($dt_col)) return ['db' => $dt_col];

            if (!isset($dt_col['db']) && !isset($dt_col['db_fake'])) throw MustHaveDbOrDbFake::create();

            return $dt_col;
        }, $this->columns());
    }

    public function getFrontEndColumns(): array
    {
        $frontend_framework = $this->getFrontendFramework();

        $arranged_cols_details = $this->getArrangedColsDetails();

        $dt_cols = $arranged_cols_details['dt_cols'];
        $db_cols_final_clean = $arranged_cols_details['db_cols_final_clean'];

        $frontend_dt_cols = [];

        foreach ($dt_cols as $index => $dt_col) {
            if (! ($dt_col['is_show'] ?? true)) continue;
            
            $db_col = $db_cols_final_clean[$index];
            $dt_label = $this->getDtLabel($dt_col, $db_col);
            $sortable = $this->isSortable($dt_col);

            if ($frontend_framework == "datatablejs") {

                $e_fe_dt_col = ['title' => $dt_label];

                if (isset($dt_col['class'])) {
                    if (is_array($dt_col['class'])) $e_fe_dt_col['className'] = implode(" ", $dt_col['class']);
                    else if (is_string($dt_col['class'])) $e_fe_dt_col['className'] = $dt_col['class'];
                }

                $e_fe_dt_col['orderable'] = $sortable;

                array_push($frontend_dt_cols, $e_fe_dt_col);

            } else {

                if ($frontend_framework == "vuetify") {
                    array_push($frontend_dt_cols, [
                        'text' => $dt_label,
                        'value' => $db_col,
                    ]);
                } else if ($frontend_framework == "others") {
                    array_push($frontend_dt_cols, [
                        'label' => $dt_label,
                        'db' => $db_col,
                        'class' => $dt_col['class'] ?? [],
                        'sortable' => $sortable,
                    ]);
                }

            }
        }

        return $frontend_dt_cols;
    }

    public function getFrontEndInitialSorting(string $db, bool $is_sort_desc = false): array
    {
        $frontend_framework = $this->getFrontendFramework();

        $arranged_cols_details = $this->getArrangedColsDetails();
        $db_cols_final = $arranged_cols_details['db_cols_final'];

        $col_index = array_flip($db_cols_final)[$db] ?? null;

        if ($col_index === null) throw InvalidDbName::create($db);

        if ($frontend_framework == "datatablejs") {

            return [
                [$col_index, ($is_sort_desc ? 'desc' : 'asc')]
            ];

        } else {

            return [
                'by' => $db,
                'desc' => $is_sort_desc,
            ];

        }
    }

    public function getData(bool $is_for_csv = false): array
    {
        $request = request();

        $frontend_framework = $this->getFrontendFramework();

        $arranged_cols_details = $this->getArrangedColsDetails($is_for_csv);
        $dt_cols = $arranged_cols_details['dt_cols'];
        $db_cols = $arranged_cols_details['db_cols'];
        $db_cols_final = $arranged_cols_details['db_cols_final'];
        $db_cols_final_clean = $arranged_cols_details['db_cols_final_clean'];

        $query = $this->query($db_cols);

        if (! $is_for_csv) {
            if ($this->is_count_enable) $query_count = $this->queryCount($query);
        }

        $before_filtered_sql_query = $query->toSql();

        $this->querySearch($query);
        $this->queryCustomFilter($query);

        $after_filtered_sql_query = $query->toSql();

        $has_filter_query = $before_filtered_sql_query !== $after_filtered_sql_query;

        if (! $is_for_csv) {
            if ($this->is_count_enable) $query_filtered_count = $has_filter_query ? $this->queryCount($query) : $query_count;
        }

        $this->queryOrder($query);
        $this->queryPagination($query, $is_for_csv);

        $query_data = $this->getFormattedData($query, $is_for_csv);

        if ($is_for_csv) {
            // prepend headers label for each column to the very first row
            array_unshift($query_data, collect($dt_cols)->filter(function ($dt_col) {
                return $dt_col['is_show_in_csv'] ?? ($dt_col['is_show'] ?? true);
            })->map(function ($dt_col, $index) use ($db_cols_final_clean) {
                $dt_col['label'] = $this->getDtLabel($dt_col, $db_cols_final_clean[$index]);
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

            if ($this->is_count_enable) {
                $ret['recordsTotal'] = $query_count;
                $ret['recordsFiltered'] = $query_filtered_count;
            }

        } else if (in_array($frontend_framework, ["vuetify", "others"])) {

            $ret = [];

            $pagination_data = $this->getPaginationData();

            if (!empty($pagination_data)) {
                $current_page_item_count = count($query_data);
                $current_item_position_start = $current_page_item_count == 0 ? 0 : ($pagination_data['offset'] + 1);
                $current_item_position_end = $current_page_item_count == 0 ? 0 : ($current_item_position_start + $current_page_item_count) - 1;

                $ret = array_merge($ret, [
                    'current_item_position_start' => $current_item_position_start,
                    'current_item_position_end' => $current_item_position_end,
                    'current_page_item_count' => $current_page_item_count,
                ]);
            }

            if ($this->is_count_enable) {
                $ret['total_item_count'] = $query_count;
                $ret['total_filtered_item_count'] = $query_filtered_count;
            }

            $ret['items'] = $query_data;
        }

        return $ret;
    }

    public function response(): Response
    {
        return new Response($this);
    }

    private function getArrangedColsDetails(bool $is_for_csv = false) : array
    {
        if (! $is_for_csv) {
            if ($this->arranged_cols_details != null) return $this->arranged_cols_details;
        }

        $dt_cols = $this->getColumns();

        $db_cols = $db_cols_initial = $db_cols_mid = $db_cols_final = $db_cols_final_clean = $formatter = [];

        foreach ($dt_cols as $key => $dt_col) {
            if (isset($dt_col['db'])) {
                $db_cols[$key] = $dt_col['db'];

                $is_db_raw = ($dt_col['db'] instanceof Expression);

                $dt_col_db_arr = $this->getDtColDbArray($dt_col['db'], $is_db_raw);

                if (count($dt_col_db_arr) == 2) {
                    $db_cols_initial[$key] = $is_db_raw ? DB::raw($dt_col_db_arr[0]) : $dt_col_db_arr[0];
                    $db_cols_mid[$key] = $is_db_raw ? str_replace("`", "", $dt_col_db_arr[1]) : $dt_col_db_arr[1];
                    $db_cols_final[$key] = $is_db_raw ? str_replace("`", "", $dt_col_db_arr[1]) : $dt_col_db_arr[1];
                } else {
                    if ($is_db_raw) throw RawExpressionMustHaveAliasName::create($this->getRawExpressionValue($dt_col['db']));

                    $db_cols_initial[$key] = $dt_col['db'];
                    $db_cols_mid[$key] = $dt_col['db'];

                    $dt_col_db_arr = explode(".", $dt_col['db']);

                    if (count($dt_col_db_arr) == 2) $db_cols_final[$key] = $dt_col_db_arr[1];
                    else $db_cols_final[$key] = $dt_col['db'];
                }

                $db_cols_final_clean[$key] = $db_cols_final[$key];
            } else if (isset($dt_col['db_fake'])) {
                $db_cols_initial[$key] = $dt_col['db_fake'] . $this->db_fake_identifier;
                $db_cols_mid[$key] = $dt_col['db_fake'] . $this->db_fake_identifier;
                $db_cols_final[$key] = $dt_col['db_fake'] . $this->db_fake_identifier;
                $db_cols_final_clean[$key] = $dt_col['db_fake'];
            }

            if (isset($dt_col['formatter'])) $formatter[$key] = $dt_col['formatter'];

            if ($is_for_csv) {
                if (isset($dt_col['formatter_csv'])) $formatter[$key] = $dt_col['formatter_csv'];
            }
        }

        $arranged_cols_details = [
            'dt_cols' => $dt_cols,
            'db_cols' => $db_cols,
            'db_cols_initial' => $db_cols_initial,
            'db_cols_mid' => $db_cols_mid,
            'db_cols_final' => $db_cols_final,
            'db_cols_final_clean' => $db_cols_final_clean,
            'formatter' => $formatter,
        ];

        if (! $is_for_csv) $this->arranged_cols_details = $arranged_cols_details;

        return $arranged_cols_details;
    }

    private function getFormattedData(EloquentBuilder|QueryBuilder $query, bool $is_for_csv = false) : array
    {
        $query_data_eloq = $query->get();

        $arranged_cols_details = $this->getArrangedColsDetails($is_for_csv);
        $dt_cols = $arranged_cols_details['dt_cols'];
        $db_cols = $arranged_cols_details['db_cols'];
        $db_cols_final = $arranged_cols_details['db_cols_final'];
        $db_cols_final_clean = $arranged_cols_details['db_cols_final_clean'];
        $formatter = $arranged_cols_details['formatter'];

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

                if ($this->isDbFake($e_db_col)) {
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
    }

    public function setFrontendFramework(string $frontend_framework)
    {
        $this->frontend_framework = $frontend_framework;

        return $this;
    }

    public function getFrontendFramework()
    {
        return $this->frontend_framework ?? config('sd-datatable-ssp.frontend_framework', 'others');
    }

    private function getRawExpressionValue(Expression $raw_expression)
    {
        $is_laravel_version_ten = intval(app()->version()) >= 10;

        if ($is_laravel_version_ten) return $raw_expression->getValue(DB::connection()->getQueryGrammar());
        else return $raw_expression->getValue();
    }

    private function getDtColDbArray(string|Expression $db_col, bool $is_db_raw): array
    {
        $db_col = $is_db_raw ? $this->getRawExpressionValue($db_col) : $db_col;

        return explode(" as ", preg_replace("/ as /i", " as ", $db_col));
    }

    private function isSortable(array $dt_col): bool
    {
        if (!isset($dt_col['db']) && isset($dt_col['db_fake'])) return false;
        if (! ($dt_col['is_show'] ?? true)) return false;

        return isset($dt_col['sortable']) ? $dt_col['sortable'] : $this->is_sort_enable;
    }

    private function isDbFake($db_col): bool
    {
        return strpos($db_col, $this->db_fake_identifier) !== false;
    }

    private function getDtLabel(array $dt_col, string $db_col): string
    {
        return $dt_col['label'] ?? ucwords(str_replace("_", " ", Str::snake($db_col)));
    }
}
