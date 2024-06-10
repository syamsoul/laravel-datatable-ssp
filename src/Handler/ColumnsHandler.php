<?php

namespace SoulDoit\DataTable\Handler;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
use SoulDoit\DataTable\Exceptions\MustHaveDbOrDbFake;
use SoulDoit\DataTable\Exceptions\RawExpressionMustHaveAliasName;

class ColumnsHandler
{
    private $db_fake_identifier = '||-----FAKE-----||';
    private array $dt_columns = [];
    private ?array $arranged_cols_details = null;

    public function __construct(
        private Handler $handler
    ) {}

    public function setColumns(array $columns): ColumnsHandler
    {
        $this->dt_columns = $columns;

        return $this;
    }

    public function getColumns(): array
    {
        return array_map(function($dt_col) {
            if (! is_array($dt_col)) return ['db' => $dt_col];

            if (!isset($dt_col['db']) && !isset($dt_col['db_fake'])) throw MustHaveDbOrDbFake::create();

            return $dt_col;
        }, ($this->handler->ssp->callColumns() ?? $this->dt_columns));
    }

    public function getArrangedColsDetails(bool $is_for_csv = false) : array
    {
        if (! $is_for_csv) {
            if ($this->arranged_cols_details !== null) return $this->arranged_cols_details;
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

    public function isSortable(array $dt_col): bool
    {
        if (!isset($dt_col['db']) && isset($dt_col['db_fake'])) return false;
        if (! ($dt_col['is_show'] ?? true)) return false;

        return isset($dt_col['sortable']) ? $dt_col['sortable'] : $this->handler->ssp->isSortingEnabled();
    }

    public function isDbFake($db_col): bool
    {
        return strpos($db_col, $this->db_fake_identifier) !== false;
    }

    public function getDtLabel(array $dt_col, string $db_col): string
    {
        return $dt_col['label'] ?? ucwords(str_replace("_", " ", Str::snake($db_col)));
    }

    private function getRawExpressionValue(Expression $raw_expression)
    {
        $is_laravel_version_ten = intval(app()->version()) >= 10;

        if ($is_laravel_version_ten) return $raw_expression->getValue(DB::connection()->getQueryGrammar());
        else return $raw_expression->getValue();
    }

    private function getDtColDbArray(string|Expression $db_col, bool $is_db_raw): array
    {
        $alias_separator = " as ";

        $db_col = $is_db_raw ? $this->getRawExpressionValue($db_col) : $db_col;

        $db_col_arr = explode($alias_separator, preg_replace("/$alias_separator/i", $alias_separator, $db_col));

        if (count($db_col_arr) <= 2) return $db_col_arr;

        $real_alias_expression = $db_col_arr[count($db_col_arr)-1];
        $real_alias_expression_length = strlen($alias_separator . $real_alias_expression);

        return [
            substr($db_col, 0, ($real_alias_expression_length * -1)),
            $real_alias_expression,
        ];
    }
}