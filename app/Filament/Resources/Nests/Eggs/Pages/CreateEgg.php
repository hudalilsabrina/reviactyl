<?php

namespace App\Filament\Resources\Nests\Eggs\Pages;

use App\Filament\Resources\Nests\EggResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEgg extends CreateRecord
{
    protected static string $resource = EggResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set nest_id from query parameter if provided
        $nestId = request()->query('nest_id');
        if ($nestId) {
            $data['nest_id'] = $nestId;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return EggResource::getUrl('edit', ['record' => $this->record]);
    }
}
