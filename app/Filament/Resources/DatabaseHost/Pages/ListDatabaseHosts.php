<?php

namespace App\Filament\Resources\DatabaseHost\Pages;

use App\Filament\Resources\DatabaseHost\DatabaseHostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDatabaseHosts extends ListRecords
{
    protected static string $resource = DatabaseHostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
