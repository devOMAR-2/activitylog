<?php

namespace Rmsramos\Activitylog\Resources\ActivitylogResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Rmsramos\Activitylog\Resources\ActivitylogResource;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

class ListActivitylog extends ListRecords
{
    protected static string $resource = ActivitylogResource::class;

    protected function paginateTableQuery(Builder $query): Paginator
    {
        return $query->simplePaginate(($this->getTableRecordsPerPage() === 'all') ? $query->count() : $this->getTableRecordsPerPage());
    }
}
