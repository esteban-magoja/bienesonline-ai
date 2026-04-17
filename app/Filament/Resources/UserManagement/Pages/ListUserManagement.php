<?php

namespace App\Filament\Resources\UserManagement\Pages;

use App\Filament\Resources\UserManagement\UserManagementResource;
use Filament\Resources\Pages\ListRecords;

class ListUserManagement extends ListRecords
{
    protected static string $resource = UserManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
