<?php

namespace App\Filament\Resources\UserManagement\Pages;

use App\Filament\Resources\UserManagement\RelationManagers\UserNotesRelationManager;
use App\Filament\Resources\UserManagement\UserManagementResource;
use App\Models\PropertyListing;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewUserManagement extends ViewRecord
{
    protected static string $resource = UserManagementResource::class;

    public function getRelationManagers(): array
    {
        return [
            UserNotesRelationManager::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deactivate_listings')
                ->label('Desactivar anuncios')
                ->icon('phosphor-eye-slash-duotone')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Desactivar todos los anuncios')
                ->modalDescription(fn () => "¿Confirma que desea desactivar los {$this->record->propertyListings()->where('is_active', true)->count()} anuncios activos de este usuario? Los anuncios dejarán de ser visibles al público.")
                ->modalSubmitActionLabel('Sí, desactivar todos')
                ->visible(fn () => $this->record->propertyListings()->where('is_active', true)->exists())
                ->action(function (): void {
                    $count = $this->record->propertyListings()->where('is_active', true)->count();
                    $this->record->propertyListings()->update(['is_active' => false]);

                    Notification::make()
                        ->title("{$count} anuncio(s) desactivados correctamente")
                        ->success()
                        ->send();
                }),

            Action::make('reactivate_listings')
                ->label('Reactivar anuncios')
                ->icon('phosphor-eye-duotone')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Reactivar todos los anuncios')
                ->modalDescription(fn () => "¿Confirma que desea reactivar los {$this->record->propertyListings()->where('is_active', false)->count()} anuncios inactivos de este usuario? Los anuncios volverán a ser visibles al público.")
                ->modalSubmitActionLabel('Sí, reactivar todos')
                ->visible(fn () => $this->record->propertyListings()->where('is_active', false)->exists())
                ->action(function (): void {
                    $count = $this->record->propertyListings()->where('is_active', false)->count();
                    $this->record->propertyListings()->update(['is_active' => true]);

                    Notification::make()
                        ->title("{$count} anuncio(s) reactivados correctamente")
                        ->success()
                        ->send();
                }),

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
