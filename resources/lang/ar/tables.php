<?php

return [
    'columns' => [
        'log_name' => [
            'label' => 'النظام',
        ],
        'event' => [
            'label' => 'الحدث',
        ],
        'subject_type' => [
            'label' => 'النوع',
        ],
        'causer' => [
            'label' => 'المستخدم',
        ],
        'properties' => [
            'label' => 'الخصائص',
        ],
        'created_at' => [
            'label' => 'التاريخ',
        ],
    ],
    'filters' => [
        'created_at' => [
            'label'         => 'التاريخ',
            'created_from'  => 'من تاريخ ',
            'created_until' => 'إلى تاريخ ',
        ],
        'event' => [
            'label' => 'الحدث',
        ],
    ],
];
