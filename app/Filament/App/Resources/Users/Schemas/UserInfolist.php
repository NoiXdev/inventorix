<?php

namespace App\Filament\App\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('Id'),

                TextEntry::make('name')
                    ->label('Name'),

                TextEntry::make('firstname')
                    ->label('Firstname'),

                TextEntry::make('lastname')
                    ->label('Lastname'),

                TextEntry::make('email')
                    ->label('Email'),

                TextEntry::make('login_enabled')
                    ->label('Login Enabled'),

                TextEntry::make('created_at')
                    ->label('Created Date')
                    ->dateTime(),

                TextEntry::make('updated_at')
                    ->label('Last Modified Date')
                    ->dateTime(),

                TextEntry::make('entra_id')
                    ->label('Entra Id'),
            ]);
    }
}
