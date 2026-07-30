# Inertia Migration — Asset History (4d) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fill the asset detail page's History tab with a read-only, newest-first timeline of the asset's spatie activity-log entries — who, when, event, and field-level old→new changes with enum labels + FK names resolved.

**Architecture:** A testable `App\Support\Assets\AssetHistory::for($asset)` helper queries the `Activity` model by subject (there is no `activities()` relation), resolves the causer name and each changed field's value, and returns a view-model array. `AssetController@show` adds a `history` prop. A read-only `asset-history.tsx` renders the timeline in the last empty tab.

**Tech Stack:** Laravel 13/PHP 8.4, spatie/laravel-activitylog, Inertia v2 + React 19, shadcn/ui, PHPUnit, Vitest. ddev; pnpm.

## Global Constraints

- ddev for all commands; **PHP tests are PHPUnit**; JS tests are **Vitest**.
- Do NOT touch Filament (`/app-old`), the `Asset` model / `LogsActivity` config, or the DB schema. Read-only feature.
- New file `app/Support/Assets/AssetHistory.php`; modify `app/Http/Controllers/App/AssetController.php` (`show()` adds `history`). New React file `resources/js/components/assets/asset-history.tsx`; modify `resources/js/pages/assets/show.tsx`. `@/` → `resources/js/*`.
- Activities are fetched from `\Spatie\Activitylog\Models\Activity` by `subject_type = $asset->getMorphClass()` + `subject_id = $asset->id`, `->with('causer')`, `->latest()`. `properties` holds `attributes` (new) + `old` (previous). Causer = auth user (config `default_auth_driver: null`), so `actingAs($user)` + a model change sets the causer.
- Logged fields (label map): `state`→State, `asset_type_id`→Asset type, `model_id`→Model, `owner_id`→Owner, `place_id`→Place, `serial_number`→Serial number, `buy_date`→Buy date, `buy_type`→Buy type, `buy_price`→Buy price, `guarantee_end`→Guarantee end.
- Value resolution: `state`→`AssetState` label, `buy_type`→`BuyType` label, the four FK ids→referenced record `name` (fallback `"(removed)"`), else raw string. causer: null id → `"System"`, missing user → `"Former user"`, else name.
- No policy (via the existing `auth`-gated show route). TDD for backend; commit per task.

---

### Task 1: `AssetHistory` helper + show history prop

**Files:**
- Create: `app/Support/Assets/AssetHistory.php`
- Modify: `app/Http/Controllers/App/AssetController.php` (`show()`)
- Test: `tests/Feature/App/AssetHistoryTest.php`

**Interfaces:**
- Consumes: `Spatie\Activitylog\Models\Activity`, `AssetState`, `BuyType`, `AssetType`/`AssetModel`/`User`/`Place`.
- Produces: `AssetHistory::for(Asset $asset): array` — newest-first list of `{ id, event, event_label, causer_name, created_at, changes: [{field,label,old,new}] }`. `show` gains a `history` prop = `AssetHistory::for($asset)`.

- [ ] **Step 1: Write the failing tests**

```php
<?php // tests/Feature/App/AssetHistoryTest.php
namespace Tests\Feature\App;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\User;
use App\Support\Assets\AssetHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_owner_change_to_names_and_records_causer(): void
    {
        $actor = User::factory()->create(['name' => 'Cara Causer', 'login_enabled' => true]);
        $oldOwner = User::factory()->create(['name' => 'Olive Old']);
        $newOwner = User::factory()->create(['name' => 'Nate New']);

        $this->actingAs($actor);
        $asset = Asset::factory()->create(['owner_id' => $oldOwner->id, 'state' => AssetState::NEW->value]);
        $asset->update(['owner_id' => $newOwner->id]);

        $history = AssetHistory::for($asset->fresh());

        // newest first: the update entry is first
        $updated = $history[0];
        $this->assertSame('updated', $updated['event']);
        $this->assertSame('Cara Causer', $updated['causer_name']);
        $ownerChange = collect($updated['changes'])->firstWhere('field', 'owner_id');
        $this->assertNotNull($ownerChange);
        $this->assertSame('Olive Old', $ownerChange['old']);
        $this->assertSame('Nate New', $ownerChange['new']);
    }

    public function test_resolves_enum_state_labels(): void
    {
        $actor = User::factory()->create(['login_enabled' => true]);
        $this->actingAs($actor);
        $asset = Asset::factory()->create(['state' => AssetState::NEW->value]);
        $asset->update(['state' => AssetState::DEFECT->value]);

        $stateChange = collect(AssetHistory::for($asset->fresh())[0]['changes'])->firstWhere('field', 'state');
        $this->assertSame(AssetState::NEW->getLabel(), $stateChange['old']);   // 'Neu'
        $this->assertSame(AssetState::DEFECT->getLabel(), $stateChange['new']); // 'Defekt'
    }

    public function test_missing_referenced_record_resolves_to_removed(): void
    {
        $actor = User::factory()->create(['login_enabled' => true]);
        $gone = User::factory()->create();
        $this->actingAs($actor);
        $asset = Asset::factory()->create(['owner_id' => null]);
        $asset->update(['owner_id' => $gone->id]);
        $gone->delete();

        $ownerChange = collect(AssetHistory::for($asset->fresh())[0]['changes'])->firstWhere('field', 'owner_id');
        $this->assertSame('(removed)', $ownerChange['new']);
    }

    public function test_null_causer_shows_system(): void
    {
        // No actingAs -> no auth user -> null causer.
        $asset = Asset::factory()->create();
        $asset->update(['state' => AssetState::STORAGE->value]);

        $entry = AssetHistory::for($asset->fresh())[0];
        $this->assertSame('System', $entry['causer_name']);
    }

    public function test_show_returns_history_prop(): void
    {
        $actor = User::factory()->create(['login_enabled' => true]);
        $this->actingAs($actor);
        $asset = Asset::factory()->create();
        $asset->update(['state' => AssetState::LEND->value]);

        $this->get("/app/assets/{$asset->id}")
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p
                ->component('assets/show')
                ->has('history', fn ($h) => $h->has('0.event')->has('0.causer_name')->has('0.changes')->etc())
                ->etc());
    }
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AssetHistoryTest.php`
Expected: FAIL — `App\Support\Assets\AssetHistory` not found; show lacks `history`.

- [ ] **Step 3: Implement the helper**

```php
<?php // app/Support/Assets/AssetHistory.php
namespace App\Support\Assets;

use App\Enums\AssetState;
use App\Enums\BuyType;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\AssetType;
use App\Models\Place;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class AssetHistory
{
    /** @var array<string, string> */
    private const LABELS = [
        'state' => 'State',
        'asset_type_id' => 'Asset type',
        'model_id' => 'Model',
        'owner_id' => 'Owner',
        'place_id' => 'Place',
        'serial_number' => 'Serial number',
        'buy_date' => 'Buy date',
        'buy_type' => 'Buy type',
        'buy_price' => 'Buy price',
        'guarantee_end' => 'Guarantee end',
    ];

    /** @return array<int, array<string, mixed>> */
    public static function for(Asset $asset): array
    {
        return Activity::query()
            ->where('subject_type', $asset->getMorphClass())
            ->where('subject_id', $asset->id)
            ->with('causer')
            ->latest()
            ->get()
            ->map(fn (Activity $a) => self::entry($a))
            ->all();
    }

    /** @return array<string, mixed> */
    private static function entry(Activity $a): array
    {
        $new = (array) data_get($a->properties, 'attributes', []);
        $old = (array) data_get($a->properties, 'old', []);

        $changes = [];
        foreach ($new as $field => $value) {
            if (! array_key_exists($field, self::LABELS)) {
                continue;
            }
            $changes[] = [
                'field' => $field,
                'label' => self::LABELS[$field],
                'old' => self::resolve($field, $old[$field] ?? null),
                'new' => self::resolve($field, $value),
            ];
        }

        return [
            'id' => $a->id,
            'event' => $a->event,
            'event_label' => $a->event ? ucfirst($a->event) : ($a->description ?: '—'),
            'causer_name' => self::causer($a),
            'created_at' => optional($a->created_at)->toDateTimeString(),
            'changes' => $changes,
        ];
    }

    private static function causer(Activity $a): string
    {
        if ($a->causer_id === null) {
            return 'System';
        }

        return $a->causer?->name ?? 'Former user';
    }

    private static function resolve(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'state' => AssetState::tryFrom((string) $value)?->getLabel() ?? (string) $value,
            'buy_type' => BuyType::tryFrom((string) $value)?->getLabel() ?? (string) $value,
            'asset_type_id' => AssetType::find($value)?->name ?? '(removed)',
            'model_id' => AssetModel::find($value)?->name ?? '(removed)',
            'owner_id' => User::find($value)?->name ?? '(removed)',
            'place_id' => Place::find($value)?->name ?? '(removed)',
            default => (string) $value,
        };
    }
}
```

- [ ] **Step 4: Add the `history` prop to `AssetController@show`**

In `app/Http/Controllers/App/AssetController.php`, add `use App\Support\Assets\AssetHistory;` at the top, and add the `history` key to the `Inertia::render('assets/show', [...])` array in `show()` (leave the existing `->load(...)` and the `asset`/`attachments`/`attachmentCategoryOptions`/`incidents` props unchanged):

```php
'history' => AssetHistory::for($asset),
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AssetHistoryTest.php`
Expected: PASS (5). Then full suite `ddev exec ./vendor/bin/phpunit` → green.

- [ ] **Step 6: Commit**

```bash
git add app/Support/Assets/AssetHistory.php app/Http/Controllers/App/AssetController.php tests/Feature/App/AssetHistoryTest.php
git commit -m "feat(assets): AssetHistory helper + show history prop (activity timeline)"
```

---

### Task 2: Frontend — history timeline panel

**Files:**
- Create: `resources/js/components/assets/asset-history.tsx`
- Modify: `resources/js/pages/assets/show.tsx`
- Test: `resources/js/components/assets/__tests__/asset-history.test.tsx`

**Interfaces:**
- Consumes: shadcn `Badge`/`Card`, `AppLayout` (via show); the `history` prop from Task 1.
- Produces: `AssetHistory` React component (`{ history: HistoryEntry[] }`); wired into `show.tsx` History tab.

- [ ] **Step 1: Write the failing test**

```tsx
// resources/js/components/assets/__tests__/asset-history.test.tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { AssetHistory } from '../asset-history';

const entries = [
    { id: 2, event: 'updated', event_label: 'Updated', causer_name: 'Cara Causer', created_at: '2025-02-01 10:00:00',
      changes: [{ field: 'owner_id', label: 'Owner', old: 'Olive Old', new: 'Nate New' }] },
    { id: 1, event: 'created', event_label: 'Created', causer_name: 'System', created_at: '2025-01-01 09:00:00', changes: [] },
];

describe('AssetHistory', () => {
    it('renders entries with event label, causer and changes', () => {
        render(<AssetHistory history={entries} />);
        expect(screen.getByText('Updated')).toBeInTheDocument();
        expect(screen.getByText('Cara Causer')).toBeInTheDocument();
        expect(screen.getByText('Owner')).toBeInTheDocument();
        expect(screen.getByText(/Olive Old/)).toBeInTheDocument();
        expect(screen.getByText(/Nate New/)).toBeInTheDocument();
    });

    it('shows the causer for a system entry', () => {
        render(<AssetHistory history={entries} />);
        expect(screen.getByText('System')).toBeInTheDocument();
    });

    it('shows an empty state when there is no history', () => {
        render(<AssetHistory history={[]} />);
        expect(screen.getByText(/no history/i)).toBeInTheDocument();
    });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev exec pnpm exec vitest run resources/js/components/assets/__tests__/asset-history.test.tsx`
Expected: FAIL — module missing.

- [ ] **Step 3: Implement the panel**

```tsx
// resources/js/components/assets/asset-history.tsx
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

export interface HistoryChange { field: string; label: string; old: string | null; new: string | null; }
export interface HistoryEntry {
    id: number; event: string; event_label: string; causer_name: string;
    created_at: string | null; changes: HistoryChange[];
}

const EVENT_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    created: 'default', updated: 'secondary', deleted: 'destructive',
};

export function AssetHistory({ history }: { history: HistoryEntry[] }) {
    if (history.length === 0) {
        return <p className="text-sm text-muted-foreground">No history yet.</p>;
    }

    return (
        <div className="space-y-3">
            {history.map((e) => (
                <Card key={e.id}>
                    <CardContent className="py-4">
                        <div className="flex items-center gap-3 text-sm">
                            <Badge variant={EVENT_VARIANT[e.event] ?? 'outline'}>{e.event_label}</Badge>
                            <span className="font-medium">{e.causer_name}</span>
                            <span className="text-xs text-muted-foreground">{e.created_at ?? ''}</span>
                        </div>
                        {e.changes.length > 0 && (
                            <ul className="mt-2 space-y-1 text-sm">
                                {e.changes.map((c) => (
                                    <li key={c.field} className="text-muted-foreground">
                                        <span className="font-medium text-foreground">{c.label}:</span>{' '}
                                        <span>{c.old ?? '—'}</span>
                                        <span className="mx-1">→</span>
                                        <span className="text-foreground">{c.new ?? '—'}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `ddev exec pnpm exec vitest run resources/js/components/assets/__tests__/asset-history.test.tsx`
Expected: PASS (3).

- [ ] **Step 5: Wire into `show.tsx`**

In `resources/js/pages/assets/show.tsx`:
- Import `{ AssetHistory, type HistoryEntry }` from `@/components/assets/asset-history`.
- Add `history: HistoryEntry[]` to the page `Props` interface and destructure it:
  `export default function ShowAsset({ asset, attachments, attachmentCategoryOptions, incidents, history }: Props)`.
- Replace the History tab body:

```tsx
<TabsContent value="history" className="mt-4">
    <AssetHistory history={history} />
</TabsContent>
```

(The `Placeholder` component may now be unused — remove it if so to keep the file clean; all three panel tabs are filled.)

- [ ] **Step 6: Build + full JS suite**

Run: `ddev exec pnpm run build` → succeeds, no type errors (remove the now-unused `Placeholder` if the build warns/errors on it).
Run: `ddev exec pnpm run test` → green.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/assets/asset-history.tsx resources/js/pages/assets/show.tsx resources/js/components/assets/__tests__/asset-history.test.tsx
git commit -m "feat(assets): read-only history timeline in detail page"
```

---

### Task 3: Final verification

**Files:** none (verification only).

- [ ] **Step 1: Full PHP suite** — `ddev exec php artisan test` → green.
- [ ] **Step 2: Full JS suite** — `ddev exec pnpm run test` → green.
- [ ] **Step 3: Build** — `ddev exec pnpm run build` → succeeds.
- [ ] **Step 4: Manual verify** — on an asset detail page, change some fields (owner/state) and reload: the History tab shows newest-first entries with your name, the event, and `Owner: X → Y` / `State: Neu → Defekt` with resolved names/labels; a change is shown even after the referenced record is deleted ("(removed)"). `/app-old` Filament history still works.
- [ ] **Step 5:** Commit anything outstanding (releases automated — skip manual CHANGELOG).

---

## Self-Review Notes

- **Spec coverage:** `AssetHistory` (source query, causer, changes, enum/FK resolution) → Task 1; show `history` prop → Task 1 Step 4; timeline panel + wiring → Task 2; tests → Task 1 (PHPUnit) + Task 2 (Vitest) + Task 3. All spec sections covered.
- **Type consistency:** `HistoryEntry`/`HistoryChange` (Task 2) match the backend entry shape (Task 1 `entry()`); `AssetHistory` React props match the show wiring.
- **Correctness:** activities fetched from the `Activity` model by subject (no relation); `properties` read via `data_get` (robust for Collection/array); causer null→System, missing→Former user; enum via `tryFrom`, FK via `find(...)?->name ?? '(removed)'`. Read-only — no model/schema/config changes.
- **Deferred (per spec):** add-note, semantic events, Summary, handover events, incident-log entries, pagination.
