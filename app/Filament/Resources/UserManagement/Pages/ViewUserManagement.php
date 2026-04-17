<?php

namespace App\Filament\Resources\UserManagement\Pages;

use App\Filament\Resources\UserManagement\UserManagementResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewUserManagement extends ViewRecord
{
    protected static string $resource = UserManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit_user')
                ->label('Editar en Usuarios')
                ->icon('phosphor-pencil-duotone')
                ->url(fn () => route('filament.admin.resources.users.edit', $this->record))
                ->color('primary'),
            Action::make('impersonate')
                ->label('Impersonar usuario')
                ->icon('phosphor-user-switch-duotone')
                ->url(fn () => route('impersonate', $this->record))
                ->visible(fn () => auth()->user()->id !== $this->record->id)
                ->color('warning'),
        ];
    }
}
