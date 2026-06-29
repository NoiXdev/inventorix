# Asset Attachments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let users upload documents and photos/videos to assets, then list, preview, download, and delete them from the Filament asset edit page.

**Architecture:** A polymorphic, UUID-keyed `attachments` table owns file metadata; an `Attachment` model + `HasAttachments` trait expose a `morphMany` relation on `Asset`. A Filament `AttachmentsRelationManager` ("Anhänge") provides a multi-file upload action, a list with download/edit/delete, and a count badge. An `AttachmentObserver` deletes the underlying file on row deletion and writes activity-log entries against the parent asset so they appear in the existing History tab.

**Tech Stack:** Laravel, FilamentPHP v4, PHPUnit (class-based tests extending `Tests\TestCase`), Spatie Activitylog, UUID primary keys (`HasUuids`). Local dev runs through **ddev**.

## Global Constraints

- All PHP/artisan/composer/node commands run inside ddev — e.g. `ddev php artisan ...`, `ddev exec ...`. (CLAUDE.md)
- Primary keys are UUIDs. Migrations must use `uuid('id')->primary()`, `uuidMorphs()`, and `foreignUuid()` — never `id()`, `morphs()`, or `foreignId()`. (UUID migrations gotcha)
- Files resolve against the **default** filesystem disk (`FILESYSTEM_DISK`). Do **not** store a per-row disk and do **not** hardcode a disk name.
- User-facing labels are German (match existing enum/label style, e.g. `AssetState::getLabel()`).
- Tests are PHPUnit classes extending `Tests\TestCase` with `use RefreshDatabase;` and `Storage::fake()` — not Pest functions.
- Run a single test class with `ddev php artisan test --filter=ClassName`.
- Commit after each task's tests pass.

---

### Task 1: Data layer — enum, migration, `Attachment` model, factory

**Files:**
- Create: `app/Enums/AttachmentCategory.php`
- Create: `database/migrations/2026_06_29_000001_create_attachments_table.php`
- Create: `app/Models/Attachment.php`
- Create: `database/factories/AttachmentFactory.php`
- Test: `tests/Feature/Attachments/AttachmentModelTest.php`

**Interfaces:**
- Produces:
  - `AttachmentCategory` (string-backed enum, implements `HasLabel`) with cases `RECHNUNG='rechnung'`, `FOTO='foto'`, `VIDEO='video'`, `DOKUMENT='dokument'`, `SONSTIGES='sonstiges'`.
  - `Attachment` model with columns `attachable_type`, `attachable_id`, `path`, `original_name`, `mime_type`, `size` (int), `type` (string), `category` (`AttachmentCategory` cast, nullable), `title` (nullable), `note` (nullable), `uploaded_by` (nullable uuid).
  - `Attachment::detectType(string $mimeType): string` returning `'image'`, `'video'`, or `'document'`.
  - Relations `attachable(): MorphTo`, `uploadedBy(): BelongsTo`.
  - `AttachmentFactory`.

- [ ] **Step 1: Write the enum**

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AttachmentCategory: string implements HasLabel
{
    case RECHNUNG = 'rechnung';
    case FOTO = 'foto';
    case VIDEO = 'video';
    case DOKUMENT = 'dokument';
    case SONSTIGES = 'sonstiges';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::RECHNUNG => 'Rechnung',
            self::FOTO => 'Foto',
            self::VIDEO => 'Video',
            self::DOKUMENT => 'Dokument',
            self::SONSTIGES => 'Sonstiges',
        };
    }
}
```

- [ ] **Step 2: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // uuidMorphs (not morphs) because attachable models use UUID keys.
            $table->uuidMorphs('attachable');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('type'); // document | image | video
            $table->string('category')->nullable();
            $table->string('title')->nullable();
            $table->text('note')->nullable();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
```

- [ ] **Step 3: Write the `Attachment` model**

```php
<?php

namespace App\Models;

use App\Enums\AttachmentCategory;
use App\Observers\AttachmentObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['path', 'original_name', 'mime_type', 'size', 'type', 'category', 'title', 'note', 'uploaded_by'])]
#[ObservedBy(AttachmentObserver::class)]
class Attachment extends Model
{
    use HasFactory, HasUuids;

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public static function detectType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            default => 'document',
        };
    }

    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    protected function casts(): array
    {
        return [
            'category' => AttachmentCategory::class,
            'size' => 'integer',
        ];
    }
}
```

> Note: `#[ObservedBy(AttachmentObserver::class)]` references the observer built in Task 3. The class won't exist yet — create an empty placeholder now so the model loads, OR add the attribute in Task 3. To keep Task 1 self-contained and runnable, **omit the `#[ObservedBy]` attribute and the `use App\Observers\AttachmentObserver;` import in this task**, and add them in Task 3.

So for Task 1, write the model **without** the observer attribute/import (everything else identical).

- [ ] **Step 4: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Enums\AttachmentCategory;
use App\Models\Asset;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Attachment> */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        $original = fake()->word().'.pdf';

        return [
            'attachable_type' => Asset::class,
            'attachable_id' => Asset::factory(),
            'path' => 'attachments/'.fake()->uuid().'.pdf',
            'original_name' => $original,
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1000, 5_000_000),
            'type' => 'document',
            'category' => AttachmentCategory::DOKUMENT,
            'title' => $original,
            'note' => null,
            'uploaded_by' => null,
        ];
    }
}
```

- [ ] **Step 5: Write the failing test**

```php
<?php

namespace Tests\Feature\Attachments;

use App\Enums\AttachmentCategory;
use App\Models\Asset;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttachmentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_detect_type_maps_mime_to_kind(): void
    {
        $this->assertSame('image', Attachment::detectType('image/png'));
        $this->assertSame('video', Attachment::detectType('video/mp4'));
        $this->assertSame('document', Attachment::detectType('application/pdf'));
        $this->assertSame('document', Attachment::detectType('text/plain'));
    }

    public function test_it_persists_and_casts_category(): void
    {
        $asset = Asset::factory()->create();

        $attachment = Attachment::factory()->create([
            'attachable_type' => Asset::class,
            'attachable_id' => $asset->getKey(),
            'category' => AttachmentCategory::RECHNUNG,
        ]);

        $attachment->refresh();

        $this->assertInstanceOf(AttachmentCategory::class, $attachment->category);
        $this->assertSame(AttachmentCategory::RECHNUNG, $attachment->category);
        $this->assertTrue($attachment->attachable->is($asset));
        $this->assertSame('Rechnung', AttachmentCategory::RECHNUNG->getLabel());
    }
}
```

- [ ] **Step 6: Run the test to verify it fails**

Run: `ddev php artisan test --filter=AttachmentModelTest`
Expected: FAIL (table/model/enum not present until migrations run) — confirm it fails before all pieces exist, then passes once Steps 1–4 are saved and migrations run.

- [ ] **Step 7: Run migrations and re-run the test**

Run: `ddev php artisan migrate && ddev php artisan test --filter=AttachmentModelTest`
Expected: PASS (both tests green)

- [ ] **Step 8: Commit**

```bash
git add app/Enums/AttachmentCategory.php database/migrations/2026_06_29_000001_create_attachments_table.php app/Models/Attachment.php database/factories/AttachmentFactory.php tests/Feature/Attachments/AttachmentModelTest.php
git commit -m "feat(attachments): add attachments table, model, enum and factory"
```

---

### Task 2: `HasAttachments` trait on `Asset`

**Files:**
- Create: `app/Models/Concerns/HasAttachments.php`
- Modify: `app/Models/Asset.php` (add `use HasAttachments;` to the trait list and the import)
- Test: `tests/Feature/Attachments/AssetAttachmentsRelationTest.php`

**Interfaces:**
- Consumes: `Attachment` model (Task 1).
- Produces: `HasAttachments` trait with `attachments(): MorphMany` returning `morphMany(Attachment::class, 'attachable')->latest()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Attachments;

use App\Models\Asset;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetAttachmentsRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_has_many_attachments_newest_first(): void
    {
        $asset = Asset::factory()->create();

        $older = Attachment::factory()->create([
            'attachable_type' => Asset::class,
            'attachable_id' => $asset->getKey(),
            'created_at' => now()->subDay(),
        ]);
        $newer = Attachment::factory()->create([
            'attachable_type' => Asset::class,
            'attachable_id' => $asset->getKey(),
            'created_at' => now(),
        ]);

        $ids = $asset->attachments()->pluck('id')->all();

        $this->assertCount(2, $ids);
        $this->assertSame($newer->getKey(), $ids[0]);
        $this->assertSame($older->getKey(), $ids[1]);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `ddev php artisan test --filter=AssetAttachmentsRelationTest`
Expected: FAIL with "Call to undefined method ...::attachments()"

- [ ] **Step 3: Write the trait**

```php
<?php

namespace App\Models\Concerns;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }
}
```

- [ ] **Step 4: Apply the trait to `Asset`**

In `app/Models/Asset.php`, add the import near the other `use App\...` imports:

```php
use App\Models\Concerns\HasAttachments;
```

And add `HasAttachments` to the existing `use` trait line inside the class:

```php
use HasAttachments, HasFactory, HasTags, HasUuids, LogsActivity;
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `ddev php artisan test --filter=AssetAttachmentsRelationTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Models/Concerns/HasAttachments.php app/Models/Asset.php tests/Feature/Attachments/AssetAttachmentsRelationTest.php
git commit -m "feat(attachments): add HasAttachments trait and wire to Asset"
```

---

### Task 3: `AttachmentObserver` — file cleanup + activity log

**Files:**
- Create: `app/Observers/AttachmentObserver.php`
- Modify: `app/Models/Attachment.php` (add `#[ObservedBy(AttachmentObserver::class)]` attribute and its import — deferred from Task 1)
- Test: `tests/Feature/Attachments/AttachmentObserverTest.php`

**Interfaces:**
- Consumes: `Attachment` model (Task 1).
- Produces: `AttachmentObserver` with `created(Attachment $a)`, `deleted(Attachment $a)`. On `deleted`, deletes `$a->path` from the default disk (`Storage::disk()->delete(...)`, guarded by `exists`). Both log an activity entry (`useLogName('asset')`, `performedOn($a->attachable)`, events `attachment_added` / `attachment_removed`, properties include `original_name`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Attachments;

use App\Models\Asset;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_attachment_removes_file_and_logs_activity(): void
    {
        Storage::fake();

        $asset = Asset::factory()->create();
        $path = UploadedFile::fake()->create('doc.pdf', 10)->store('attachments');

        Storage::disk()->assertExists($path);

        $attachment = Attachment::factory()->create([
            'attachable_type' => Asset::class,
            'attachable_id' => $asset->getKey(),
            'path' => $path,
            'original_name' => 'doc.pdf',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'asset',
            'event' => 'attachment_added',
            'subject_id' => $asset->getKey(),
        ]);

        $attachment->delete();

        Storage::disk()->assertMissing($path);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'asset',
            'event' => 'attachment_removed',
            'subject_id' => $asset->getKey(),
        ]);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `ddev php artisan test --filter=AttachmentObserverTest`
Expected: FAIL (file still exists / no activity row, observer not registered)

- [ ] **Step 3: Write the observer**

```php
<?php

namespace App\Observers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class AttachmentObserver
{
    public function created(Attachment $attachment): void
    {
        $this->log($attachment, 'attachment_added');
    }

    public function deleted(Attachment $attachment): void
    {
        if ($attachment->path && Storage::disk()->exists($attachment->path)) {
            Storage::disk()->delete($attachment->path);
        }

        $this->log($attachment, 'attachment_removed');
    }

    private function log(Attachment $attachment, string $event): void
    {
        $subject = $attachment->attachable;

        if ($subject === null) {
            return;
        }

        activity('asset')
            ->performedOn($subject)
            ->causedBy(auth()->user())
            ->withProperties([
                'original_name' => $attachment->original_name,
                'title' => $attachment->title,
            ])
            ->log($event);
    }
}
```

- [ ] **Step 4: Register the observer on the model**

In `app/Models/Attachment.php`, add the import:

```php
use App\Observers\AttachmentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
```

And add the attribute directly above the existing `#[Fillable(...)]` attribute:

```php
#[ObservedBy(AttachmentObserver::class)]
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `ddev php artisan test --filter=AttachmentObserverTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Observers/AttachmentObserver.php app/Models/Attachment.php tests/Feature/Attachments/AttachmentObserverTest.php
git commit -m "feat(attachments): observer for file cleanup and activity log"
```

---

### Task 4: `AttachmentsRelationManager` — upload, list, download, edit, delete

**Files:**
- Create: `app/Filament/App/Resources/Assets/RelationManagers/AttachmentsRelationManager.php`
- Modify: `app/Filament/App/Resources/Assets/AssetResource.php` (register relation manager + import)
- Test: `tests/Feature/Attachments/AttachmentsRelationManagerTest.php`

**Interfaces:**
- Consumes: `Attachment` model + `attachments` relation (Tasks 1–2), `AttachmentCategory` enum (Task 1), observer (Task 3).
- Produces: a Filament relation manager (`$relationship = 'attachments'`) with a multi-file upload header action that creates one `Attachment` per uploaded file, populating `original_name`, `mime_type`, `size`, `type`, `uploaded_by`, defaulting `title` to `original_name`; a table with type/category badge, title, size, uploaded-by, date; row Download / Edit / Delete actions; a tab count badge.

- [ ] **Step 1: Write the relation manager**

```php
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
```

- [ ] **Step 2: Register the relation manager on `AssetResource`**

In `app/Filament/App/Resources/Assets/AssetResource.php`, add the import alongside the existing relation-manager imports:

```php
use App\Filament\App\Resources\Assets\RelationManagers\AttachmentsRelationManager;
```

And add it to `getRelations()`:

```php
public static function getRelations(): array
{
    return [
        IncidentsRelationManager::class,
        AttachmentsRelationManager::class,
        HistoryRelationManager::class,
    ];
}
```

- [ ] **Step 3: Write the failing test**

```php
<?php

namespace Tests\Feature\Attachments;

use App\Enums\AttachmentCategory;
use App\Filament\App\Resources\Assets\RelationManagers\AttachmentsRelationManager;
use App\Filament\App\Resources\Assets\Pages\EditAsset;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AttachmentsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_action_creates_attachments_with_metadata(): void
    {
        Storage::fake();
        $user = User::factory()->create();
        $this->actingAs($user);

        $asset = Asset::factory()->create();

        Livewire::test(AttachmentsRelationManager::class, [
            'ownerRecord' => $asset,
            'pageClass' => EditAsset::class,
        ])
            ->callAction('upload', data: [
                'files' => [UploadedFile::fake()->image('photo.jpg')],
                'category' => AttachmentCategory::FOTO->value,
                'note' => 'Frontansicht',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(1, $asset->attachments()->count());

        $attachment = $asset->attachments()->first();
        $this->assertSame('image', $attachment->type);
        $this->assertSame(AttachmentCategory::FOTO, $attachment->category);
        $this->assertSame('photo.jpg', $attachment->original_name);
        $this->assertSame('photo.jpg', $attachment->title); // defaulted from filename
        $this->assertSame($user->getKey(), $attachment->uploaded_by);
        Storage::disk()->assertExists($attachment->path);
    }
}
```

- [ ] **Step 4: Run the test to verify it fails, then passes**

Run: `ddev php artisan test --filter=AttachmentsRelationManagerTest`
Expected: PASS after Steps 1–2 are saved. (If you run it before Step 1, it fails with class-not-found.)

- [ ] **Step 5: Run the full attachments suite**

Run: `ddev php artisan test --filter=Attachments`
Expected: PASS (all four test classes green)

- [ ] **Step 6: Commit**

```bash
git add app/Filament/App/Resources/Assets/RelationManagers/AttachmentsRelationManager.php app/Filament/App/Resources/Assets/AssetResource.php tests/Feature/Attachments/AttachmentsRelationManagerTest.php
git commit -m "feat(attachments): Anhänge relation manager with upload, download, delete"
```

---

## Notes & known follow-ups (not in scope for this plan)

- **Image thumbnails / inline preview:** the spec mentions thumbnails via signed/temporary URLs. On a private default disk this needs a signed route or temporary URL; left out of the initial build to keep the download path disk-agnostic. The Download action covers viewing. Add an `ImageColumn` with a temporary-URL callback as a follow-up if desired.
- **Edit action file replacement:** Edit only changes metadata (title/category/note), not the file itself. Replacing a file = delete + re-upload, which is fine for v1.

## Self-Review Notes

- **Spec coverage:** table/model/enum/note column (Task 1), no disk column (Task 1 migration), `HasAttachments` (Task 2), file deletion + activity log against asset (Task 3), relation manager with upload/download/delete/badge/category/title/note/uploaded_by/type-detection/MIME+size validation (Task 4), tests with `Storage::fake()` (all tasks). Thumbnails explicitly deferred (noted above).
- **Test style:** PHPUnit classes extending `Tests\TestCase`, matching the existing `AssetImporterTest` style — not Pest.
- **Type consistency:** `detectType()`, `attachments()`, column names, and `AttachmentCategory` cases are used identically across tasks.
