<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('username')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('name_first')
                    ->label(trans('strings.name'))
                    ->searchable(['name_first', 'name_last'])
                    ->formatStateUsing(fn ($record) => $record->name_first.' '.$record->name_last),
                IconColumn::make('root_admin')
                    ->boolean()
                    ->label(trans('strings.admin'))
                    ->sortable(),
                IconColumn::make('use_totp')
                    ->boolean()
                    ->label(trans('strings.2fa'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_seen')
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->last_seen?->diffForHumans())
                    ->placeholder(trans('generic.never')),
            ])
            ->filters([
                TernaryFilter::make('root_admin')
                    ->label(trans('admin/user.details.admin_status')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
