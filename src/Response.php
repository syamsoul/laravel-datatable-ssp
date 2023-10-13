<?php

namespace SoulDoit\DataTable;

use SoulDoit\DataTable\SSP;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Response
{
    public function __construct(
        private SSP $ssp
    ) {}

    public function json(): JsonResponse
    {
        $frontend_framework = $this->ssp->getFrontendFramework();

        $data = $this->ssp->getData();

        if ($frontend_framework === 'datatablejs') {
            return response()->json($data);
        } else {
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }
    }

    public function csv(): StreamedResponse
    {
        $is_cache_lock_enable = config('sd-datatable-ssp.export_to_csv.is_cache_lock_enable', false);

        if ($is_cache_lock_enable) {
            $lock_name = 'export-csv-'.request()->route()->getName();

            if (config('sd-datatable-ssp.export_to_csv.is_cache_lock_based_on_auth')) {
                $current_user = auth()->user();
                if (!empty($current_user)) $lock_name .= '-'.$current_user->id;
            }
            
            $lock = Cache::lock($lock_name, 1800); // lock for 30 minutes

            $retry_count = 0;

            while (!$lock->get() && $retry_count < 5) {
                $retry_count++;
                usleep(1500000);
            }

            if ($retry_count == 5) abort(408, "Currently, there's another proccess is running. Please try again later.");
        }

        $headers = [
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename='.strtr(request()->route()->getName(), ".", "-") ."-".now()->format("YmdHis").'.csv',
            'Expires'             => '0',
            'Pragma'              => 'public'
        ];

        $query_data = $this->ssp->getData(true);

        //check if value in each columns is string
        foreach ($query_data as $row) {
            foreach ($row as $e_col) {
                if (!is_string($e_col) && !is_numeric($e_col) && $e_col !== null) {
                    if ($is_cache_lock_enable) $lock->release();
                    throw ValueInCsvColumnsMustBeString::create(json_encode($e_col));
                }
            }
        }

        $callback = function() use($query_data) {
            $file = fopen('php://output', 'w');
            foreach ($query_data as $row) fputcsv($file, $row);
            fclose($file);
        };

        if ($is_cache_lock_enable) $lock->release();

        return response()->stream($callback, 200, $headers);
    }
}