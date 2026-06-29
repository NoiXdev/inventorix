<?php

namespace App\Filament\App\Resources\Assets\RelationManagers;

use App\Enums\AttachmentCategory;
use App\Models\Attachment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Anhänge';

    private const ACCEPTED = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'image/jpeg', 'image/png', 'image/webp', 'image/heic',
        'video/mp4', 'video/quicktime', 'video/webm',
    ];

    public static function getBadge($ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->attachments()->count();
    }

    public function form(Schema $schema): Schema
    {
        // Used by the Edit action: metadata only (no file replacement).
        return $schema->components([
            TextInput::make('title')->label('Titel'),
            Select::make('category')
                ->label('Kategorie')
                ->options(AttachmentCategory::class),
            Textarea::make('note')->label('Notiz'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('type')
                    ->label('Typ')
                    ->badge(),
                TextColumn::make('category')
                    ->label('Kategorie')
                    ->badge(),
                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable(),
                TextColumn::make('original_name')
                    ->label('Datei')
                    ->searchable(),
                TextColumn::make('size')
                    ->label('Größe')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1024 / 1024, 2).' MB'),
                TextColumn::make('uploadedBy.name')
                    ->label('Hochgeladen von'),
                TextColumn::make('created_at')
                    ->label('Datum')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->headerActions([
                Action::make('upload')
                    ->label('Hochladen')
                    ->schema([
                        FileUpload::make('files')
                            ->label('Dateien')
                            ->multiple()
                            ->directory('attachments')
                            ->acceptedFileTypes(self::ACCEPTED)
                            ->maxSize(50 * 1024) // KB => ~50 MB
                            ->storeFileNamesIn('file_names')
                            ->required(),
                        Select::make('category')
                            ->label('Kategorie')
                            ->options(AttachmentCategory::class),
                        TextInput::make('title')->label('Titel'),
                        Textarea::make('note')->label('Notiz'),
                    ])
                    ->action(fn (array $data) => $this->createFromUpload($data)),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (Attachment $record) => Storage::disk()->download($record->path, $record->original_name)),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createFromUpload(array $data): void
    {
        $paths = (array) ($data['files'] ?? []);
        $names = (array) ($data['file_names'] ?? []);
        $disk = Storage::disk();

        foreach ($paths as $path) {
            $mime = $disk->mimeType($path) ?: 'application/octet-stream';
            $originalName = $names[$path] ?? basename($path);

            $this->getOwnerRecord()->attachments()->create([
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => $mime,
                'size' => $disk->size($path),
                'type' => Attachment::detectType($mime),
                'category' => $data['category'] ?? null,
                'title' => filled($data['title'] ?? null) ? $data['title'] : $originalName,
                'note' => $data['note'] ?? null,
                'uploaded_by' => auth()->id(),
            ]);
        }
    }
}
