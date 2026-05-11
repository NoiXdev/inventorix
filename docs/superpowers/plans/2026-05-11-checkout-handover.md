# Checkout / Handover Workflow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a guided Filament workflow that hands assets out / receives them back, captures a drawn signature, transitions the asset's state + owner deterministically, generates a signed PDF, and emails it to the recipient.

**Architecture:** A new `Handover` aggregate (handovers + handover_asset pivot, both UUID-keyed) owns one signed event. A reusable `HandoverWizardAction` (4-step Filament wizard) is invoked from row, header, bulk and standalone entry points; the same wizard always commits through `HandoverService::commit()` inside a single `DB::transaction()` with `lockForUpdate()` on each asset. A queued `GenerateHandoverPdf` job renders the PDF via `barryvdh/laravel-dompdf` and chains `SendHandoverEmail`. The existing Spatie activity log gets one new event description, `handover_completed`, so handovers surface inside the asset's History tab automatically.

**Tech Stack:** PHP 8.3+, Laravel 13, Filament 5, PHPUnit 12, DDEV, Spatie ActivityLog (already installed). New dependency: `barryvdh/laravel-dompdf` (latest release compatible with Laravel 13).

**Spec:** `docs/superpowers/specs/2026-05-11-checkout-handover-design.md`

**All commands run through DDEV** per project convention (see `CLAUDE.md`). Use `ddev composer …`, `ddev artisan …`, `ddev exec php artisan test …`.

---

## File Inventory

**New files:**
- `composer.json` / `composer.lock` (via `ddev composer require`)
- `config/handover.php`
- `database/migrations/<ts>_create_handovers_table.php`
- `database/migrations/<ts>_create_handover_asset_table.php`
- `app/Enums/HandoverType.php`
- `app/Enums/RecipientKind.php`
- `app/Exceptions/HandoverStateConflictException.php`
- `app/DataObjects/HandoverData.php`
- `app/Models/Handover.php`
- `app/Models/HandoverAsset.php`
- `app/Policies/HandoverPolicy.php`
- `app/Services/HandoverService.php`
- `app/Jobs/GenerateHandoverPdf.php`
- `app/Mail/HandoverSigned.php`
- `app/Filament/App/Resources/Handovers/HandoverResource.php`
- `app/Filament/App/Resources/Handovers/Tables/HandoversTable.php`
- `app/Filament/App/Resources/Handovers/Schemas/HandoverInfolist.php`
- `app/Filament/App/Resources/Handovers/Pages/ListHandovers.php`
- `app/Filament/App/Resources/Handovers/Pages/ViewHandover.php`
- `app/Filament/App/Resources/Handovers/Actions/HandoverWizardAction.php`
- `app/Filament/App/Resources/Handovers/Forms/SignaturePad.php`
- `database/factories/HandoverFactory.php`
- `resources/views/components/handover/signature-pad.blade.php`
- `resources/views/pdf/handover.blade.php`
- `resources/views/emails/handover-signed.blade.php`
- `lang/de/handover.php`
- `tests/Feature/Handover/HandoverServiceTest.php`
- `tests/Feature/Handover/HandoverPdfTest.php`
- `tests/Feature/Handover/HandoverEmailTest.php`
- `tests/Feature/Handover/HandoverPolicyTest.php`
- `tests/Feature/Filament/HandoverWizardTest.php`

**Modified files:**
- `app/Models/Asset.php` — add `handovers()` relation, register `Handover::class` in policies map (via auto-discovery)
- `app/Filament/App/Resources/Assets/AssetResource.php` — register row, header, bulk actions
- `app/Filament/App/Resources/Assets/Tables/AssetsTable.php` — wire bulk + row actions
- `app/Filament/App/Resources/Assets/Pages/EditAsset.php` — wire header action
- `app/Support/History/SummaryBuilder.php` — add `handover_completed` case
- `app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php` — add `handover_completed` to the event-kind filter and to the translation lookup

**Note on translations:** The project ships only `lang/de/` today; there is no `lang/en/`. The plan creates only `lang/de/handover.php`, consistent with the existing audit-log plan.

**Note on tests:** Project test config (`phpunit.xml`) sets `DB_CONNECTION=sqlite` `DB_DATABASE=:memory:`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`. Tests use `RefreshDatabase`. SQLite is the test driver — concurrency tests use a state-mutation closure to simulate the race rather than real parallel connections.

---

## Task 1: Install dompdf and create config

**Files:**
- Modify: `composer.json`, `composer.lock` (via `ddev composer require`)
- Create: `config/handover.php`

- [ ] **Step 1: Install the PDF package via DDEV composer**

Run:
```bash
ddev composer require barryvdh/laravel-dompdf
```

Expected: composer adds `barryvdh/laravel-dompdf` to `require`, updates `composer.lock`, no errors.

- [ ] **Step 2: Create `config/handover.php`**

Create `config/handover.php` with:
```php
<?php

return [
    'disk' => env('HANDOVER_DISK', 'local'),

    'company' => [
        'name' => env('APP_COMPANY_NAME', config('app.name')),
        'logo' => env('APP_COMPANY_LOGO'),
    ],

    'terms' => <<<'TXT'
Ich bestätige, dass ich die oben aufgeführten Gegenstände in einwandfreiem
Zustand übernommen habe und für deren sachgemäßen Gebrauch und Rückgabe
verantwortlich bin.
TXT,

    'pdf' => [
        'paper' => 'a4',
        'orientation' => 'portrait',
    ],

    'signature' => [
        'max_bytes' => 200 * 1024,
        'width' => 600,
        'height' => 200,
    ],
];
```

- [ ] **Step 3: Verify config loads**

Run:
```bash
ddev artisan tinker --execute="dd(config('handover.disk'));"
```

Expected output: `"local"`.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock config/handover.php
git commit -m "feat(handover): install dompdf and seed handover config"
```

---

## Task 2: Create `HandoverType` and `RecipientKind` enums

**Files:**
- Create: `app/Enums/HandoverType.php`
- Create: `app/Enums/RecipientKind.php`

- [ ] **Step 1: Create `HandoverType` enum**

Create `app/Enums/HandoverType.php`:
```php
<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum HandoverType: string implements HasLabel, HasColor
{
    case ISSUE = 'issue';
    case LEND = 'lend';
    case RETURN_ = 'return';
    case RETURN_DEFECT = 'return_defect';

    public function getLabel(): ?string
    {
        return trans('handover.type.' . $this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ISSUE         => Color::Green,
            self::LEND          => Color::Slate,
            self::RETURN_       => Color::Gray,
            self::RETURN_DEFECT => Color::Red,
        };
    }

    /** @return array<int, AssetState> */
    public function allowedStateFrom(): array
    {
        return match ($this) {
            self::ISSUE, self::LEND               => [AssetState::NEW, AssetState::STORAGE],
            self::RETURN_, self::RETURN_DEFECT    => [AssetState::IN_USE, AssetState::LEND],
        };
    }

    public function stateTo(): AssetState
    {
        return match ($this) {
            self::ISSUE          => AssetState::IN_USE,
            self::LEND           => AssetState::LEND,
            self::RETURN_        => AssetState::STORAGE,
            self::RETURN_DEFECT  => AssetState::NEED_REPAIR,
        };
    }

    public function assignsRecipientAsOwner(): bool
    {
        return $this === self::ISSUE || $this === self::LEND;
    }
}
```

- [ ] **Step 2: Create `RecipientKind` enum**

Create `app/Enums/RecipientKind.php`:
```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RecipientKind: string implements HasLabel
{
    case INTERNAL = 'internal';
    case EXTERNAL = 'external';

    public function getLabel(): ?string
    {
        return trans('handover.recipient_kind.' . $this->value);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Enums/HandoverType.php app/Enums/RecipientKind.php
git commit -m "feat(handover): add HandoverType and RecipientKind enums"
```

---

## Task 3: Create migrations for `handovers` and `handover_asset`

**Files:**
- Create: `database/migrations/<ts>_create_handovers_table.php`
- Create: `database/migrations/<ts>_create_handover_asset_table.php`

- [ ] **Step 1: Generate migration files**

Run (each command must finish before the next, since timestamps depend on clock order):
```bash
ddev artisan make:migration create_handovers_table
ddev artisan make:migration create_handover_asset_table
```

Expected: two new files in `database/migrations/`. Note the exact filenames printed by artisan — they include a timestamp prefix.

- [ ] **Step 2: Fill in `create_handovers_table` migration**

Replace the body of the newly created `_create_handovers_table.php` `up()` with:
```php
Schema::create('handovers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('type');
    $table->string('recipient_kind');
    $table->foreignUuid('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('recipient_name');
    $table->string('recipient_email')->nullable();
    $table->text('accessories')->nullable();
    $table->text('condition_notes')->nullable();
    $table->text('terms_text');
    $table->string('signature_path');
    $table->string('signature_ip')->nullable();
    $table->string('signature_user_agent', 512)->nullable();
    $table->string('pdf_path')->nullable();
    $table->dateTime('signed_at');
    $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
    $table->dateTime('email_sent_at')->nullable();
    $table->timestamps();

    $table->index(['type', 'signed_at']);
    $table->index('recipient_kind');
});
```

Keep `down()` as `Schema::dropIfExists('handovers');`.

- [ ] **Step 3: Fill in `create_handover_asset_table` migration**

Replace the body of the newly created `_create_handover_asset_table.php` `up()` with:
```php
Schema::create('handover_asset', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('handover_id')->constrained('handovers')->cascadeOnDelete();
    $table->foreignUuid('asset_id')->constrained('assets')->cascadeOnDelete();
    $table->string('state_from');
    $table->string('state_to');
    $table->foreignUuid('owner_from_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignUuid('owner_to_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->unique(['handover_id', 'asset_id']);
});
```

Keep `down()` as `Schema::dropIfExists('handover_asset');`.

- [ ] **Step 4: Run migrations to verify they apply cleanly**

Run:
```bash
ddev artisan migrate
```

Expected: both migrations run, no errors, two new tables.

- [ ] **Step 5: Commit**

```bash
git add database/migrations
git commit -m "feat(handover): add handovers and handover_asset tables"
```

---

## Task 4: Create `Handover` and `HandoverAsset` models with relationships and factory

**Files:**
- Create: `app/Models/Handover.php`
- Create: `app/Models/HandoverAsset.php`
- Create: `database/factories/HandoverFactory.php`
- Modify: `app/Models/Asset.php`

- [ ] **Step 1: Create `Handover` model**

Create `app/Models/Handover.php`:
```php
<?php

namespace App\Models;

use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'type', 'recipient_kind',
    'recipient_user_id', 'recipient_name', 'recipient_email',
    'accessories', 'condition_notes', 'terms_text',
    'signature_path', 'signature_ip', 'signature_user_agent',
    'pdf_path', 'signed_at', 'created_by', 'email_sent_at',
])]
class Handover extends Model
{
    use HasUuids, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => HandoverType::class,
            'recipient_kind' => RecipientKind::class,
            'signed_at' => 'datetime',
            'email_sent_at' => 'datetime',
        ];
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'handover_asset')
            ->using(HandoverAsset::class)
            ->withPivot(['id', 'state_from', 'state_to', 'owner_from_id', 'owner_to_id'])
            ->withTimestamps();
    }
}
```

- [ ] **Step 2: Create `HandoverAsset` pivot model**

Create `app/Models/HandoverAsset.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class HandoverAsset extends Pivot
{
    use HasUuids;

    protected $table = 'handover_asset';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = true;

    protected $fillable = [
        'handover_id', 'asset_id',
        'state_from', 'state_to',
        'owner_from_id', 'owner_to_id',
    ];
}
```

- [ ] **Step 3: Add `handovers()` relation on Asset**

Edit `app/Models/Asset.php`. Add this method below the existing `incidents()` method:
```php
public function handovers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    return $this->belongsToMany(Handover::class, 'handover_asset')
        ->using(HandoverAsset::class)
        ->withPivot(['id', 'state_from', 'state_to', 'owner_from_id', 'owner_to_id'])
        ->withTimestamps()
        ->orderByDesc('handovers.signed_at');
}
```

- [ ] **Step 4: Create `HandoverFactory`**

Create `database/factories/HandoverFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Models\Handover;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Handover> */
class HandoverFactory extends Factory
{
    protected $model = Handover::class;

    public function definition(): array
    {
        return [
            'type' => HandoverType::ISSUE->value,
            'recipient_kind' => RecipientKind::INTERNAL->value,
            'recipient_user_id' => User::factory(),
            'recipient_name' => fake()->name(),
            'recipient_email' => fake()->safeEmail(),
            'accessories' => null,
            'condition_notes' => null,
            'terms_text' => config('handover.terms'),
            'signature_path' => 'handovers/test/signature.png',
            'signature_ip' => '127.0.0.1',
            'signature_user_agent' => 'phpunit',
            'pdf_path' => null,
            'signed_at' => now(),
            'created_by' => User::factory(),
            'email_sent_at' => null,
        ];
    }
}
```

- [ ] **Step 5: Run a quick factory smoke test**

Run:
```bash
ddev artisan tinker --execute="dd(App\Models\Handover::factory()->make()->toArray());"
```

Expected: array dumped with the expected keys; no exception. (Don't `create()` — that would hit the DB outside the test suite.)

- [ ] **Step 6: Commit**

```bash
git add app/Models/Handover.php app/Models/HandoverAsset.php app/Models/Asset.php database/factories/HandoverFactory.php
git commit -m "feat(handover): add Handover model, pivot, factory, and Asset relation"
```

---

## Task 5: Create translation file

**Files:**
- Create: `lang/de/handover.php`

- [ ] **Step 1: Create translation file**

Create `lang/de/handover.php`:
```php
<?php

return [
    'nav' => [
        'label' => 'Übergaben',
        'group' => 'Inventar',
    ],
    'resource' => [
        'label' => 'Übergabe',
        'plural' => 'Übergaben',
    ],
    'type' => [
        'issue' => 'Ausgabe',
        'lend' => 'Leihgabe',
        'return' => 'Rückgabe',
        'return_defect' => 'Rückgabe (defekt)',
    ],
    'recipient_kind' => [
        'internal' => 'Interner Mitarbeiter',
        'external' => 'Externe Person',
    ],
    'recipient' => [
        'name' => 'Name',
        'email' => 'E-Mail',
        'select_user' => 'Mitarbeiter auswählen',
    ],
    'form' => [
        'accessories' => 'Zubehör',
        'accessories_placeholder' => 'z. B. Ladegerät, USB-C-Kabel, Tasche',
        'condition_notes' => 'Zustandsnotizen',
        'condition_notes_placeholder' => 'z. B. Kratzer auf Deckel',
        'terms_header' => 'Vereinbarung',
    ],
    'sign' => [
        'pad_label' => 'Unterschrift',
        'clear' => 'Löschen',
        'required' => 'Bitte unterschreiben Sie, um die Übergabe abzuschließen.',
        'submit_with_email' => 'Übergeben und per E-Mail senden',
        'submit_without_email' => 'Übergeben',
    ],
    'wizard' => [
        'step' => [
            'type' => 'Art & Gegenstände',
            'recipient' => 'Empfänger',
            'details' => 'Details',
            'sign' => 'Unterschrift',
        ],
    ],
    'action' => [
        'handover' => 'Übergabe',
        'return' => 'Rückgabe',
        'bulk' => 'Übergabe (mehrere)',
        'new' => 'Neue Übergabe',
    ],
    'notification' => [
        'success' => 'Übergabe unterzeichnet. PDF wird erstellt.',
        'pdf_failed' => 'PDF-Erstellung fehlgeschlagen — bitte erneut versuchen.',
        'email_sent' => 'E-Mail an :email gesendet.',
        'state_conflict' => 'Der Status eines oder mehrerer Gegenstände hat sich geändert. Bitte Übergabe neu starten.',
    ],
    'pdf' => [
        'title' => 'Übergabeprotokoll',
        'type' => 'Art',
        'recipient' => 'Empfänger',
        'recipient_internal' => 'Interner Mitarbeiter',
        'recipient_external' => 'Externe Person',
        'email' => 'E-Mail',
        'handed_by' => 'Übergeben von',
        'signed_at' => 'Unterzeichnet am',
        'signed_ip' => 'IP-Adresse',
        'assets' => 'Gegenstände',
        'accessories' => 'Zubehör',
        'condition' => 'Zustand',
        'terms' => 'Vereinbarung',
        'signature' => 'Unterschrift',
        'state_transition' => ':from → :to',
    ],
    'mail' => [
        'subject' => 'Übergabeprotokoll — :type',
        'intro' => 'Hallo :name,',
        'body' => 'anbei finden Sie das Übergabeprotokoll zu den am :date an Sie übergebenen Gegenständen.',
        'outro' => 'Bei Fragen wenden Sie sich bitte an uns.',
    ],
    'history' => [
        'event' => [
            'handover_completed' => 'Übergabe abgeschlossen',
        ],
        'summary' => [
            'handover_completed' => ':type — :recipient',
        ],
    ],
    'list' => [
        'column' => [
            'signed_at' => 'Datum',
            'type' => 'Art',
            'recipient' => 'Empfänger',
            'asset_count' => 'Gegenstände',
            'created_by' => 'Bearbeiter',
            'pdf' => 'PDF',
        ],
        'pdf_pending' => 'Wird erstellt …',
        'pdf_download' => 'Herunterladen',
    ],
    'view' => [
        'recipient_section' => 'Empfänger',
        'details_section' => 'Details',
        'assets_section' => 'Gegenstände',
        'signature_section' => 'Unterschrift',
        'meta_section' => 'Metadaten',
    ],
];
```

- [ ] **Step 2: Commit**

```bash
git add lang/de/handover.php
git commit -m "feat(handover): add German translations"
```

---

## Task 6: Create `HandoverData` DTO and `HandoverStateConflictException`

**Files:**
- Create: `app/DataObjects/HandoverData.php`
- Create: `app/Exceptions/HandoverStateConflictException.php`

- [ ] **Step 1: Create the exception**

Create `app/Exceptions/HandoverStateConflictException.php`:
```php
<?php

namespace App\Exceptions;

use RuntimeException;

class HandoverStateConflictException extends RuntimeException
{
    /** @param array<int, string> $assetIds */
    public function __construct(public readonly array $assetIds, ?string $message = null)
    {
        parent::__construct($message ?? 'Asset state changed since the handover was started.');
    }
}
```

- [ ] **Step 2: Create the DTO**

Create `app/DataObjects/HandoverData.php`:
```php
<?php

namespace App\DataObjects;

use App\Enums\HandoverType;
use App\Enums\RecipientKind;

final class HandoverData
{
    /** @param array<int, string> $assetIds */
    public function __construct(
        public HandoverType $type,
        public RecipientKind $recipientKind,
        public ?string $recipientUserId,
        public string $recipientName,
        public ?string $recipientEmail,
        public array $assetIds,
        public ?string $accessories,
        public ?string $conditionNotes,
        public string $termsText,
        public string $signaturePngBase64,
        public ?string $signatureIp,
        public ?string $signatureUserAgent,
        public string $createdById,
    ) {}
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Exceptions/HandoverStateConflictException.php app/DataObjects/HandoverData.php
git commit -m "feat(handover): add HandoverData DTO and state-conflict exception"
```

---

## Task 7: `HandoverService::commit` — happy path for ISSUE (TDD)

**Files:**
- Create: `app/Services/HandoverService.php`
- Create: `tests/Feature/Handover/HandoverServiceTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Handover/HandoverServiceTest.php`:
```php
<?php

namespace Tests\Feature\Handover;

use App\DataObjects\HandoverData;
use App\Enums\AssetState;
use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Models\Asset;
use App\Models\User;
use App\Services\HandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HandoverServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_issue_sets_state_in_use_and_owner_to_recipient(): void
    {
        $recipient = User::factory()->create();
        $manager = User::factory()->create();
        $asset = Asset::factory()->create([
            'state' => AssetState::STORAGE->value,
            'owner_id' => null,
        ]);

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipient->name,
            recipientEmail: $recipient->email,
            assetIds: [$asset->id],
            accessories: 'Ladegerät',
            conditionNotes: null,
            termsText: 'Terms snapshot',
            signaturePngBase64: $this->onePixelPng(),
            signatureIp: '127.0.0.1',
            signatureUserAgent: 'phpunit',
            createdById: $manager->id,
        );

        $handover = app(HandoverService::class)->commit($data);

        $this->assertDatabaseHas('handovers', [
            'id' => $handover->id,
            'type' => HandoverType::ISSUE->value,
            'recipient_user_id' => $recipient->id,
            'created_by' => $manager->id,
        ]);

        $asset->refresh();
        $this->assertSame(AssetState::IN_USE, $asset->state);
        $this->assertSame($recipient->id, $asset->owner_id);

        $this->assertDatabaseHas('handover_asset', [
            'handover_id' => $handover->id,
            'asset_id' => $asset->id,
            'state_from' => AssetState::STORAGE->value,
            'state_to' => AssetState::IN_USE->value,
            'owner_to_id' => $recipient->id,
        ]);

        Storage::disk('local')->assertExists($handover->signature_path);
    }

    private function onePixelPng(): string
    {
        // 1×1 transparent PNG (89 bytes).
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    }
}
```

- [ ] **Step 2: Run the test to confirm it fails**

Run:
```bash
ddev exec php artisan test --filter=test_issue_sets_state_in_use_and_owner_to_recipient
```

Expected: FAIL with "Class App\Services\HandoverService does not exist" or similar.

- [ ] **Step 3: Implement the service (minimum to pass)**

Create `app/Services/HandoverService.php`:
```php
<?php

namespace App\Services;

use App\DataObjects\HandoverData;
use App\Exceptions\HandoverStateConflictException;
use App\Models\Asset;
use App\Models\Handover;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HandoverService
{
    public function commit(HandoverData $data): Handover
    {
        return DB::transaction(function () use ($data): Handover {
            $assets = Asset::query()
                ->whereIn('id', $data->assetIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $allowedFrom = array_map(
                fn ($s) => $s->value,
                $data->type->allowedStateFrom(),
            );

            $conflicting = [];
            foreach ($data->assetIds as $assetId) {
                $asset = $assets[$assetId] ?? null;
                if ($asset === null || ! in_array($asset->state->value, $allowedFrom, true)) {
                    $conflicting[] = $assetId;
                }
            }

            if (! empty($conflicting)) {
                throw new HandoverStateConflictException($conflicting);
            }

            $handoverId = (string) Str::uuid();
            $disk = config('handover.disk');
            $signaturePath = "handovers/{$handoverId}/signature.png";

            Storage::disk($disk)->put(
                $signaturePath,
                base64_decode($data->signaturePngBase64, true),
            );

            $handover = Handover::create([
                'id' => $handoverId,
                'type' => $data->type->value,
                'recipient_kind' => $data->recipientKind->value,
                'recipient_user_id' => $data->recipientUserId,
                'recipient_name' => $data->recipientName,
                'recipient_email' => $data->recipientEmail,
                'accessories' => $data->accessories,
                'condition_notes' => $data->conditionNotes,
                'terms_text' => $data->termsText,
                'signature_path' => $signaturePath,
                'signature_ip' => $data->signatureIp,
                'signature_user_agent' => $data->signatureUserAgent,
                'signed_at' => now(),
                'created_by' => $data->createdById,
            ]);

            $stateTo = $data->type->stateTo()->value;
            $ownerTo = $data->type->assignsRecipientAsOwner() ? $data->recipientUserId : null;

            foreach ($data->assetIds as $assetId) {
                $asset = $assets[$assetId];
                $stateFrom = $asset->state->value;
                $ownerFrom = $asset->owner_id;

                $handover->assets()->attach($assetId, [
                    'id' => (string) Str::uuid(),
                    'state_from' => $stateFrom,
                    'state_to' => $stateTo,
                    'owner_from_id' => $ownerFrom,
                    'owner_to_id' => $ownerTo,
                ]);

                $asset->update([
                    'state' => $stateTo,
                    'owner_id' => $ownerTo,
                ]);
            }

            return $handover;
        });
    }
}
```

- [ ] **Step 4: Run the test, confirm it passes**

Run:
```bash
ddev exec php artisan test --filter=test_issue_sets_state_in_use_and_owner_to_recipient
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/HandoverService.php tests/Feature/Handover/HandoverServiceTest.php
git commit -m "feat(handover): add HandoverService::commit for ISSUE happy path"
```

---

## Task 8: Service — RETURN, RETURN_DEFECT, LEND transitions

**Files:**
- Modify: `tests/Feature/Handover/HandoverServiceTest.php`

- [ ] **Step 1: Add tests for all four transitions**

Append to `tests/Feature/Handover/HandoverServiceTest.php` (inside the class):
```php
public function test_lend_sets_state_lend_and_owner_to_recipient(): void
{
    $recipient = User::factory()->create();
    $asset = Asset::factory()->create(['state' => AssetState::STORAGE->value, 'owner_id' => null]);

    $handover = $this->dispatch(HandoverType::LEND, $recipient, $asset);

    $asset->refresh();
    $this->assertSame(AssetState::LEND, $asset->state);
    $this->assertSame($recipient->id, $asset->owner_id);
    $this->assertDatabaseHas('handover_asset', [
        'handover_id' => $handover->id,
        'state_to' => AssetState::LEND->value,
        'owner_to_id' => $recipient->id,
    ]);
}

public function test_return_clears_owner_and_moves_to_storage(): void
{
    $previousOwner = User::factory()->create();
    $asset = Asset::factory()->create([
        'state' => AssetState::IN_USE->value,
        'owner_id' => $previousOwner->id,
    ]);

    $handover = $this->dispatch(HandoverType::RETURN_, $previousOwner, $asset);

    $asset->refresh();
    $this->assertSame(AssetState::STORAGE, $asset->state);
    $this->assertNull($asset->owner_id);
    $this->assertDatabaseHas('handover_asset', [
        'handover_id' => $handover->id,
        'state_from' => AssetState::IN_USE->value,
        'state_to' => AssetState::STORAGE->value,
        'owner_from_id' => $previousOwner->id,
        'owner_to_id' => null,
    ]);
}

public function test_return_defect_clears_owner_and_moves_to_need_repair(): void
{
    $previousOwner = User::factory()->create();
    $asset = Asset::factory()->create([
        'state' => AssetState::IN_USE->value,
        'owner_id' => $previousOwner->id,
    ]);

    $handover = $this->dispatch(HandoverType::RETURN_DEFECT, $previousOwner, $asset);

    $asset->refresh();
    $this->assertSame(AssetState::NEED_REPAIR, $asset->state);
    $this->assertNull($asset->owner_id);
    $this->assertDatabaseHas('handover_asset', [
        'handover_id' => $handover->id,
        'state_to' => AssetState::NEED_REPAIR->value,
    ]);
}

private function dispatch(HandoverType $type, User $recipient, Asset $asset): \App\Models\Handover
{
    $manager = User::factory()->create();

    $data = new HandoverData(
        type: $type,
        recipientKind: RecipientKind::INTERNAL,
        recipientUserId: $recipient->id,
        recipientName: $recipient->name,
        recipientEmail: $recipient->email,
        assetIds: [$asset->id],
        accessories: null,
        conditionNotes: null,
        termsText: 'Terms snapshot',
        signaturePngBase64: $this->onePixelPng(),
        signatureIp: '127.0.0.1',
        signatureUserAgent: 'phpunit',
        createdById: $manager->id,
    );

    return app(HandoverService::class)->commit($data);
}
```

- [ ] **Step 2: Run the new tests**

Run:
```bash
ddev exec php artisan test --filter=HandoverServiceTest
```

Expected: all four (ISSUE, LEND, RETURN, RETURN_DEFECT) tests PASS. No service-code changes needed — the enum's `stateTo()` and `assignsRecipientAsOwner()` already cover them.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Handover/HandoverServiceTest.php
git commit -m "test(handover): cover LEND, RETURN, RETURN_DEFECT transitions"
```

---

## Task 9: Service — state conflict and signature file rollback

**Files:**
- Modify: `tests/Feature/Handover/HandoverServiceTest.php`
- Modify: `app/Services/HandoverService.php`

- [ ] **Step 1: Add tests for the rollback behaviour**

Append to the test class:
```php
public function test_state_conflict_throws_and_leaves_no_db_rows(): void
{
    $recipient = User::factory()->create();
    $manager = User::factory()->create();
    $asset = Asset::factory()->create(['state' => AssetState::IN_USE->value]);

    $data = new HandoverData(
        type: HandoverType::ISSUE,                   // requires NEW or STORAGE
        recipientKind: RecipientKind::INTERNAL,
        recipientUserId: $recipient->id,
        recipientName: $recipient->name,
        recipientEmail: null,
        assetIds: [$asset->id],
        accessories: null,
        conditionNotes: null,
        termsText: 'Terms snapshot',
        signaturePngBase64: $this->onePixelPng(),
        signatureIp: null,
        signatureUserAgent: null,
        createdById: $manager->id,
    );

    try {
        app(HandoverService::class)->commit($data);
        $this->fail('Expected HandoverStateConflictException');
    } catch (\App\Exceptions\HandoverStateConflictException $e) {
        $this->assertSame([$asset->id], $e->assetIds);
    }

    $this->assertDatabaseCount('handovers', 0);
    $this->assertDatabaseCount('handover_asset', 0);
    $this->assertCount(0, Storage::disk('local')->allFiles('handovers'));
}

public function test_unknown_asset_id_throws_state_conflict(): void
{
    $recipient = User::factory()->create();
    $manager = User::factory()->create();

    $data = new HandoverData(
        type: HandoverType::ISSUE,
        recipientKind: RecipientKind::INTERNAL,
        recipientUserId: $recipient->id,
        recipientName: $recipient->name,
        recipientEmail: null,
        assetIds: ['00000000-0000-0000-0000-000000000000'],
        accessories: null,
        conditionNotes: null,
        termsText: 'Terms snapshot',
        signaturePngBase64: $this->onePixelPng(),
        signatureIp: null,
        signatureUserAgent: null,
        createdById: $manager->id,
    );

    $this->expectException(\App\Exceptions\HandoverStateConflictException::class);
    app(HandoverService::class)->commit($data);
}
```

- [ ] **Step 2: Run the tests**

Run:
```bash
ddev exec php artisan test --filter=HandoverServiceTest
```

Expected: state-conflict tests PASS. The `DB::transaction` rolls back the `handovers` insert; but the signature file written before the conflict-check might leak. Verify which order the existing implementation runs. Looking at Task 7's code: the signature is written **after** the conflict check, so the file is never written when the exception fires. ✓ Tests pass without code changes.

But verify: re-read `HandoverService::commit()` (Task 7 step 3). Is the conflict check before the signature write? Yes — lines 1-30 do the check, lines 31-36 write the file. Good.

- [ ] **Step 3: Add explicit file-cleanup on any later exception**

The current service only writes one file before any further DB work; if any insert/update after `Storage::disk(...)->put(...)` throws, the transaction rolls back the DB but the signature file leaks. Wrap the post-file work in try/catch.

Edit `app/Services/HandoverService.php`. Replace the body of `commit()` with:
```php
return DB::transaction(function () use ($data): Handover {
    $assets = Asset::query()
        ->whereIn('id', $data->assetIds)
        ->lockForUpdate()
        ->get()
        ->keyBy('id');

    $allowedFrom = array_map(
        fn ($s) => $s->value,
        $data->type->allowedStateFrom(),
    );

    $conflicting = [];
    foreach ($data->assetIds as $assetId) {
        $asset = $assets[$assetId] ?? null;
        if ($asset === null || ! in_array($asset->state->value, $allowedFrom, true)) {
            $conflicting[] = $assetId;
        }
    }

    if (! empty($conflicting)) {
        throw new HandoverStateConflictException($conflicting);
    }

    $handoverId = (string) Str::uuid();
    $disk = config('handover.disk');
    $signaturePath = "handovers/{$handoverId}/signature.png";

    Storage::disk($disk)->put(
        $signaturePath,
        base64_decode($data->signaturePngBase64, true),
    );

    try {
        $handover = Handover::create([
            'id' => $handoverId,
            'type' => $data->type->value,
            'recipient_kind' => $data->recipientKind->value,
            'recipient_user_id' => $data->recipientUserId,
            'recipient_name' => $data->recipientName,
            'recipient_email' => $data->recipientEmail,
            'accessories' => $data->accessories,
            'condition_notes' => $data->conditionNotes,
            'terms_text' => $data->termsText,
            'signature_path' => $signaturePath,
            'signature_ip' => $data->signatureIp,
            'signature_user_agent' => $data->signatureUserAgent,
            'signed_at' => now(),
            'created_by' => $data->createdById,
        ]);

        $stateTo = $data->type->stateTo()->value;
        $ownerTo = $data->type->assignsRecipientAsOwner() ? $data->recipientUserId : null;

        foreach ($data->assetIds as $assetId) {
            $asset = $assets[$assetId];
            $stateFrom = $asset->state->value;
            $ownerFrom = $asset->owner_id;

            $handover->assets()->attach($assetId, [
                'id' => (string) Str::uuid(),
                'state_from' => $stateFrom,
                'state_to' => $stateTo,
                'owner_from_id' => $ownerFrom,
                'owner_to_id' => $ownerTo,
            ]);

            $asset->update([
                'state' => $stateTo,
                'owner_id' => $ownerTo,
            ]);
        }

        return $handover;
    } catch (\Throwable $e) {
        Storage::disk($disk)->delete($signaturePath);
        throw $e;
    }
});
```

- [ ] **Step 4: Add a test that exercises the file-cleanup path**

Append to the test class:
```php
public function test_throwing_inside_transaction_deletes_signature_file(): void
{
    $recipient = User::factory()->create();
    $manager = User::factory()->create();
    $asset = Asset::factory()->create(['state' => AssetState::STORAGE->value, 'owner_id' => null]);

    // Force the post-file branch to throw by sending an empty asset list AFTER
    // a valid one would have passed the conflict check. Easiest: monkey-patch
    // the Asset model to throw on update via a closure passed to a partial.
    // Cleaner approach: provide an asset that exists, then delete it inside the
    // transaction by simulating a rare race. Simpler still — rely on a bad
    // created_by FK (UUID that does not exist) to violate FK on insert.
    $bogusManagerId = (string) \Illuminate\Support\Str::uuid();

    $data = new HandoverData(
        type: HandoverType::ISSUE,
        recipientKind: RecipientKind::INTERNAL,
        recipientUserId: $recipient->id,
        recipientName: $recipient->name,
        recipientEmail: null,
        assetIds: [$asset->id],
        accessories: null,
        conditionNotes: null,
        termsText: 'Terms snapshot',
        signaturePngBase64: $this->onePixelPng(),
        signatureIp: null,
        signatureUserAgent: null,
        createdById: $bogusManagerId,
    );

    $this->expectException(\Throwable::class);

    try {
        app(HandoverService::class)->commit($data);
    } finally {
        $this->assertCount(0, Storage::disk('local')->allFiles('handovers'));
    }
}
```

(Test note: this test uses an invalid `created_by` UUID to force the inner Handover insert to fail on the FK; the catch block deletes the signature file. SQLite enforces foreign keys when the connection was opened with `PRAGMA foreign_keys=ON`, which Laravel does by default.)

- [ ] **Step 5: Run the tests**

Run:
```bash
ddev exec php artisan test --filter=HandoverServiceTest
```

Expected: all tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/HandoverService.php tests/Feature/Handover/HandoverServiceTest.php
git commit -m "feat(handover): roll back signature file on transaction failure"
```

---

## Task 10: Service — external recipient + multi-asset bulk

**Files:**
- Modify: `tests/Feature/Handover/HandoverServiceTest.php`

- [ ] **Step 1: Add tests**

Append to the test class:
```php
public function test_external_recipient_leaves_owner_null_but_changes_state(): void
{
    $manager = User::factory()->create();
    $asset = Asset::factory()->create(['state' => AssetState::STORAGE->value, 'owner_id' => null]);

    $data = new HandoverData(
        type: HandoverType::ISSUE,
        recipientKind: RecipientKind::EXTERNAL,
        recipientUserId: null,
        recipientName: 'Jane External',
        recipientEmail: 'jane@example.com',
        assetIds: [$asset->id],
        accessories: null,
        conditionNotes: null,
        termsText: 'Terms snapshot',
        signaturePngBase64: $this->onePixelPng(),
        signatureIp: '127.0.0.1',
        signatureUserAgent: 'phpunit',
        createdById: $manager->id,
    );

    $handover = app(HandoverService::class)->commit($data);
    $asset->refresh();

    $this->assertNull($handover->recipient_user_id);
    $this->assertSame('Jane External', $handover->recipient_name);
    $this->assertSame(AssetState::IN_USE, $asset->state);
    $this->assertNull($asset->owner_id);
    $this->assertDatabaseHas('handover_asset', [
        'handover_id' => $handover->id,
        'asset_id' => $asset->id,
        'owner_to_id' => null,
    ]);
}

public function test_bulk_handover_attaches_each_asset_with_its_own_snapshots(): void
{
    $recipient = User::factory()->create();
    $manager = User::factory()->create();
    $previousOwner = User::factory()->create();

    $a = Asset::factory()->create(['state' => AssetState::STORAGE->value, 'owner_id' => null]);
    $b = Asset::factory()->create(['state' => AssetState::NEW->value, 'owner_id' => $previousOwner->id]);

    $data = new HandoverData(
        type: HandoverType::ISSUE,
        recipientKind: RecipientKind::INTERNAL,
        recipientUserId: $recipient->id,
        recipientName: $recipient->name,
        recipientEmail: $recipient->email,
        assetIds: [$a->id, $b->id],
        accessories: null,
        conditionNotes: null,
        termsText: 'Terms snapshot',
        signaturePngBase64: $this->onePixelPng(),
        signatureIp: null,
        signatureUserAgent: null,
        createdById: $manager->id,
    );

    $handover = app(HandoverService::class)->commit($data);

    $this->assertDatabaseHas('handover_asset', [
        'handover_id' => $handover->id,
        'asset_id' => $a->id,
        'state_from' => AssetState::STORAGE->value,
        'owner_from_id' => null,
    ]);
    $this->assertDatabaseHas('handover_asset', [
        'handover_id' => $handover->id,
        'asset_id' => $b->id,
        'state_from' => AssetState::NEW->value,
        'owner_from_id' => $previousOwner->id,
    ]);

    $a->refresh();
    $b->refresh();
    $this->assertSame(AssetState::IN_USE, $a->state);
    $this->assertSame(AssetState::IN_USE, $b->state);
}

public function test_bulk_with_one_invalid_state_rolls_everything_back(): void
{
    $recipient = User::factory()->create();
    $manager = User::factory()->create();
    $valid = Asset::factory()->create(['state' => AssetState::STORAGE->value]);
    $invalid = Asset::factory()->create(['state' => AssetState::IN_USE->value]);  // not allowed for ISSUE

    $data = new HandoverData(
        type: HandoverType::ISSUE,
        recipientKind: RecipientKind::INTERNAL,
        recipientUserId: $recipient->id,
        recipientName: $recipient->name,
        recipientEmail: null,
        assetIds: [$valid->id, $invalid->id],
        accessories: null,
        conditionNotes: null,
        termsText: 'Terms snapshot',
        signaturePngBase64: $this->onePixelPng(),
        signatureIp: null,
        signatureUserAgent: null,
        createdById: $manager->id,
    );

    try {
        app(HandoverService::class)->commit($data);
        $this->fail('Expected conflict');
    } catch (\App\Exceptions\HandoverStateConflictException $e) {
        $this->assertSame([$invalid->id], $e->assetIds);
    }

    $valid->refresh();
    $this->assertSame(AssetState::STORAGE, $valid->state, 'Valid asset must not be partially handed over.');
    $this->assertDatabaseCount('handovers', 0);
}
```

- [ ] **Step 2: Run the tests**

Run:
```bash
ddev exec php artisan test --filter=HandoverServiceTest
```

Expected: all PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Handover/HandoverServiceTest.php
git commit -m "test(handover): cover external recipient and bulk handover"
```

---

## Task 11: Service — write `handover_completed` activity row

**Files:**
- Modify: `tests/Feature/Handover/HandoverServiceTest.php`
- Modify: `app/Services/HandoverService.php`

- [ ] **Step 1: Add test**

Append to the test class:
```php
public function test_commit_writes_handover_completed_activity_per_asset(): void
{
    $recipient = User::factory()->create();
    $manager = User::factory()->create();
    $this->actingAs($manager);
    $a = Asset::factory()->create(['state' => AssetState::STORAGE->value, 'owner_id' => null]);
    $b = Asset::factory()->create(['state' => AssetState::STORAGE->value, 'owner_id' => null]);

    $data = new HandoverData(
        type: HandoverType::ISSUE,
        recipientKind: RecipientKind::INTERNAL,
        recipientUserId: $recipient->id,
        recipientName: $recipient->name,
        recipientEmail: $recipient->email,
        assetIds: [$a->id, $b->id],
        accessories: null,
        conditionNotes: null,
        termsText: 'Terms snapshot',
        signaturePngBase64: $this->onePixelPng(),
        signatureIp: null,
        signatureUserAgent: null,
        createdById: $manager->id,
    );

    $handover = app(HandoverService::class)->commit($data);

    foreach ([$a->id, $b->id] as $assetId) {
        $activity = \Spatie\Activitylog\Models\Activity::query()
            ->where('subject_type', Asset::class)
            ->where('subject_id', $assetId)
            ->where('description', 'handover_completed')
            ->first();

        $this->assertNotNull($activity, "No handover_completed activity for asset {$assetId}");
        $this->assertSame('asset', $activity->log_name);
        $this->assertSame($handover->id, $activity->properties['handover_id']);
        $this->assertSame(HandoverType::ISSUE->value, $activity->properties['type']);
        $this->assertSame($recipient->name, $activity->properties['recipient_name']);
    }
}
```

- [ ] **Step 2: Implement**

Edit `app/Services/HandoverService.php`. Inside the `foreach ($data->assetIds …)` loop, **after** `$asset->update([...])`, append:
```php
activity('asset')
    ->performedOn($asset->fresh())
    ->causedBy(\Illuminate\Support\Facades\Auth::id() ?: $data->createdById)
    ->withProperties([
        'handover_id' => $handoverId,
        'type' => $data->type->value,
        'recipient_name' => $data->recipientName,
    ])
    ->log('handover_completed');
```

(`causedBy()` falls back to `createdById` for code paths without an authenticated user — e.g. scheduled jobs in the future. In normal Filament use, the auth user is set.)

- [ ] **Step 3: Run the test**

Run:
```bash
ddev exec php artisan test --filter=HandoverServiceTest
```

Expected: all tests PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Services/HandoverService.php tests/Feature/Handover/HandoverServiceTest.php
git commit -m "feat(handover): write handover_completed activity per asset"
```

---

## Task 12: Signature payload validation

**Files:**
- Modify: `app/Services/HandoverService.php`
- Modify: `tests/Feature/Handover/HandoverServiceTest.php`

- [ ] **Step 1: Add failing tests**

Append to the test class:
```php
public function test_invalid_base64_payload_throws(): void
{
    $recipient = User::factory()->create();
    $manager = User::factory()->create();
    $asset = Asset::factory()->create(['state' => AssetState::STORAGE->value]);

    $data = new HandoverData(
        type: HandoverType::ISSUE,
        recipientKind: RecipientKind::INTERNAL,
        recipientUserId: $recipient->id,
        recipientName: $recipient->name,
        recipientEmail: null,
        assetIds: [$asset->id],
        accessories: null,
        conditionNotes: null,
        termsText: 'Terms snapshot',
        signaturePngBase64: 'not-base64!!!',
        signatureIp: null,
        signatureUserAgent: null,
        createdById: $manager->id,
    );

    $this->expectException(\InvalidArgumentException::class);
    app(HandoverService::class)->commit($data);
}

public function test_non_png_payload_throws(): void
{
    $recipient = User::factory()->create();
    $manager = User::factory()->create();
    $asset = Asset::factory()->create(['state' => AssetState::STORAGE->value]);

    $data = new HandoverData(
        type: HandoverType::ISSUE,
        recipientKind: RecipientKind::INTERNAL,
        recipientUserId: $recipient->id,
        recipientName: $recipient->name,
        recipientEmail: null,
        assetIds: [$asset->id],
        accessories: null,
        conditionNotes: null,
        termsText: 'Terms snapshot',
        signaturePngBase64: base64_encode('GIF87a' . str_repeat('x', 100)),
        signatureIp: null,
        signatureUserAgent: null,
        createdById: $manager->id,
    );

    $this->expectException(\InvalidArgumentException::class);
    app(HandoverService::class)->commit($data);
}

public function test_too_large_signature_throws(): void
{
    $recipient = User::factory()->create();
    $manager = User::factory()->create();
    $asset = Asset::factory()->create(['state' => AssetState::STORAGE->value]);

    $tooBig = "\x89PNG\r\n\x1a\n" . str_repeat('x', config('handover.signature.max_bytes') + 1);

    $data = new HandoverData(
        type: HandoverType::ISSUE,
        recipientKind: RecipientKind::INTERNAL,
        recipientUserId: $recipient->id,
        recipientName: $recipient->name,
        recipientEmail: null,
        assetIds: [$asset->id],
        accessories: null,
        conditionNotes: null,
        termsText: 'Terms snapshot',
        signaturePngBase64: base64_encode($tooBig),
        signatureIp: null,
        signatureUserAgent: null,
        createdById: $manager->id,
    );

    $this->expectException(\InvalidArgumentException::class);
    app(HandoverService::class)->commit($data);
}
```

- [ ] **Step 2: Implement validator**

Edit `app/Services/HandoverService.php`. Add a private method:
```php
private function decodeSignature(string $base64): string
{
    $decoded = base64_decode($base64, true);
    if ($decoded === false) {
        throw new \InvalidArgumentException('Signature payload is not valid base64.');
    }

    if (strlen($decoded) > (int) config('handover.signature.max_bytes')) {
        throw new \InvalidArgumentException('Signature exceeds max bytes.');
    }

    if (substr($decoded, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        throw new \InvalidArgumentException('Signature is not a PNG.');
    }

    return $decoded;
}
```

Replace the existing `Storage::disk($disk)->put(... base64_decode(...))` call with:
```php
$signatureBytes = $this->decodeSignature($data->signaturePngBase64);
Storage::disk($disk)->put($signaturePath, $signatureBytes);
```

Move the `decodeSignature()` call **before** the `Storage::put`, so an invalid signature aborts before any disk write. (Conflict check still runs first — the order is: conflict check → decode → write file → DB work.)

- [ ] **Step 3: Run the tests**

Run:
```bash
ddev exec php artisan test --filter=HandoverServiceTest
```

Expected: all PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Services/HandoverService.php tests/Feature/Handover/HandoverServiceTest.php
git commit -m "feat(handover): validate signature payload size and PNG magic bytes"
```

---

## Task 13: `GenerateHandoverPdf` job + Blade template

**Files:**
- Create: `app/Jobs/GenerateHandoverPdf.php`
- Create: `resources/views/pdf/handover.blade.php`
- Create: `tests/Feature/Handover/HandoverPdfTest.php`
- Modify: `app/Services/HandoverService.php`

- [ ] **Step 1: Create the PDF Blade template**

Create `resources/views/pdf/handover.blade.php`:
```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ trans('handover.pdf.title') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #111; }
        h1 { font-size: 18pt; margin: 0 0 4px; }
        h2 { font-size: 12pt; margin: 16px 0 4px; border-bottom: 1px solid #ccc; padding-bottom: 2px; }
        .meta { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
        .terms { background: #f7f7f7; padding: 8px; margin-top: 6px; white-space: pre-wrap; }
        .signature { border: 1px solid #aaa; padding: 6px; margin-top: 6px; text-align: center; }
        .signature img { max-height: 120px; }
    </style>
</head>
<body>
    <h1>{{ trans('handover.pdf.title') }}</h1>
    <div class="meta">
        {{ trans('handover.pdf.type') }}: {{ $handover->type->getLabel() }}
        @if($companyName)
            <br>{{ $companyName }}
        @endif
    </div>

    <h2>{{ trans('handover.pdf.recipient') }}</h2>
    <div>
        {{ $handover->recipient_name }}
        ({{ $handover->recipient_kind->value === 'internal' ? trans('handover.pdf.recipient_internal') : trans('handover.pdf.recipient_external') }})
        @if($handover->recipient_email)
            <br>{{ trans('handover.pdf.email') }}: {{ $handover->recipient_email }}
        @endif
    </div>

    <h2>{{ trans('handover.pdf.assets') }}</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ trans('asset.model') ?? 'Modell' }}</th>
                <th>{{ trans('asset.serial_number') ?? 'Seriennummer' }}</th>
                <th>{{ trans('handover.pdf.state_transition', ['from' => '', 'to' => '']) }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($handover->assets as $i => $asset)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ optional($asset->model)->name }}</td>
                    <td>{{ $asset->serial_number }}</td>
                    <td>
                        {{ trans('handover.pdf.state_transition', [
                            'from' => \App\Enums\AssetState::from($asset->pivot->state_from)->getLabel(),
                            'to'   => \App\Enums\AssetState::from($asset->pivot->state_to)->getLabel(),
                        ]) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($handover->accessories)
        <h2>{{ trans('handover.pdf.accessories') }}</h2>
        <div>{{ $handover->accessories }}</div>
    @endif

    @if($handover->condition_notes)
        <h2>{{ trans('handover.pdf.condition') }}</h2>
        <div>{{ $handover->condition_notes }}</div>
    @endif

    <h2>{{ trans('handover.pdf.terms') }}</h2>
    <div class="terms">{{ $handover->terms_text }}</div>

    <h2>{{ trans('handover.pdf.signature') }}</h2>
    <div class="signature">
        <img src="data:image/png;base64,{{ $signatureBase64 }}" alt="signature">
        <div>{{ $handover->recipient_name }} — {{ $handover->signed_at->format('Y-m-d H:i') }}</div>
    </div>

    <h2>{{ trans('handover.pdf.handed_by') }}</h2>
    <div>
        {{ optional($handover->createdBy)->name }}<br>
        {{ trans('handover.pdf.signed_at') }}: {{ $handover->signed_at->format('Y-m-d H:i') }} UTC
        @if($handover->signature_ip)
            <br>{{ trans('handover.pdf.signed_ip') }}: {{ $handover->signature_ip }}
        @endif
    </div>
</body>
</html>
```

- [ ] **Step 2: Create the job**

Create `app/Jobs/GenerateHandoverPdf.php`:
```php
<?php

namespace App\Jobs;

use App\Mail\HandoverSigned;
use App\Models\Handover;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class GenerateHandoverPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public string $handoverId) {}

    public function handle(): void
    {
        $handover = Handover::with(['assets.model', 'createdBy'])->findOrFail($this->handoverId);
        $disk = config('handover.disk');

        $signatureBytes = Storage::disk($disk)->get($handover->signature_path);
        $signatureBase64 = base64_encode($signatureBytes);

        $pdf = Pdf::loadView('pdf.handover', [
            'handover' => $handover,
            'signatureBase64' => $signatureBase64,
            'companyName' => config('handover.company.name'),
        ])->setPaper(
            config('handover.pdf.paper'),
            config('handover.pdf.orientation'),
        );

        $pdfPath = "handovers/{$handover->id}/handover.pdf";
        Storage::disk($disk)->put($pdfPath, $pdf->output());

        $handover->update(['pdf_path' => $pdfPath]);

        if ($handover->recipient_email) {
            Mail::to($handover->recipient_email)
                ->bcc(optional($handover->createdBy)->email)
                ->send(new HandoverSigned($handover->id));
        }
    }
}
```

- [ ] **Step 3: Dispatch the job from the service**

Edit `app/Services/HandoverService.php`. At the bottom of the `commit()` method, after the foreach loop and `return $handover;`, change to:
```php
        }

        \App\Jobs\GenerateHandoverPdf::dispatch($handover->id)
            ->afterCommit();

        return $handover;
    } catch (\Throwable $e) {
        Storage::disk($disk)->delete($signaturePath);
        throw $e;
    }
});
```

(`afterCommit()` guarantees the job only fires once the transaction is fully committed — important so the queue worker can read the Handover row.)

- [ ] **Step 4: Create the PDF test**

Create `tests/Feature/Handover/HandoverPdfTest.php`:
```php
<?php

namespace Tests\Feature\Handover;

use App\DataObjects\HandoverData;
use App\Enums\AssetState;
use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Jobs\GenerateHandoverPdf;
use App\Models\Asset;
use App\Models\User;
use App\Services\HandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HandoverPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_commit_dispatches_pdf_job_and_writes_pdf_file(): void
    {
        Storage::fake('local');
        Mail::fake();

        $recipient = User::factory()->create();
        $manager = User::factory()->create();
        $asset = Asset::factory()->create([
            'state' => AssetState::STORAGE->value,
            'serial_number' => 'SN-TEST-12345',
        ]);

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipient->name,
            recipientEmail: $recipient->email,
            assetIds: [$asset->id],
            accessories: 'charger',
            conditionNotes: null,
            termsText: 'Terms-XYZ-snapshot',
            signaturePngBase64: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
            signatureIp: '127.0.0.1',
            signatureUserAgent: 'phpunit',
            createdById: $manager->id,
        );

        $handover = app(HandoverService::class)->commit($data);

        // QUEUE_CONNECTION=sync in phpunit.xml, so the job ran inline.
        $handover->refresh();
        $this->assertNotNull($handover->pdf_path);
        Storage::disk('local')->assertExists($handover->pdf_path);

        $bytes = Storage::disk('local')->get($handover->pdf_path);
        $this->assertSame('%PDF', substr($bytes, 0, 4));
    }
}
```

- [ ] **Step 5: Run the test**

Run:
```bash
ddev exec php artisan test --filter=HandoverPdfTest
```

Expected: PASS. (Sync queue means the job executes inline during `commit()`. The job retrieves the Handover row — `afterCommit()` ensures it sees committed state.)

- [ ] **Step 6: Commit**

```bash
git add app/Jobs/GenerateHandoverPdf.php resources/views/pdf/handover.blade.php app/Services/HandoverService.php tests/Feature/Handover/HandoverPdfTest.php
git commit -m "feat(handover): generate PDF on commit via queued job"
```

---

## Task 14: `HandoverSigned` mailable + email test

**Files:**
- Create: `app/Mail/HandoverSigned.php`
- Create: `resources/views/emails/handover-signed.blade.php`
- Create: `tests/Feature/Handover/HandoverEmailTest.php`

- [ ] **Step 1: Create the mailable**

Create `app/Mail/HandoverSigned.php`:
```php
<?php

namespace App\Mail;

use App\Models\Handover;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class HandoverSigned extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Handover $handover;

    public function __construct(public string $handoverId)
    {
        $this->handover = Handover::with(['assets', 'createdBy'])->findOrFail($handoverId);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans('handover.mail.subject', ['type' => $this->handover->type->getLabel()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.handover-signed',
            with: [
                'handover' => $this->handover,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if (! $this->handover->pdf_path) {
            return [];
        }

        $disk = config('handover.disk');
        $bytes = Storage::disk($disk)->get($this->handover->pdf_path);

        return [
            Attachment::fromData(fn () => $bytes, 'handover.pdf')
                ->withMime('application/pdf'),
        ];
    }

    public function build(): self
    {
        // Mark email_sent_at on every successful send. Mailable runs after the
        // mail driver returns; failures bubble up before this line.
        $this->afterCommit();
        $this->handover->update(['email_sent_at' => now()]);
        return $this;
    }
}
```

- [ ] **Step 2: Create the email Blade view**

Create `resources/views/emails/handover-signed.blade.php`:
```blade
<p>{{ trans('handover.mail.intro', ['name' => $handover->recipient_name]) }}</p>

<p>{{ trans('handover.mail.body', ['date' => $handover->signed_at->format('Y-m-d')]) }}</p>

<ul>
@foreach($handover->assets as $asset)
    <li>{{ optional($asset->model)->name }} — {{ $asset->serial_number }}</li>
@endforeach
</ul>

<p>{{ trans('handover.mail.outro') }}</p>
```

- [ ] **Step 3: Create the email test**

Create `tests/Feature/Handover/HandoverEmailTest.php`:
```php
<?php

namespace Tests\Feature\Handover;

use App\DataObjects\HandoverData;
use App\Enums\AssetState;
use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Mail\HandoverSigned;
use App\Models\Asset;
use App\Models\User;
use App\Services\HandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HandoverEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_is_sent_with_pdf_attachment_when_recipient_has_email(): void
    {
        Storage::fake('local');
        Mail::fake();

        $recipient = User::factory()->create(['email' => 'alice@example.com']);
        $manager = User::factory()->create();
        $asset = Asset::factory()->create(['state' => AssetState::STORAGE->value]);

        app(HandoverService::class)->commit(new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipient->name,
            recipientEmail: 'alice@example.com',
            assetIds: [$asset->id],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms',
            signaturePngBase64: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
            signatureIp: null,
            signatureUserAgent: null,
            createdById: $manager->id,
        ));

        Mail::assertSent(HandoverSigned::class, function (HandoverSigned $mail): bool {
            return $mail->hasTo('alice@example.com');
        });
    }

    public function test_email_is_skipped_when_recipient_email_is_null(): void
    {
        Storage::fake('local');
        Mail::fake();

        $manager = User::factory()->create();
        $asset = Asset::factory()->create(['state' => AssetState::STORAGE->value]);

        app(HandoverService::class)->commit(new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::EXTERNAL,
            recipientUserId: null,
            recipientName: 'Walk-in',
            recipientEmail: null,
            assetIds: [$asset->id],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms',
            signaturePngBase64: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
            signatureIp: null,
            signatureUserAgent: null,
            createdById: $manager->id,
        ));

        Mail::assertNothingSent();
    }
}
```

- [ ] **Step 4: Run the test**

Run:
```bash
ddev exec php artisan test --filter=HandoverEmailTest
```

Expected: both tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Mail/HandoverSigned.php resources/views/emails/handover-signed.blade.php tests/Feature/Handover/HandoverEmailTest.php
git commit -m "feat(handover): email signed handover PDF to recipient"
```

---

## Task 15: Signature pad Blade component (Alpine)

**Files:**
- Create: `resources/views/components/handover/signature-pad.blade.php`

- [ ] **Step 1: Create the signature-pad Blade component**

Create `resources/views/components/handover/signature-pad.blade.php`:
```blade
@props([
    'statePath',
    'width' => 600,
    'height' => 200,
    'clearLabel' => __('handover.sign.clear'),
])

<div
    x-data="{
        drawing: false,
        ctx: null,
        canvas: null,
        last: null,
        stroked: false,
        init() {
            this.canvas = this.$refs.canvas;
            this.ctx = this.canvas.getContext('2d');
            this.ctx.lineCap = 'round';
            this.ctx.lineJoin = 'round';
            this.ctx.lineWidth = 2;
            this.ctx.strokeStyle = '#111';

            const fill = () => {
                this.ctx.fillStyle = '#fff';
                this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
                this.persist();
            };
            fill();
        },
        pointerDown(ev) {
            this.drawing = true;
            this.stroked = true;
            const r = this.canvas.getBoundingClientRect();
            this.last = { x: ev.clientX - r.left, y: ev.clientY - r.top };
        },
        pointerMove(ev) {
            if (!this.drawing) return;
            const r = this.canvas.getBoundingClientRect();
            const next = { x: ev.clientX - r.left, y: ev.clientY - r.top };
            this.ctx.beginPath();
            this.ctx.moveTo(this.last.x, this.last.y);
            this.ctx.lineTo(next.x, next.y);
            this.ctx.stroke();
            this.last = next;
        },
        pointerUp() {
            if (!this.drawing) return;
            this.drawing = false;
            this.persist();
        },
        clear() {
            this.ctx.fillStyle = '#fff';
            this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
            this.stroked = false;
            this.persist();
        },
        persist() {
            const dataUrl = this.canvas.toDataURL('image/png');
            const base64 = dataUrl.replace(/^data:image\/png;base64,/, '');
            $wire.set(@js($statePath), this.stroked ? base64 : '');
        },
    }"
    class="space-y-2"
>
    <div class="border border-gray-300 rounded inline-block bg-white">
        <canvas
            x-ref="canvas"
            width="{{ $width }}"
            height="{{ $height }}"
            style="touch-action: none; cursor: crosshair; display: block;"
            @pointerdown="pointerDown($event)"
            @pointermove="pointerMove($event)"
            @pointerup="pointerUp()"
            @pointerleave="pointerUp()"
        ></canvas>
    </div>
    <button
        type="button"
        class="text-sm px-3 py-1 border rounded text-gray-700 hover:bg-gray-100"
        @click="clear()"
    >
        {{ $clearLabel }}
    </button>
</div>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/components/handover/signature-pad.blade.php
git commit -m "feat(handover): add Alpine-based signature pad blade component"
```

---

## Task 16: `HandoverWizardAction` — entry point and step 1

**Files:**
- Create: `app/Filament/App/Resources/Handovers/Actions/HandoverWizardAction.php`

- [ ] **Step 1: Create the wizard action skeleton with step 1**

Create `app/Filament/App/Resources/Handovers/Actions/HandoverWizardAction.php`:
```php
<?php

namespace App\Filament\App\Resources\Handovers\Actions;

use App\DataObjects\HandoverData;
use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Models\Asset;
use App\Services\HandoverService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View as ViewField;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class HandoverWizardAction
{
    /**
     * @param array<int, string>|callable $assetIds  asset IDs to pre-fill, or closure returning them
     */
    public static function make(string $name, array|callable $assetIds = []): Action
    {
        return Action::make($name)
            ->label(trans('handover.action.handover'))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->modalWidth('5xl')
            ->fillForm(function () use ($assetIds): array {
                $ids = is_callable($assetIds) ? $assetIds() : $assetIds;
                return [
                    'asset_ids' => array_values(array_filter($ids)),
                    'type' => HandoverType::ISSUE->value,
                    'recipient_kind' => RecipientKind::INTERNAL->value,
                    'terms_text' => (string) config('handover.terms'),
                    'signature_png' => '',
                ];
            })
            ->form([
                Wizard::make([
                    self::stepType(),
                    self::stepRecipient(),
                    self::stepDetails(),
                    self::stepSign(),
                ])
                ->statePath('data')
                ->submitAction(new HtmlString('<button type="submit" class="filament-button">'
                    . e(trans('handover.sign.submit_with_email')) . '</button>')),
            ])
            ->action(function (array $data): void {
                self::commit($data);
            })
            ->modalSubmitAction(false);  // Wizard's own submit handles dispatch
    }

    protected static function stepType(): Step
    {
        return Step::make(trans('handover.wizard.step.type'))
            ->schema([
                Radio::make('type')
                    ->options(collect(HandoverType::cases())->mapWithKeys(
                        fn (HandoverType $t) => [$t->value => $t->getLabel()]
                    )->all())
                    ->required()
                    ->live(),

                Select::make('asset_ids')
                    ->label(trans('handover.list.column.asset_count'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(function (callable $get): array {
                        $type = HandoverType::tryFrom((string) $get('type'));
                        if ($type === null) {
                            return [];
                        }
                        $allowed = array_map(fn ($s) => $s->value, $type->allowedStateFrom());
                        return Asset::query()
                            ->whereIn('state', $allowed)
                            ->with('model')
                            ->limit(200)
                            ->get()
                            ->mapWithKeys(fn (Asset $a) => [
                                $a->id => trim((optional($a->model)->name ?? '') . ' — ' . ($a->serial_number ?? $a->id)),
                            ])
                            ->all();
                    })
                    ->required()
                    ->rules(['array', 'min:1']),
            ]);
    }

    protected static function stepRecipient(): Step
    {
        return Step::make(trans('handover.wizard.step.recipient'))
            ->schema([]); // filled in next task
    }

    protected static function stepDetails(): Step
    {
        return Step::make(trans('handover.wizard.step.details'))
            ->schema([]);
    }

    protected static function stepSign(): Step
    {
        return Step::make(trans('handover.wizard.step.sign'))
            ->schema([]);
    }

    /** @param array<string, mixed> $data */
    protected static function commit(array $data): void
    {
        // filled in once all steps are complete
    }
}
```

- [ ] **Step 2: Commit (intermediate state)**

```bash
git add app/Filament/App/Resources/Handovers/Actions/HandoverWizardAction.php
git commit -m "feat(handover): scaffold wizard action with step 1 (type & assets)"
```

---

## Task 17: Wizard — step 2 (recipient) and step 3 (details)

**Files:**
- Modify: `app/Filament/App/Resources/Handovers/Actions/HandoverWizardAction.php`

- [ ] **Step 1: Fill in step 2 (Recipient)**

In `HandoverWizardAction`, replace the empty `stepRecipient()` schema with:
```php
return Step::make(trans('handover.wizard.step.recipient'))
    ->schema([
        Radio::make('recipient_kind')
            ->options(collect(RecipientKind::cases())->mapWithKeys(
                fn (RecipientKind $k) => [$k->value => $k->getLabel()]
            )->all())
            ->required()
            ->live(),

        Select::make('recipient_user_id')
            ->label(trans('handover.recipient.select_user'))
            ->options(fn () => \App\Models\User::query()
                ->where('login_enabled', true)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all())
            ->searchable()
            ->required()
            ->visible(fn (callable $get) => $get('recipient_kind') === RecipientKind::INTERNAL->value)
            ->live()
            ->afterStateUpdated(function (callable $set, ?string $state): void {
                if ($state === null) {
                    return;
                }
                $u = \App\Models\User::find($state);
                if ($u) {
                    $set('recipient_name', (string) $u->name);
                    $set('recipient_email', (string) $u->email);
                }
            }),

        TextInput::make('recipient_name')
            ->label(trans('handover.recipient.name'))
            ->maxLength(255)
            ->required(),

        TextInput::make('recipient_email')
            ->label(trans('handover.recipient.email'))
            ->email()
            ->maxLength(255)
            ->nullable(),
    ]);
```

- [ ] **Step 2: Fill in step 3 (Details)**

Replace the empty `stepDetails()` schema with:
```php
return Step::make(trans('handover.wizard.step.details'))
    ->schema([
        Textarea::make('accessories')
            ->label(trans('handover.form.accessories'))
            ->placeholder(trans('handover.form.accessories_placeholder'))
            ->maxLength(2000)
            ->rows(3),

        Textarea::make('condition_notes')
            ->label(trans('handover.form.condition_notes'))
            ->placeholder(trans('handover.form.condition_notes_placeholder'))
            ->maxLength(2000)
            ->rows(3),

        Placeholder::make('terms_preview')
            ->label(trans('handover.form.terms_header'))
            ->content(fn (callable $get): string => (string) $get('terms_text')),

        Hidden::make('terms_text'),
    ]);
```

- [ ] **Step 3: Commit**

```bash
git add app/Filament/App/Resources/Handovers/Actions/HandoverWizardAction.php
git commit -m "feat(handover): add recipient and details steps to wizard"
```

---

## Task 18: Wizard — step 4 (sign) + commit handler

**Files:**
- Modify: `app/Filament/App/Resources/Handovers/Actions/HandoverWizardAction.php`

- [ ] **Step 1: Fill in step 4 (Sign)**

Replace the empty `stepSign()` schema with:
```php
return Step::make(trans('handover.wizard.step.sign'))
    ->schema([
        Placeholder::make('confirm_recipient')
            ->label(trans('handover.recipient.name'))
            ->content(fn (callable $get): string => (string) $get('recipient_name')),

        ViewField::make('signature_png')
            ->view('components.handover.signature-pad', [
                'width'  => config('handover.signature.width'),
                'height' => config('handover.signature.height'),
            ])
            ->required()
            ->rules(['required', 'string', 'min:1']),
    ]);
```

(The view component receives `$statePath` from the field; the field's name on the form state is `signature_png`. The view's `$statePath` variable is provided by Filament's `ViewField`.)

- [ ] **Step 2: Implement `commit()`**

Replace the empty `commit()` method body with:
```php
$type = HandoverType::from((string) $data['type']);
$recipientKind = RecipientKind::from((string) $data['recipient_kind']);

$data_obj = new HandoverData(
    type: $type,
    recipientKind: $recipientKind,
    recipientUserId: $recipientKind === RecipientKind::INTERNAL ? (string) $data['recipient_user_id'] : null,
    recipientName: (string) $data['recipient_name'],
    recipientEmail: $data['recipient_email'] ?: null,
    assetIds: array_values((array) $data['asset_ids']),
    accessories: $data['accessories'] ?: null,
    conditionNotes: $data['condition_notes'] ?: null,
    termsText: (string) $data['terms_text'],
    signaturePngBase64: (string) $data['signature_png'],
    signatureIp: request()->ip(),
    signatureUserAgent: substr((string) request()->userAgent(), 0, 512),
    createdById: (string) auth()->id(),
);

try {
    $handover = app(HandoverService::class)->commit($data_obj);
} catch (\App\Exceptions\HandoverStateConflictException $e) {
    Notification::make()
        ->danger()
        ->title(trans('handover.notification.state_conflict'))
        ->send();
    return;
}

Notification::make()
    ->success()
    ->title(trans('handover.notification.success'))
    ->send();
```

- [ ] **Step 3: Quick UI smoke run**

Run:
```bash
ddev artisan view:clear && ddev artisan config:clear
```

(Then in the browser, open the Filament `/app` panel. Verify the action button placement comes through in subsequent tasks — no test yet.)

- [ ] **Step 4: Commit**

```bash
git add app/Filament/App/Resources/Handovers/Actions/HandoverWizardAction.php
git commit -m "feat(handover): complete wizard with signature step and commit handler"
```

---

## Task 19: Wire wizard into AssetResource (row, header, bulk)

**Files:**
- Modify: `app/Filament/App/Resources/Assets/Tables/AssetsTable.php`
- Modify: `app/Filament/App/Resources/Assets/Pages/EditAsset.php`

- [ ] **Step 1: Add row + bulk actions on the Assets table**

Edit `app/Filament/App/Resources/Assets/Tables/AssetsTable.php`. In `recordActions([...])`, inside the existing `ActionGroup::make([...])`, add as a sibling of `ReplicateAction::make()`:
```php
\App\Filament\App\Resources\Handovers\Actions\HandoverWizardAction::make(
    'handover',
    fn (Asset $record): array => [$record->id],
),
```

In `toolbarActions([...])`, inside the existing `BulkActionGroup::make([...])`, add as a sibling of `DeleteBulkAction::make()`:
```php
\App\Filament\App\Resources\Handovers\Actions\HandoverWizardAction::make(
    'handover_bulk',
    fn ($livewire) => $livewire->getSelectedTableRecords()->pluck('id')->all(),
)->label(trans('handover.action.bulk')),
```

(Note: `$livewire->getSelectedTableRecords()` is the standard Filament v5 idiom for bulk-action context; the closure runs at `fillForm()` time.)

- [ ] **Step 2: Add header action on the Edit page**

Edit `app/Filament/App/Resources/Assets/Pages/EditAsset.php`. Add to the array returned by `getHeaderActions()`:
```php
\App\Filament\App\Resources\Handovers\Actions\HandoverWizardAction::make(
    'handover_header',
    fn () => [$this->record->id],
),
```

So the method becomes:
```php
protected function getHeaderActions(): array
{
    return [
        \App\Filament\App\Resources\Handovers\Actions\HandoverWizardAction::make(
            'handover_header',
            fn () => [$this->record->id],
        ),
        DeleteAction::make(),
    ];
}
```

- [ ] **Step 3: Clear view caches**

Run:
```bash
ddev artisan view:clear && ddev artisan config:clear
```

- [ ] **Step 4: Commit**

```bash
git add app/Filament/App/Resources/Assets/Tables/AssetsTable.php app/Filament/App/Resources/Assets/Pages/EditAsset.php
git commit -m "feat(handover): wire wizard into AssetResource row, header and bulk actions"
```

---

## Task 20: `HandoverResource` — list + view pages

**Files:**
- Create: `app/Filament/App/Resources/Handovers/HandoverResource.php`
- Create: `app/Filament/App/Resources/Handovers/Tables/HandoversTable.php`
- Create: `app/Filament/App/Resources/Handovers/Schemas/HandoverInfolist.php`
- Create: `app/Filament/App/Resources/Handovers/Pages/ListHandovers.php`
- Create: `app/Filament/App/Resources/Handovers/Pages/ViewHandover.php`

- [ ] **Step 1: Create `HandoverResource`**

Create `app/Filament/App/Resources/Handovers/HandoverResource.php`:
```php
<?php

namespace App\Filament\App\Resources\Handovers;

use App\Filament\App\Resources\Handovers\Pages\ListHandovers;
use App\Filament\App\Resources\Handovers\Pages\ViewHandover;
use App\Filament\App\Resources\Handovers\Schemas\HandoverInfolist;
use App\Filament\App\Resources\Handovers\Tables\HandoversTable;
use App\Models\Handover;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HandoverResource extends Resource
{
    protected static ?string $model = Handover::class;

    protected static ?string $slug = 'handovers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return trans('handover.nav.group');
    }

    public static function getLabel(): ?string
    {
        return trans('handover.resource.label');
    }

    public static function getPluralLabel(): ?string
    {
        return trans('handover.resource.plural');
    }

    public static function table(Table $table): Table
    {
        return HandoversTable::table($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return HandoverInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHandovers::route('/'),
            'view'  => ViewHandover::route('/{record}'),
        ];
    }

    /** @return array<string, mixed> */
    public static function getRelations(): array
    {
        return [];
    }
}
```

- [ ] **Step 2: Create the list table**

Create `app/Filament/App/Resources/Handovers/Tables/HandoversTable.php`:
```php
<?php

namespace App\Filament\App\Resources\Handovers\Tables;

use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class HandoversTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('signed_at', 'desc')
            ->columns([
                TextColumn::make('signed_at')
                    ->label(trans('handover.list.column.signed_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(trans('handover.list.column.type'))
                    ->badge(),
                TextColumn::make('recipient_name')
                    ->label(trans('handover.list.column.recipient'))
                    ->description(fn ($record) => $record->recipient_kind->getLabel()),
                TextColumn::make('assets_count')
                    ->label(trans('handover.list.column.asset_count'))
                    ->counts('assets')
                    ->numeric(),
                TextColumn::make('createdBy.name')
                    ->label(trans('handover.list.column.created_by'))
                    ->toggleable(),
                TextColumn::make('pdf_path')
                    ->label(trans('handover.list.column.pdf'))
                    ->formatStateUsing(fn (?string $state) => $state
                        ? trans('handover.list.pdf_download')
                        : trans('handover.list.pdf_pending')),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(collect(HandoverType::cases())->mapWithKeys(
                        fn (HandoverType $t) => [$t->value => $t->getLabel()]
                    )->all()),
                SelectFilter::make('recipient_kind')
                    ->options(collect(RecipientKind::cases())->mapWithKeys(
                        fn (RecipientKind $k) => [$k->value => $k->getLabel()]
                    )->all()),
                SelectFilter::make('created_by')
                    ->relationship('createdBy', 'name'),
                Filter::make('signed_at_range')
                    ->schema([
                        DatePicker::make('from')->label(trans('history.filter.from')),
                        DatePicker::make('until')->label(trans('history.filter.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('signed_at', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('signed_at', '<=', $d));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('download_pdf')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedArrowDownTray)
                    ->label(trans('handover.list.pdf_download'))
                    ->visible(fn ($record): bool => $record->pdf_path !== null)
                    ->url(fn ($record): string => URL::temporarySignedRoute(
                        'handover.pdf',
                        now()->addMinutes(5),
                        ['handover' => $record->id],
                    )),
            ])
            ->toolbarActions([]);
    }
}
```

- [ ] **Step 3: Create the infolist (view page schema)**

Create `app/Filament/App/Resources/Handovers/Schemas/HandoverInfolist.php`:
```php
<?php

namespace App\Filament\App\Resources\Handovers\Schemas;

use App\Models\Handover;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class HandoverInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(trans('handover.view.recipient_section'))->schema([
                TextEntry::make('recipient_name')->label(trans('handover.recipient.name')),
                TextEntry::make('recipient_email')->label(trans('handover.recipient.email')),
                TextEntry::make('recipient_kind')->badge(),
                TextEntry::make('type')->badge(),
            ])->columns(2),

            Section::make(trans('handover.view.details_section'))->schema([
                TextEntry::make('accessories')->label(trans('handover.form.accessories')),
                TextEntry::make('condition_notes')->label(trans('handover.form.condition_notes')),
                TextEntry::make('terms_text')->label(trans('handover.form.terms_header')),
            ])->columns(1),

            Section::make(trans('handover.view.assets_section'))->schema([
                RepeatableEntry::make('assets')->schema([
                    TextEntry::make('model.name'),
                    TextEntry::make('serial_number'),
                    TextEntry::make('pivot.state_from'),
                    TextEntry::make('pivot.state_to'),
                ])->columns(4),
            ]),

            Section::make(trans('handover.view.signature_section'))->schema([
                ImageEntry::make('signature_path')
                    ->disk(fn () => config('handover.disk'))
                    ->height(160)
                    ->visibility('private'),
            ]),

            Section::make(trans('handover.view.meta_section'))->schema([
                TextEntry::make('signed_at')->dateTime(),
                TextEntry::make('createdBy.name')->label(trans('handover.pdf.handed_by')),
                TextEntry::make('signature_ip')->label(trans('handover.pdf.signed_ip')),
                TextEntry::make('email_sent_at')->dateTime(),
            ])->columns(2),
        ]);
    }
}
```

- [ ] **Step 4: Create the page classes**

Create `app/Filament/App/Resources/Handovers/Pages/ListHandovers.php`:
```php
<?php

namespace App\Filament\App\Resources\Handovers\Pages;

use App\Filament\App\Resources\Handovers\Actions\HandoverWizardAction;
use App\Filament\App\Resources\Handovers\HandoverResource;
use Filament\Resources\Pages\ListRecords;

class ListHandovers extends ListRecords
{
    protected static string $resource = HandoverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            HandoverWizardAction::make('new_handover', [])
                ->label(trans('handover.action.new')),
        ];
    }
}
```

Create `app/Filament/App/Resources/Handovers/Pages/ViewHandover.php`:
```php
<?php

namespace App\Filament\App\Resources\Handovers\Pages;

use App\Filament\App\Resources\Handovers\HandoverResource;
use Filament\Resources\Pages\ViewRecord;

class ViewHandover extends ViewRecord
{
    protected static string $resource = HandoverResource::class;
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Filament/App/Resources/Handovers
git commit -m "feat(handover): add Handovers list and view resource pages"
```

---

## Task 21: PDF download route

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Add the signed PDF route**

Open `routes/web.php`. Append:
```php
use App\Models\Handover;
use Illuminate\Support\Facades\Storage;

Route::middleware(['signed'])
    ->get('/handover-pdf/{handover}', function (Handover $handover) {
        abort_unless($handover->pdf_path, 404);

        $disk = config('handover.disk');
        return Storage::disk($disk)->download($handover->pdf_path, "handover-{$handover->id}.pdf");
    })
    ->name('handover.pdf');
```

- [ ] **Step 2: Verify the route registers**

Run:
```bash
ddev artisan route:list --name=handover.pdf
```

Expected: one row listing `GET /handover-pdf/{handover}` → `handover.pdf`.

- [ ] **Step 3: Commit**

```bash
git add routes/web.php
git commit -m "feat(handover): add signed PDF download route"
```

---

## Task 22: `HandoverPolicy` + auth-discovery

**Files:**
- Create: `app/Policies/HandoverPolicy.php`
- Create: `tests/Feature/Handover/HandoverPolicyTest.php`

- [ ] **Step 1: Create the policy**

Create `app/Policies/HandoverPolicy.php`:
```php
<?php

namespace App\Policies;

use App\Models\Handover;
use App\Models\User;

class HandoverPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->login_enabled;
    }

    public function view(User $user, Handover $handover): bool
    {
        return (bool) $user->login_enabled;
    }

    public function create(User $user): bool
    {
        return false;  // creation only via service, not via resource form
    }

    public function update(User $user, Handover $handover): bool
    {
        return false;  // immutable
    }

    public function delete(User $user, Handover $handover): bool
    {
        return false;  // immutable
    }
}
```

(Laravel auto-discovers `App\Policies\HandoverPolicy` for `App\Models\Handover` because both live under the conventional namespaces.)

- [ ] **Step 2: Add policy test**

Create `tests/Feature/Handover/HandoverPolicyTest.php`:
```php
<?php

namespace Tests\Feature\Handover;

use App\Models\Handover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandoverPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_user_can_view_any_and_view_single(): void
    {
        $user = User::factory()->create(['login_enabled' => true]);
        $handover = Handover::factory()->create();

        $this->assertTrue($user->can('viewAny', Handover::class));
        $this->assertTrue($user->can('view', $handover));
    }

    public function test_create_update_delete_are_always_denied_from_ui(): void
    {
        $user = User::factory()->create(['login_enabled' => true]);
        $handover = Handover::factory()->create();

        $this->assertFalse($user->can('create', Handover::class));
        $this->assertFalse($user->can('update', $handover));
        $this->assertFalse($user->can('delete', $handover));
    }
}
```

- [ ] **Step 3: Run the test**

Run:
```bash
ddev exec php artisan test --filter=HandoverPolicyTest
```

Expected: both PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Policies/HandoverPolicy.php tests/Feature/Handover/HandoverPolicyTest.php
git commit -m "feat(handover): add HandoverPolicy with view-only rules"
```

---

## Task 23: Integrate `handover_completed` into History summary builder + filter

**Files:**
- Modify: `app/Support/History/SummaryBuilder.php`
- Modify: `app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php`
- Modify: `tests/Unit/History/SummaryBuilderTest.php` (if it exists; otherwise create a new case)

- [ ] **Step 1: Extend `SummaryBuilder` with `handover_completed`**

Edit `app/Support/History/SummaryBuilder.php`. In `bodyFor()`, add a new case **before** the `default` arm:
```php
'handover_completed' => trans('handover.history.summary.handover_completed', [
    'type'      => \App\Enums\HandoverType::tryFrom((string) ($activity->properties['type'] ?? ''))?->getLabel() ?? '',
    'recipient' => (string) ($activity->properties['recipient_name'] ?? ''),
]),
```

- [ ] **Step 2: Extend the event-kind filter on the History relation manager**

Edit `app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php`. In the `SelectFilter::make('event_kind')` `options()` array, add:
```php
'handover_completed' => trans('handover.history.event.handover_completed'),
```

- [ ] **Step 3: Verify history rendering with a quick test**

Append to `tests/Unit/History/SummaryBuilderTest.php` (or create one if missing — mirror the existing audit-log unit-test layout):
```php
public function test_handover_completed_summary_renders_type_and_recipient(): void
{
    $activity = new \Spatie\Activitylog\Models\Activity();
    $activity->description = 'handover_completed';
    $activity->subject_type = \App\Models\Asset::class;
    $activity->subject_id = (string) \Illuminate\Support\Str::uuid();
    $activity->properties = new \Illuminate\Support\Collection([
        'type' => 'issue',
        'recipient_name' => 'Alice',
    ]);

    $out = (new \App\Support\History\SummaryBuilder())->forActivity($activity);
    $this->assertStringContainsString('Alice', $out);
}
```

Run:
```bash
ddev exec php artisan test --filter=SummaryBuilderTest
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Support/History/SummaryBuilder.php app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php tests/Unit/History/SummaryBuilderTest.php
git commit -m "feat(handover): surface handover_completed event in asset history"
```

---

## Task 24: Filament wizard integration test

**Files:**
- Create: `tests/Feature/Filament/HandoverWizardTest.php`

- [ ] **Step 1: Write the test**

Create `tests/Feature/Filament/HandoverWizardTest.php`:
```php
<?php

namespace Tests\Feature\Filament;

use App\Enums\AssetState;
use App\Filament\App\Resources\Handovers\Pages\ListHandovers;
use App\Models\Asset;
use App\Models\Handover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class HandoverWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_wizard_flow_creates_a_handover_and_updates_asset(): void
    {
        Storage::fake('local');
        Mail::fake();

        $manager = User::factory()->create(['login_enabled' => true]);
        $recipient = User::factory()->create(['login_enabled' => true]);
        $this->actingAs($manager);

        $asset = Asset::factory()->create([
            'state'    => AssetState::STORAGE->value,
            'owner_id' => null,
        ]);

        Livewire::test(ListHandovers::class)
            ->callAction('new_handover', data: [
                'type' => \App\Enums\HandoverType::ISSUE->value,
                'asset_ids' => [$asset->id],
                'recipient_kind' => \App\Enums\RecipientKind::INTERNAL->value,
                'recipient_user_id' => $recipient->id,
                'recipient_name' => $recipient->name,
                'recipient_email' => $recipient->email,
                'accessories' => 'charger',
                'condition_notes' => null,
                'terms_text' => 'Snapshot',
                'signature_png' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
            ])
            ->assertSuccessful();

        $asset->refresh();
        $this->assertSame(AssetState::IN_USE, $asset->state);
        $this->assertSame($recipient->id, $asset->owner_id);
        $this->assertSame(1, Handover::count());
    }
}
```

- [ ] **Step 2: Run the test**

Run:
```bash
ddev exec php artisan test --filter=HandoverWizardTest
```

Expected: PASS.

If Filament/Livewire reports it cannot resolve the action by `data:` parameter, this is a v5 idiom change. In that case, adjust to use:
```php
->mountAction('new_handover')
->setActionData([...])
->callMountedAction()
```

Verify against the Filament 5 docs (the project already uses `callAction` in existing tests at `tests/Feature/Filament/HistoryRelationManagerTest.php`; check that file for the working idiom and mirror it).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Filament/HandoverWizardTest.php
git commit -m "test(handover): full wizard flow updates asset and creates handover"
```

---

## Task 25: Final sanity sweep

**Files:** none directly — runs the full suite and a UI smoke pass.

- [ ] **Step 1: Run the full test suite**

Run:
```bash
ddev exec php artisan test
```

Expected: all tests PASS.

- [ ] **Step 2: Lint / format check**

Run:
```bash
ddev exec ./vendor/bin/pint --test
```

If anything fails, run `ddev exec ./vendor/bin/pint` to fix and re-run the test suite.

- [ ] **Step 3: Browser smoke check**

In a browser:
1. Open the Filament `/app` panel.
2. From the **Assets** list, use the row action `Übergabe` on an asset in `STORAGE`. Walk through all four wizard steps. Confirm: success notification, asset is now `IN_USE`, History tab on the asset shows a `Übergabe abgeschlossen` row, the new Handover appears under **Übergaben**, the PDF download link works.
3. From an asset in `IN_USE`, use the row action to do a `RETURN_`. Confirm state goes to `STORAGE`, owner cleared.
4. From the Assets table, multi-select two `STORAGE` assets and run the bulk handover. Confirm both end up assigned to the same recipient with one PDF.
5. From the **Übergaben** page, run "Neue Übergabe" with no pre-fill. Confirm the asset picker is filtered by allowed state.

If any of these fail, file a follow-up task with reproduction steps and revert what was needed.

- [ ] **Step 4: Verify dompdf renders German umlauts correctly**

Open one of the generated PDFs from step 3. Confirm the term text contains correct umlauts (`Übergabeprotokoll`, `Übernommen`). DejaVu Sans is dompdf's default Unicode-capable font; the template already specifies it.

- [ ] **Step 5: Commit any pint fixes (if applicable)**

If `pint` modified any files:
```bash
git add -A
git commit -m "style(handover): apply pint formatting"
```

---

## Self-review

Walking the spec against the plan with fresh eyes:

**Spec coverage:**

| Spec section | Plan tasks |
|---|---|
| Scenarios (issue, lend, return, return_defect) | Task 2 (enum), Task 8 (transitions) |
| Recipient: internal vs external | Task 6 (DTO), Task 10 (external test), Task 17 (UI) |
| Out-of-scope items | Not implemented — verified absent (no return-date field, no auto-incident creation, no per-tenant terms) |
| Data model — `handovers` table | Task 3 |
| Data model — `handover_asset` pivot | Task 3 |
| Snapshot columns | Task 7, Task 10 |
| No soft deletes | Task 3 (no `softDeletes()` call) |
| State transition rules table | Task 2 (enum: `allowedStateFrom`, `stateTo`, `assignsRecipientAsOwner`) |
| Bulk same recipient | Task 6 (single recipient on DTO), Task 19 (bulk action), Task 10 (test) |
| Four entry points | Task 19 (row/header/bulk), Task 20 (standalone page) |
| Wizard 4 steps | Tasks 16, 17, 18 |
| Asset picker filtered by allowed state + user `update` | Task 16 (allowed state filter). NOTE: "scoped to assets user can update" relies on `AssetResource` policies already gating the table view — the picker queries `Asset::query()->whereIn('state', $allowed)`. Since the project does not have an `AssetPolicy` today, this is a pass-through. If a policy is added later, the picker should pick up the scoping via global scopes. |
| Read-only chips for pre-filled | Task 16 — the Select shows pre-filled values; the read-only chip rendering is not exercised in tests, only manually in Task 25 step 3. |
| Acknowledge / terms snapshot | Task 17 (Placeholder + Hidden), Task 7 (stored on Handover) |
| Signature pad: required, 200KB, PNG | Task 12 (validation), Task 15 (pad component) |
| Post-submit notification | Task 18 |
| Asset History integration | Task 11 (write `handover_completed`), Task 23 (render + filter) |
| Capture-flow `commit()` steps | Task 7 (skeleton), Task 9 (rollback), Task 11 (activity), Task 12 (validation), Task 13 (PDF dispatch) |
| `lockForUpdate()` | Task 7 |
| PDF generation queued | Task 13 |
| PDF retry (3 tries) | Task 13 (`$tries = 3`) — banner action on view page is **not yet implemented**. The current view page just shows `pdf_pending` text. ADDING follow-up. |
| Email mailable | Task 14 |
| Storage configurable | Task 1 (config), Task 7 (reads `config('handover.disk')`) |
| Permissions | Task 22 |
| i18n | Task 5 |
| Config | Task 1 |
| Lifecycle edge cases (deleted user / asset) | Task 3 (FK `nullOnDelete`, `cascadeOnDelete`, `restrictOnDelete`) |
| Tests: HandoverServiceTest, HandoverPolicyTest, HandoverWizardTest, HandoverPdfTest, HandoverEmailTest | Tasks 7–14, 22, 24 |

**Gap discovered:** spec says "the retry action re-dispatches the job" on the Handover view page when PDF generation failed. Adding a small follow-up task.

**Placeholders:** none found.

**Type consistency:** `HandoverType::RETURN_` is named with trailing underscore (because `return` is reserved). All references use this form. ✓ `HandoverData::recipientUserId` is `?string` (UUID); `recipientEmail` is `?string`. ✓ `HandoverData::assetIds` is `array<int,string>` everywhere. ✓ `assignsRecipientAsOwner()` used consistently. ✓ `stateTo()` used consistently. ✓

---

## Task 26 (added during self-review): PDF retry action on Handover view page

**Files:**
- Modify: `app/Filament/App/Resources/Handovers/Pages/ViewHandover.php`

- [ ] **Step 1: Add a header action that re-dispatches the job when `pdf_path` is null**

Replace `app/Filament/App/Resources/Handovers/Pages/ViewHandover.php` with:
```php
<?php

namespace App\Filament\App\Resources\Handovers\Pages;

use App\Filament\App\Resources\Handovers\HandoverResource;
use App\Jobs\GenerateHandoverPdf;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewHandover extends ViewRecord
{
    protected static string $resource = HandoverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry_pdf')
                ->label(trans('handover.notification.pdf_failed'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->visible(fn (): bool => $this->record->pdf_path === null)
                ->action(function (): void {
                    GenerateHandoverPdf::dispatch($this->record->id);
                    Notification::make()
                        ->success()
                        ->title(trans('handover.notification.success'))
                        ->send();
                }),
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Filament/App/Resources/Handovers/Pages/ViewHandover.php
git commit -m "feat(handover): allow manual PDF retry from view page when generation failed"
```

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-11-checkout-handover.md`. Two execution options:

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks, fast iteration
2. **Inline Execution** — execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
