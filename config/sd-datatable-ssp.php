<?php
return [
    'frontend_framework' => 'others', // NOTE: available options = datatablejs, vuetify, others
    'export_to_csv' => [
        'is_cache_lock_enable' => false,
        'is_cache_lock_based_on_auth' => true,
    ],
    'default_modifier_timezone' => 'UTC', // NOTE: used in DateTimeModifier
];