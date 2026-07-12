<?php

namespace App\Filament\Resources\Mounts\Pages;

use App\Filament\Resources\Mounts\MountResource;
use App\Models\Mount;
use App\Services\Activity\ActivityLogService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMount extends EditRecord
{
    protected static string $resource = MountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(function (Mount $record) {
                    app(ActivityLogService::class)
                        ->subject($record)
                        ->event('mount:delete')
                        ->log();
                }),
        ];
    }

    protected function afterSave(): void
    {
        app(ActivityLogService::class)
            ->subject($this->record)
            ->event('mount:update')
            ->log();
    }
}
