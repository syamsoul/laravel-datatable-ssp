<?php
namespace SoulDoit\DataTable;

use SoulDoit\DataTable\Traits\SSPFriends;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use SoulDoit\DataTable\Handler\Handler;
use SoulDoit\DataTable\Handler\FrontendHandler;
use SoulDoit\DataTable\Handler\ResponseHandler;

class SSP
{
    use SSPFriends;

    private array|null $allowed_items_per_page = null;
    private bool $is_search_enable = false;
    private bool $is_sort_enable = true;
    private bool $is_count_enable = true;
    private bool $is_allowed_export_all_items_in_csv = false;

    private ?Handler $handlerInstance = null;

    protected function columns(): ?array
    {
        return null;
    }

    protected function query(array $selected_columns): EloquentBuilder|QueryBuilder
    {
        return $this->handler()->query()->query($selected_columns);
    }

    protected function queryCustomFilter(EloquentBuilder|QueryBuilder $query): void
    {
        $this->handler()->query()->queryCustomFilter($query);
    }

    protected function queryCount(EloquentBuilder|QueryBuilder $query): int
    {
        return $this->handler()->query()->queryCount($query);
    }

    public function setColumns(array $columns): SSP
    {
        $this->handler()->columns()->setColumns($columns);

        return $this;
    }

    public function setQuery(callable|EloquentBuilder|QueryBuilder $query): SSP
    {
        $this->handler()->query()->setQuery($query);

        return $this;
    }

    public function setQueryCustomFilter(callable $query_custom_filter): SSP
    {
        $this->handler()->query()->setQueryCustomFilter($query_custom_filter);

        return $this;
    }

    public function setQueryCount(callable|int $query_count): SSP
    {
        $this->handler()->query()->setQueryCount($query_count);

        return $this;
    }

    public function enableSearch(bool $enable = true)
    {
        $this->is_search_enable = $enable;

        return $this;
    }

    public function disableSorting(bool $disable = true)
    {
        $this->is_sort_enable = !$disable;

        return $this;
    }

    public function disableCount(bool $disable = true)
    {
        $this->is_count_enable = !$disable;

        return $this;
    }

    public function allowExportAllItemsInCsv(bool $allow = true)
    {
        $this->is_allowed_export_all_items_in_csv = $allow;

        return $this;
    }

    public function setAllowedItemsPerPage(int|array $allowed_items_per_page)
    {
        $this->allowed_items_per_page = is_numeric($allowed_items_per_page) ? [$allowed_items_per_page] : (is_array($allowed_items_per_page) ? $allowed_items_per_page : null);

        return $this;
    }

    public function isSearchEnabled(): bool
    {
        return $this->is_search_enable;
    }

    public function isSortingEnabled(): bool
    {
        return $this->is_sort_enable;
    }

    public function isCountEnabled(): bool
    {
        return $this->is_count_enable;
    }

    public function isAllowedExportAllItemsInCsv(): bool
    {
        return $this->is_allowed_export_all_items_in_csv;
    }

    public function getAllowedItemsPerPage(): ?array
    {
        return $this->allowed_items_per_page;
    }

    public function getData(bool $is_for_csv = false): array
    {
        return $this->handler()->data()->getData($is_for_csv);
    }

    public function frontend(): FrontendHandler
    {
        return $this->handler()->frontend();
    }

    public function response(): ResponseHandler
    {
        return $this->handler()->response();
    }

    private function handler(): Handler
    {
        if ($this->handlerInstance === null) {
            $this->handlerInstance = new Handler($this);
        }

        return $this->handlerInstance;
    }
}
