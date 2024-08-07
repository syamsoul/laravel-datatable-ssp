<?php
return [
    'frontend_framework' => 'others', // NOTE: available options = datatablejs, vuetify, others
    'export_to_csv' => [
        'filename_prefix' => env('DATATABLE_SSP_CSV_FILENAME_PREFIX', ''),
        'is_cache_lock_enable' => false,
        'is_cache_lock_based_on_auth' => true,
        'timeout' => 600,
    ],
    'default_modifier_timezone' => 'UTC', // NOTE: used in DateTimeModifier
];