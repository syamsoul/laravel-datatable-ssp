<?php

namespace SoulDoit\DataTable\Handler;

use SoulDoit\DataTable\Exceptions\InvalidDbName;

class FrontendHandler
{
    private ?string $framework = null;
    private ?string $response_data_url = null;
    private ?int $initial_items_per_page = null;
    private array $initial_sorting = [];
    private bool $is_fetch_on_init = true;

    public function __construct(
        private Handler $handler
    ) {}

    public function setFramework(string $framework): FrontendHandler
    {
        $this->framework = $framework;

        return $this;
    }

    public function setResponseDataRouteName(string $response_data_route_name): FrontendHandler
    {
        $this->response_data_url = route($response_data_route_name);

        return $this;
    }

    public function setResponseDataUrl(string $response_data_url): FrontendHandler
    {
        $this->response_data_url = $response_data_url;

        return $this;
    }

    public function setInitialItemsPerPage(int $initial_items_per_page): FrontendHandler
    {
        $this->initial_items_per_page = $initial_items_per_page;

        return $this;
    }

    public function setInitialSorting(string $db, bool $is_sort_desc = false): FrontendHandler
    {
        $framework = $this->getFramework();

        $arranged_cols_details = $this->handler->columns()->getArrangedColsDetails();
        $db_cols_final = $arranged_cols_details['db_cols_final'];

        $col_index = array_flip($db_cols_final)[$db] ?? null;

        if ($col_index === null) throw InvalidDbName::create($db);

        if ($framework == "datatablejs") {

            $this->initial_sorting = [
                [$col_index, ($is_sort_desc ? 'desc' : 'asc')]
            ];

        } else {

            $this->initial_sorting = [
                'by' => $db,
                'desc' => $is_sort_desc,
            ];

        }

        return $this;
    }

    public function disableFetchOnInit(bool $disable = true): FrontendHandler
    {
        $this->is_fetch_on_init = !$disable;

        return $this;
    }

    public function getFramework(): string
    {
        return $this->framework ?? config('sd-datatable-ssp.frontend_framework', 'others');
    }

    public function getResponseDataUrl(): string
    {
        return $this->response_data_url;
    }

    public function getInitialItemsPerPage(): int
    {
        return $this->initial_items_per_page ?? ($this->handler->ssp->getAllowedItemsPerPage()[0] ?? 10);
    }

    public function getInitialSorting(): array
    {
        return $this->initial_sorting ?? [];
    }

    public function isFetchOnInit(): bool
    {
        return $this->is_fetch_on_init;
    }

    public function getSettings(bool $is_return_json_string = false): array|string
    {
        $framework = $this->getFramework();

        $data = [];

        if ($framework === 'datatablejs') {
            $data = [
                'processing' => true,
                'serverSide' => true,
                'ajax' => $this->getResponseDataUrl(),
                'columns' => $this->getColumns(),
                'lengthMenu' => [
                    $this->handler->ssp->getAllowedItemsPerPage(),
                    array_map(fn ($v) => ($v == -1 ? 'All' : $v), $this->handler->ssp->getAllowedItemsPerPage()),
                ],
                'pageLength' => $this->getInitialItemsPerPage(),
                'searching' => $this->handler->ssp->isSearchEnabled(),
                'pagingType' => $this->handler->ssp->isCountEnabled() ? 'simple_numbers' : 'simple',
                'info' => $this->handler->ssp->isCountEnabled(),
                'order' => $this->getInitialSorting(),
            ];
        } else {
            $initial_sorting = $this->getInitialSorting();

            $data = [
                'columns' => $this->getColumns(),
                'allowedItemsPerPage' => $this->handler->ssp->getAllowedItemsPerPage(),
                'is_ssp_mode' => true,
                'url' => $this->getResponseDataUrl(),
                'is_search_enable' => $this->handler->ssp->isSearchEnabled(),
                'is_count_enable' => $this->handler->ssp->isCountEnabled(),
                'is_fetch_on_init' => $this->isFetchOnInit(),
                'defaultItemsPerPage' => $this->getInitialItemsPerPage(),
            ];

            if (isset($initial_sorting['by']) && isset($initial_sorting['desc'])) {
                $data['defaultSortBy'] = $initial_sorting['by'];
                $data['defaultSortDesc'] = $initial_sorting['desc'];
            }
        }

        if ($is_return_json_string) return json_encode($data);

        return $data;
    }

    public function getColumns(): array
    {
        $framework = $this->getFramework();

        $arranged_cols_details = $this->handler->columns()->getArrangedColsDetails();

        $dt_cols = $arranged_cols_details['dt_cols'];
        $db_cols_final_clean = $arranged_cols_details['db_cols_final_clean'];

        $frontend_dt_cols = [];

        foreach ($dt_cols as $index => $dt_col) {
            if (! ($dt_col['is_show'] ?? true)) continue;
            
            $db_col = $db_cols_final_clean[$index];
            $dt_label = $this->handler->columns()->getDtLabel($dt_col, $db_col);
            $sortable = $this->handler->columns()->isSortable($dt_col);

            if ($framework == "datatablejs") {

                $e_fe_dt_col = ['title' => $dt_label];

                if (isset($dt_col['class'])) {
                    if (is_array($dt_col['class'])) $e_fe_dt_col['className'] = implode(" ", $dt_col['class']);
                    else if (is_string($dt_col['class'])) $e_fe_dt_col['className'] = $dt_col['class'];
                }

                $e_fe_dt_col['orderable'] = $sortable;

                array_push($frontend_dt_cols, $e_fe_dt_col);

            } else {

                if ($framework == "vuetify") {
                    array_push($frontend_dt_cols, [
                        'text' => $dt_label,
                        'value' => $db_col,
                    ]);
                } else if ($framework == "others") {
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
}