<?php

namespace App\Filament\Resources\Api\Pages;

use App\Filament\Resources\Api\ApiKeyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApiKeys extends ListRecords
{
    protected static string $resource = ApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(trans('admin/api.create-btn')),
        ];
    }
}
