<?php

return [
    'resources' => [
        'label'                  => 'سجل الحدث',
        'plural_label'           => 'سجلات الأحداث',
        'navigation_group'       => 'النظام',
        'navigation_icon'        => 'heroicon-o-presentation-chart-line',
        'navigation_sort'        => null,
        'navigation_count_badge' => false,
        'resource'               => \Rmsramos\Activitylog\Resources\ActivitylogResource::class,
    ],
    'datetime_format' => 'd M Y - h:i A',
];
