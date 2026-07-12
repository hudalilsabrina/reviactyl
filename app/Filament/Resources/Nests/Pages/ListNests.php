<?php

namespace App\Filament\Resources\Nests\Pages;

use App\Filament\Resources\Nests\NestResource;
use App\Models\Nest;
use App\Services\Eggs\Sharing\EggImporterService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListNests extends ListRecords
{
    protected static string $resource = NestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('import')
                ->label(trans('admin/nests.actions.import'))
                ->color('gray')
                ->form([
                    FileUpload::make('file')
                        ->label(trans('admin/nests.import.file_label'))
                        ->acceptedFileTypes(['application/json'])
                        ->required()
                        ->storeFiles(true),
                    Select::make('nest_id')
                        ->label(trans('admin/nests.import.nest_label'))
                        ->options(Nest::all()->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data, $livewire) {
                    $tempFile = $data['file'];

                    if (is_array($tempFile)) {
                        $tempFile = reset($tempFile);
                    }

                    if (is_string($tempFile)) {
                        $possiblePaths = [
                            storage_path('app/livewire-tmp/'.$tempFile),
                            storage_path('app/private/'.$tempFile),
                            storage_path('app/'.$tempFile),
                        ];

                        $foundPath = null;
                        foreach ($possiblePaths as $path) {
                            if (file_exists($path)) {
                                $foundPath = $path;
                                break;
                            }
                        }

                        if (! $foundPath) {
                            Notification::make()
                                ->title(trans('admin/nests.import.file_not_found'))
                                ->body(trans('admin/nests.import.file_not_found_body'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $file = new UploadedFile(
                            $foundPath,
                            basename($foundPath),
                            'application/json',
                            null,
                            true
                        );
                    } elseif ($tempFile instanceof TemporaryUploadedFile) {
                        $realPath = $tempFile->getRealPath();
                        $file = new UploadedFile(
                            $realPath,
                            $tempFile->getClientOriginalName(),
                            $tempFile->getMimeType(),
                            null,
                            true
                        );
                    } else {
                        Notification::make()
                            ->title(trans('admin/nests.import.invalid_format'))
                            ->body(trans('admin/nests.import.invalid_format_body'))
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        app(EggImporterService::class)->handle($file, (int) $data['nest_id']);

                        Notification::make()
                            ->title(trans('admin/nests.import.success'))
                            ->success()
                            ->send();
                    } catch (\Exception $exception) {
                        Notification::make()
                            ->title(trans('admin/nests.import.failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
