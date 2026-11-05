<?php

namespace App\Filament\Resources\EmailTemplates\RelationManagers;

use App\Filament\Resources\EmailSends\Tables\EmailSendsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class SendsRelationManager extends RelationManager
{
    protected static string $relationship = 'sends';

    protected static ?string $title = 'Envíos de este mensaje';

    public function table(Table $table): Table
    {
        return EmailSendsTable::configure($table, showTemplate: false);
    }
}
