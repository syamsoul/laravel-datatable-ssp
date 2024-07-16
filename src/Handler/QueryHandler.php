<?php
namespace SoulDoit\DataTable\Handler;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Validator;
use SoulDoit\DataTable\Exceptions\MustHaveQuery;

class QueryHandler
{
    private $dt_query = null;
    private $query_count = null;
    private $query_custom_filter;
    private $pagination_data;

    public function __construct(
        private Handler $handler
    ) {}

    public function query(array $selected_columns): EloquentBuilder|QueryBuilder
    {
        if ($this->dt_query === null) throw MustHaveQuery::create();

        return is_callable($this->dt_query) ? ($this->dt_query)($selected_columns) : $this->dt_query;
    }

    public function queryCount(EloquentBuilder|QueryBuilder $query): int
    {
        if ($this->query_count === null) {
            if ($query instanceof EloquentBuilder) {
                if (!empty($query->getQuery()->groups)) return $query->getQuery()->getCountForPagination();
            }

            return $query->count();
        }

        return is_callable($this->query_count) ? ($this->query_count)($query) : $this->query_count;
    }

    public function queryCustomFilter(EloquentBuilder|QueryBuilder $query): void
    {
        if (is_callable($this->query_custom_filter)) ($this->query_custom_filter)($query);
    }

    public function setQuery(callable|EloquentBuilder|QueryBuilder $query): QueryHandler
    {
        $this->dt_query = $query;

        return $this;
    }

    public function setQueryCount(callable|int $query_count): QueryHandler
    {
        $this->query_count = $query_count;

        return $this;
    }

    public function setQueryCustomFilter(callable $query_custom_filter): QueryHandler
    {
        $this->query_custom_filter = $query_custom_filter;

        return $this;
    }

    public function queryOrder(EloquentBuilder|QueryBuilder $query): void
    {
        $request = request();

        $frontend_framework = $this->handler->frontend()->getFramework();

        $arranged_cols_details = $this->handler->columns()->getArrangedColsDetails();
        $dt_cols = $arranged_cols_details['dt_cols'];
        $db_cols_mid = $arranged_cols_details['db_cols_mid'];
        $db_cols_final = $arranged_cols_details['db_cols_final'];
        $db_cols_final_clean = $arranged_cols_details['db_cols_final_clean'];
        
        $sortable_cols = [];

        foreach ($dt_cols as $index => $dt_col) {
            if ($this->handler->columns()->isSortable($dt_col)) $sortable_cols[$index] = $db_cols_final_clean[$index];
        }

        if ($frontend_framework == "datatablejs") {
            $this->validateRequest([
                'order' => ['filled', 'array'],
                'order.*.column' => ['required', 'in:' . implode(",", array_keys($sortable_cols))],
                'order.*.dir' => ['required', 'in:asc,desc'],
            ], [
                'order.*.column.in' => 'Order column is invalid. Allowed Order column: ' . implode(",", $sortable_cols),
                'order.*.dir.in' => 'Order dir must be either asc or desc',
            ]);

            if ($request->filled('order')) {
                $query->orderBy($db_cols_mid[$request->order[0]["column"]], $request->order[0]['dir']);
            }

        } else if (in_array($frontend_framework, ["vuetify", "others"])) {

            if ($request->filled('sortBy') && $request->filled('sortDesc')) {
                $this->validateRequest([
                    'sortBy' => ['in:' . implode(",", $sortable_cols)],
                    'sortDesc' => ['in:1,0,true,false'],
                ], [
                    'sortBy.in' => 'Selected sortBy is invalid. Allowed sortBy: ' . implode(",", $sortable_cols),
                    'sortDesc.in' => 'sortDesc must be either 1,0,true or false',
                ]);

                $sortDesc = $request->sortDesc;

                if (is_string($request->sortDesc) || is_numeric($request->sortDesc)) {
                    $sortDesc = filter_var($request->sortDesc, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                }

                $col_index = array_flip($db_cols_final)[$request->sortBy];

                $query->orderBy($db_cols_mid[$col_index], ($sortDesc ? 'desc' : 'asc'));
            }

        }
    }

    public function queryPagination(EloquentBuilder|QueryBuilder $query, bool $is_for_csv = false): void
    {
        $request = request();

        $pagination_data = $this->getPaginationData($is_for_csv);

        if (isset($pagination_data['items_per_page']) && isset($pagination_data['offset'])) {
            if ($pagination_data['items_per_page'] != "-1") $query->limit($pagination_data['items_per_page'])->offset($pagination_data['offset']);
        }
    }

    public function getPaginationData(bool $is_for_csv = false)
    {
        if ($this->pagination_data !== null) return $this->pagination_data;

        $request = request();

        $ret = [];

        $frontend_framework = $this->handler->frontend()->getFramework();

        if ($frontend_framework == "datatablejs") {

            $firstRequestName = 'start';
            $secondRequestName = 'length';

            if ($request->filled('length') && $request->filled('start')) {
                $ret['items_per_page'] = $request->length;
                $ret['offset'] = $request->start;
            }

        } else if (in_array($frontend_framework, ["vuetify", "others"])) {

            $firstRequestName = 'page';
            $secondRequestName = 'itemsPerPage';

            if ($request->filled('itemsPerPage') && $request->filled('page')) {
                $ret['items_per_page'] = $request->itemsPerPage;
                $ret['offset'] = ($request->page - 1) * $request->itemsPerPage;
            }

        }

        $validation_rules = [
            $firstRequestName => ['required_with:'.$secondRequestName],
            $secondRequestName => ['required_with:'.$firstRequestName],
        ];

        $validation_error_messages = [];

        $allowed_items_per_page = $this->handler->ssp->getAllowedItemsPerPage();

        if (!empty($allowed_items_per_page)) {
            if (is_array($allowed_items_per_page)) {

                $allowed_items_per_page = array_map(function($v){ return intval($v); }, $allowed_items_per_page);

                if ($is_for_csv) {
                    if ($this->handler->ssp->isAllowedExportAllItemsInCsv()) $allowed_items_per_page = array_merge($allowed_items_per_page, [-1]);
                }

                $allowed_items_per_page = array_unique($allowed_items_per_page);

                array_push($validation_rules[$secondRequestName], 'in:' . implode(',', $allowed_items_per_page));
                $validation_error_messages["$secondRequestName.in"] = "The selected $secondRequestName is invalid. Available options: " . implode(',', $allowed_items_per_page);

                if (! in_array(-1, $allowed_items_per_page)) {
                    array_push($validation_rules[$firstRequestName], 'required');
                    array_push($validation_rules[$secondRequestName], 'required');
                } else {
                    array_push($validation_rules[$firstRequestName], 'filled');
                    array_push($validation_rules[$secondRequestName], 'filled');
                }

            }
        }

        $this->validateRequest($validation_rules, $validation_error_messages);

        $this->pagination_data = $ret;

        return $ret;
    }

    public function querySearch(EloquentBuilder|QueryBuilder $query): void
    {
        $search_value = $this->getSearchValue();

        if (!empty($search_value)) {
            $arranged_cols_details = $this->handler->columns()->getArrangedColsDetails();
            $dt_cols = $arranged_cols_details['dt_cols'];
            $db_cols_initial = $arranged_cols_details['db_cols_initial'];

            $query->where(function ($the_query) use ($dt_cols, $db_cols_initial, $search_value) {
                $count = 0;
                foreach ($db_cols_initial as $index => $e_col) {
                    if (! ($dt_cols[$index]['searchable'] ?? true)) continue;
                    if ($this->handler->columns()->isDbFake($e_col)) continue;

                    if ($count == 0) $the_query->where($e_col, 'LIKE', "%".$search_value."%");
                    else $the_query->orWhere($e_col, 'LIKE', "%".$search_value."%");

                    $count++;
                }
            });
        }
    }

    private function getSearchValue(): string
    {
        if (! $this->handler->ssp->isSearchEnabled()) return '';

        $request = request();
        $frontend_framework = $this->handler->frontend()->getFramework();

        $search_value = '';

        if ($frontend_framework == "datatablejs") {

            if ($request->filled('search')) {
                $search_value = $request->search['value'] ?? '';
            }

        } else if (in_array($frontend_framework, ["vuetify", "others"])) {

            if ($request->filled('search')) {
                $search_value = $request->search;
            }

        }

        return $search_value;
    }

    private function validateRequest(array $rules, array $error_messages = [])
    {
        $request = request();

        $validator = Validator::make($request->all(), $rules, $error_messages);

        $validator->validate();
    }
}
