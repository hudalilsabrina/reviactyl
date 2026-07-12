<?php

namespace App\Filament\Resources\Nests\Pages;

use App\Filament\Resources\Nests\NestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNest extends EditRecord
{
    protected static string $resource = NestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
