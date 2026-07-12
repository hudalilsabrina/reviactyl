<?php

namespace App\Filament\Resources\Mounts\Pages;

use App\Filament\Resources\Mounts\MountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMounts extends ListRecords
{
    protected static string $resource = MountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
