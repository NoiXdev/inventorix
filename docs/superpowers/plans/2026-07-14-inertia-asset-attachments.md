# Inertia Migration — Asset Attachments (4b) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fill the asset detail page's Attachments tab — list an asset's attachments (image thumbnails + rows), upload multiple files with optional title/category/note, and delete — reusing the existing attachment backend.

**Architecture:** `AssetController@show` gains an `attachments` prop (+ `attachmentCategoryOptions`), each row carrying `url = route('attachments.open', …)` reused for both the "Open" link and image `<img src>`. A new asset-scoped `AssetAttachmentController` (store/destroy) creates one `Attachment` per uploaded file on the default disk and deletes via the existing observer. The frontend panel uploads with Inertia `forceFormData` and re-renders from fresh `show` props.

**Tech Stack:** Laravel 13/PHP 8.4, Inertia v2 + React 19, shadcn/ui, PHPUnit (`Storage::fake()`), Vitest. ddev; pnpm.

## Global Constraints

- ddev for all commands; **PHP tests are PHPUnit**; JS tests are **Vitest**.
- Do NOT touch Filament (`/app-old`), the `Attachment` model/observer/migration, the `HasAttachments` concern, or the existing `attachments.open` route. No DB schema changes.
- New controller `App\Http\Controllers\App\AssetAttachmentController`; request `App\Http\Requests\App\AssetAttachmentRequest`. Routes in the authenticated `/app` group. New React file `resources/js/components/assets/asset-attachments.tsx`; modify `resources/js/pages/assets/show.tsx`. `@/` → `resources/js/*`.
- Uploads/reads use the app **default** filesystem disk (`$file->store('attachments')`, `Storage::disk()`), matching the observer + open route.
- Attachment endpoints are **asset-scoped**. No policy (gate on `auth`). Metadata set at upload only. Accepted mimes: jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,mp4,mov,webm; max 51200 KB (~50 MB).
- `Attachment`: fillable `path, original_name, mime_type, size, type, category, title, note, uploaded_by`; `detectType($mime)`; `category` cast `AttachmentCategory`. `Asset::attachments()` morphMany latest. `AttachmentFactory` defaults to an Asset attachable, pdf/document.
- TDD for backend; commit after every task.

---

### Task 1: Backend — show attachments prop + upload/delete endpoints

**Files:**
- Modify: `app/Http/Controllers/App/AssetController.php` (`show()` + a `humanSize` helper)
- Create: `app/Http/Controllers/App/AssetAttachmentController.php`, `app/Http/Requests/App/AssetAttachmentRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/App/AssetAttachmentControllerTest.php`, and extend `tests/Feature/App/AssetControllerTest.php` (show prop)

**Interfaces:**
- Consumes: `Asset::attachments()`, `Attachment` (+ `detectType`), `AttachmentCategory`, existing `attachments.open` route.
- Produces: routes `app.assets.attachments.store` (POST `/app/assets/{asset}/attachments`), `app.assets.attachments.destroy` (DELETE `/app/assets/{asset}/attachments/{attachment}`). `show` props gain `attachments: [{id,type,category_label,title,original_name,size,size_label,uploaded_by_name,created_at,url}]` and `attachmentCategoryOptions: [{value,label}]`.

- [ ] **Step 1: Write the failing tests**

```php
<?php // tests/Feature/App/AssetAttachmentControllerTest.php
namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetAttachmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User { return User::factory()->create(['login_enabled' => true]); }

    public function test_upload_creates_attachment_rows_and_stores_files(): void
    {
        Storage::fake();
        $asset = Asset::factory()->create();
        $actor = $this->actor();

        $this->actingAs($actor)->post("/app/assets/{$asset->id}/attachments", [
            'files' => [
                UploadedFile::fake()->image('photo.jpg'),
                UploadedFile::fake()->create('manual.pdf', 200, 'application/pdf'),
            ],
            'category' => 'dokument',
            'title' => 'Docs',
        ])->assertRedirect();

        $this->assertSame(2, $asset->attachments()->count());
        $image = $asset->attachments()->where('original_name', 'photo.jpg')->first();
        $this->assertSame('image', $image->type);
        $this->assertSame($actor->id, $image->uploaded_by);
        Storage::disk()->assertExists($image->path);
    }

    public function test_upload_requires_at_least_one_file(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/attachments", ['files' => []])
            ->assertSessionHasErrors('files');
    }

    public function test_upload_rejects_bad_category(): void
    {
        Storage::fake();
        $asset = Asset::factory()->create();
        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/attachments", [
            'files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
            'category' => 'bogus',
        ])->assertSessionHasErrors('category');
    }

    public function test_upload_rejects_oversized_file(): void
    {
        Storage::fake();
        $asset = Asset::factory()->create();
        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/attachments", [
            'files' => [UploadedFile::fake()->create('big.pdf', 60_000, 'application/pdf')], // ~60MB > 50MB
        ])->assertSessionHasErrors('files.0');
    }

    public function test_delete_removes_row_and_file(): void
    {
        Storage::fake();
        $asset = Asset::factory()->create();
        Storage::disk()->put('attachments/x.pdf', 'data');
        $attachment = $asset->attachments()->create([
            'path' => 'attachments/x.pdf', 'original_name' => 'x.pdf', 'mime_type' => 'application/pdf',
            'size' => 4, 'type' => 'document', 'category' => 'dokument',
        ]);

        $this->actingAs($this->actor())->delete("/app/assets/{$asset->id}/attachments/{$attachment->id}")
            ->assertRedirect();

        $this->assertNull(Attachment::find($attachment->id));
        Storage::disk()->assertMissing('attachments/x.pdf');
    }

    public function test_delete_rejects_attachment_from_other_asset(): void
    {
        Storage::fake();
        $assetA = Asset::factory()->create();
        $assetB = Asset::factory()->create();
        $attachment = $assetB->attachments()->create([
            'path' => 'attachments/y.pdf', 'original_name' => 'y.pdf', 'mime_type' => 'application/pdf',
            'size' => 4, 'type' => 'document',
        ]);

        $this->actingAs($this->actor())->delete("/app/assets/{$assetA->id}/attachments/{$attachment->id}")
            ->assertForbidden();
        $this->assertNotNull(Attachment::find($attachment->id));
    }

    public function test_upload_requires_auth(): void
    {
        $asset = Asset::factory()->create();
        $this->post("/app/assets/{$asset->id}/attachments", ['files' => []])->assertRedirect(); // to login
    }
}
```

Extend `tests/Feature/App/AssetControllerTest.php` with a show-prop test:

```php
public function test_show_returns_attachments_and_category_options(): void
{
    $asset = Asset::factory()->create();
    $asset->attachments()->create([
        'path' => 'attachments/z.pdf', 'original_name' => 'z.pdf', 'mime_type' => 'application/pdf',
        'size' => 2048, 'type' => 'document', 'category' => 'dokument', 'title' => 'Z',
    ]);

    $this->actingAs(User::factory()->create(['login_enabled' => true]))
        ->get("/app/assets/{$asset->id}")
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p
            ->component('assets/show')
            ->has('attachments', 1, fn ($a) => $a
                ->where('type', 'document')->where('original_name', 'z.pdf')
                ->where('size_label', '2 KB')->has('url')->etc())
            ->has('attachmentCategoryOptions')
            ->etc());
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AssetAttachmentControllerTest.php tests/Feature/App/AssetControllerTest.php`
Expected: FAIL — routes/controller undefined; show lacks `attachments`.

- [ ] **Step 3: Create the FormRequest**

```php
<?php // app/Http/Requests/App/AssetAttachmentRequest.php
namespace App\Http\Requests\App;

use App\Enums\AttachmentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetAttachmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,mp4,mov,webm'],
            'title' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'category' => ['nullable', Rule::enum(AttachmentCategory::class)],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

```php
<?php // app/Http/Controllers/App/AssetAttachmentController.php
namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\AssetAttachmentRequest;
use App\Models\Asset;
use App\Models\Attachment;
use Illuminate\Http\RedirectResponse;

class AssetAttachmentController extends Controller
{
    public function store(AssetAttachmentRequest $request, Asset $asset): RedirectResponse
    {
        $data = $request->validated();

        foreach ($request->file('files') as $file) {
            $path = $file->store('attachments');
            $asset->attachments()->create([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'type' => Attachment::detectType((string) $file->getMimeType()),
                'category' => $data['category'] ?? null,
                'title' => $data['title'] ?? null,
                'note' => $data['note'] ?? null,
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', 'Attachment uploaded.');
    }

    public function destroy(Asset $asset, Attachment $attachment): RedirectResponse
    {
        abort_unless(
            $attachment->attachable_type === $asset->getMorphClass() && $attachment->attachable_id === $asset->id,
            403,
        );

        $attachment->delete(); // AttachmentObserver removes the file + logs

        return back()->with('success', 'Attachment deleted.');
    }
}
```

- [ ] **Step 5: Extend `AssetController@show` + add `humanSize`**

Replace the `show()` method in `app/Http/Controllers/App/AssetController.php`:

```php
public function show(Asset $asset): Response
{
    $asset->load('assetType', 'model.manufacturer', 'owner', 'place', 'tags', 'attachments.uploadedBy')
        ->loadCount('incidents');

    return Inertia::render('assets/show', [
        'asset' => $this->detail($asset),
        'attachments' => $asset->attachments->map(fn (\App\Models\Attachment $a) => [
            'id' => $a->id,
            'type' => $a->type,
            'category_label' => $a->category?->getLabel(),
            'title' => $a->title,
            'original_name' => $a->original_name,
            'size' => $a->size,
            'size_label' => $this->humanSize((int) $a->size),
            'uploaded_by_name' => optional($a->uploadedBy)->name,
            'created_at' => optional($a->created_at)->toDateTimeString(),
            'url' => route('attachments.open', $a),
        ])->all(),
        'attachmentCategoryOptions' => array_map(
            fn (\App\Enums\AttachmentCategory $c) => ['value' => $c->value, 'label' => $c->getLabel()],
            \App\Enums\AttachmentCategory::cases(),
        ),
    ]);
}
```

Add a private helper to the same controller:

```php
private function humanSize(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes.' B';
    }
    $kb = $bytes / 1024;
    if ($kb < 1024) {
        return rtrim(rtrim(number_format($kb, 1), '0'), '.').' KB';
    }

    return rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.').' MB';
}
```

(2048 bytes → `2 KB`, matching the show test.)

- [ ] **Step 6: Register the routes**

In `routes/web.php`, inside the authenticated `/app` group (near the assets resource):

```php
use App\Http\Controllers\App\AssetAttachmentController;
// … inside the authenticated group:
Route::post('assets/{asset}/attachments', [AssetAttachmentController::class, 'store'])->name('assets.attachments.store');
Route::delete('assets/{asset}/attachments/{attachment}', [AssetAttachmentController::class, 'destroy'])->name('assets.attachments.destroy');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AssetAttachmentControllerTest.php tests/Feature/App/AssetControllerTest.php`
Expected: PASS. Then full suite: `ddev exec ./vendor/bin/phpunit` → green.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/App/AssetAttachmentController.php app/Http/Requests/App/AssetAttachmentRequest.php app/Http/Controllers/App/AssetController.php routes/web.php tests/Feature/App/AssetAttachmentControllerTest.php tests/Feature/App/AssetControllerTest.php
git commit -m "feat(assets): attachment upload/delete endpoints + show attachments prop"
```

---

### Task 2: Frontend — attachments panel + Textarea primitive + wire into show

**Files:**
- Create: `resources/js/components/ui/textarea.tsx` (via shadcn CLI), `resources/js/components/assets/asset-attachments.tsx`
- Modify: `resources/js/pages/assets/show.tsx`
- Test: `resources/js/components/assets/__tests__/asset-attachments.test.tsx`

**Interfaces:**
- Consumes: `useForm`/`router` (Inertia), `TextField`, `SelectField`, shadcn `Textarea`/`Badge`/`Button`, props from Task 1.
- Produces: `AssetAttachments` (`{ assetId: string; attachments: AttachmentItem[]; categoryOptions: {value,label}[] }`); `show.tsx` renders it in the Attachments tab.

- [ ] **Step 1: Add the shadcn Textarea primitive**

Run: `ddev exec pnpm dlx shadcn@latest add textarea` (or add the standard shadcn `textarea.tsx` manually if the CLI can't run). Confirm it exports `Textarea`.

- [ ] **Step 2: Write the failing test**

```tsx
// resources/js/components/assets/__tests__/asset-attachments.test.tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { AssetAttachments } from '../asset-attachments';

const base = { assetId: 'a1', categoryOptions: [{ value: 'foto', label: 'Foto' }] };

describe('AssetAttachments', () => {
    it('renders image attachments as thumbnails', () => {
        render(<AssetAttachments {...base} attachments={[
            { id: 'i1', type: 'image', category_label: 'Foto', title: 'Front', original_name: 'front.jpg', size: 1000, size_label: '1000 B', uploaded_by_name: 'Ada', created_at: '2025-01-01 10:00:00', url: 'http://x/open/i1' },
        ]} />);
        const img = screen.getByRole('img');
        expect(img).toHaveAttribute('src', 'http://x/open/i1');
    });

    it('renders non-image attachments as rows with an Open link', () => {
        render(<AssetAttachments {...base} attachments={[
            { id: 'd1', type: 'document', category_label: 'Dokument', title: 'Manual', original_name: 'manual.pdf', size: 2048, size_label: '2 KB', uploaded_by_name: 'Ada', created_at: '2025-01-01 10:00:00', url: 'http://x/open/d1' },
        ]} />);
        expect(screen.getByText('manual.pdf')).toBeInTheDocument();
        const open = screen.getByRole('link', { name: /open/i });
        expect(open).toHaveAttribute('href', 'http://x/open/d1');
    });

    it('shows an empty state when there are no attachments', () => {
        render(<AssetAttachments {...base} attachments={[]} />);
        expect(screen.getByText(/no attachments/i)).toBeInTheDocument();
    });
});
```

- [ ] **Step 3: Run to verify it fails**

Run: `ddev exec pnpm exec vitest run resources/js/components/assets/__tests__/asset-attachments.test.tsx`
Expected: FAIL — module missing.

- [ ] **Step 4: Implement the panel**

```tsx
// resources/js/components/assets/asset-attachments.tsx
import { router, useForm } from '@inertiajs/react';
import { ExternalLink, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent } from '@/components/ui/card';
import { TextField } from '@/components/form/text-field';
import { SelectField } from '@/components/form/select-field';
import { FormError } from '@/components/form/form-error';

export interface AttachmentItem {
    id: string; type: string; category_label: string | null; title: string | null;
    original_name: string; size: number; size_label: string;
    uploaded_by_name: string | null; created_at: string | null; url: string;
}
interface Props { assetId: string; attachments: AttachmentItem[]; categoryOptions: { value: string; label: string }[]; }

export function AssetAttachments({ assetId, attachments, categoryOptions }: Props) {
    const form = useForm<{ files: File[]; title: string; category: string; note: string }>({
        files: [], title: '', category: '', note: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/app/assets/${assetId}/attachments`, {
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
    };

    const remove = (id: string) => {
        if (confirm('Delete this attachment?')) router.delete(`/app/assets/${assetId}/attachments/${id}`);
    };

    const images = attachments.filter((a) => a.type === 'image');
    const others = attachments.filter((a) => a.type !== 'image');

    return (
        <div className="space-y-6">
            <Card>
                <CardContent className="py-6">
                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="files">Files</Label>
                            <input id="files" type="file" multiple
                                onChange={(e) => form.setData('files', Array.from(e.target.files ?? []))}
                                className="block w-full text-sm file:mr-3 file:rounded-md file:border file:bg-secondary file:px-3 file:py-1.5" />
                            <FormError message={form.errors.files as string | undefined} />
                            <FormError message={(form.errors as Record<string, string>)['files.0']} />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField id="att_title" label="Title" value={form.data.title}
                                onChange={(v) => form.setData('title', v)} error={form.errors.title} />
                            <SelectField id="att_category" label="Category" nullable options={categoryOptions}
                                value={form.data.category} onChange={(v) => form.setData('category', v)} error={form.errors.category} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="att_note">Note</Label>
                            <Textarea id="att_note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                            <FormError message={form.errors.note} />
                        </div>
                        <Button type="submit" disabled={form.processing || form.data.files.length === 0}>Upload</Button>
                    </form>
                </CardContent>
            </Card>

            {attachments.length === 0 ? (
                <p className="text-sm text-muted-foreground">No attachments yet.</p>
            ) : (
                <div className="space-y-6">
                    {images.length > 0 && (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                            {images.map((a) => (
                                <div key={a.id} className="group relative">
                                    <a href={a.url} target="_blank" rel="noreferrer">
                                        <img src={a.url} alt={a.title ?? a.original_name} className="h-28 w-full rounded object-cover" />
                                    </a>
                                    <div className="mt-1 truncate text-xs text-muted-foreground">{a.title ?? a.original_name}</div>
                                    <Button variant="ghost" size="icon" className="absolute right-1 top-1 bg-background/80"
                                        onClick={() => remove(a.id)} aria-label={`Delete ${a.original_name}`}>
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                    {others.length > 0 && (
                        <div className="rounded-md border divide-y">
                            {others.map((a) => (
                                <div key={a.id} className="flex items-center gap-3 px-3 py-2 text-sm">
                                    <Badge variant="secondary">{a.type}</Badge>
                                    {a.category_label && <Badge variant="outline">{a.category_label}</Badge>}
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate font-medium">{a.title ?? a.original_name}</div>
                                        <div className="truncate text-xs text-muted-foreground">{a.original_name} · {a.size_label} · {a.uploaded_by_name ?? '—'} · {a.created_at ?? ''}</div>
                                    </div>
                                    <a href={a.url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-sm hover:underline">
                                        <ExternalLink className="h-4 w-4" /> Open
                                    </a>
                                    <Button variant="ghost" size="icon" onClick={() => remove(a.id)} aria-label={`Delete ${a.original_name}`}>
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
```

- [ ] **Step 5: Run to verify it passes**

Run: `ddev exec pnpm exec vitest run resources/js/components/assets/__tests__/asset-attachments.test.tsx`
Expected: PASS (3).

- [ ] **Step 6: Wire into `show.tsx`**

In `resources/js/pages/assets/show.tsx`:
- Import `AssetAttachments, type AttachmentItem` from `@/components/assets/asset-attachments`.
- Extend the page props interface to include `attachments: AttachmentItem[]` and `attachmentCategoryOptions: { value: string; label: string }[]`, and destructure them in the default export: `export default function ShowAsset({ asset, attachments, attachmentCategoryOptions }: Props)`.
- Replace the Attachments tab body:

```tsx
<TabsContent value="attachments" className="mt-4">
    <AssetAttachments assetId={asset.id} attachments={attachments} categoryOptions={attachmentCategoryOptions} />
</TabsContent>
```

(Leave the Incidents/History placeholders unchanged.)

- [ ] **Step 7: Build + full JS suite**

Run: `ddev exec pnpm run build` → succeeds, no type errors.
Run: `ddev exec pnpm run test` → green.

- [ ] **Step 8: Commit**

```bash
git add resources/js/components/ui/textarea.tsx resources/js/components/assets/asset-attachments.tsx resources/js/pages/assets/show.tsx resources/js/components/assets/__tests__/asset-attachments.test.tsx package.json pnpm-lock.yaml
git commit -m "feat(assets): attachments panel (upload, thumbnails, delete) in detail page"
```

---

### Task 3: Final verification

**Files:** none (verification only).

- [ ] **Step 1: Full PHP suite** — `ddev exec php artisan test` → green (attachment + all prior).
- [ ] **Step 2: Full JS suite** — `ddev exec pnpm run test` → green.
- [ ] **Step 3: Build** — `ddev exec pnpm run build` → succeeds.
- [ ] **Step 4: Manual verify** — on an asset detail page, the Attachments tab: upload a file (image → thumbnail, doc → row), Open opens it, delete removes it; `/app-old` Filament attachments still work.
- [ ] **Step 5:** Commit anything outstanding (releases automated — skip manual CHANGELOG).

---

## Self-Review Notes

- **Spec coverage:** show `attachments` prop + category options + `humanSize` → Task 1 Step 5; upload/delete endpoints + validation + ownership 403 → Task 1; frontend panel (thumbnails/rows/upload/delete) + Textarea + show wiring → Task 2; tests → Task 1 (PHPUnit `Storage::fake`) + Task 2 (Vitest) + Task 3. All spec sections covered.
- **Type consistency:** `AttachmentItem` (Task 2) matches the `show` `attachments` row shape (Task 1 Step 5) field-for-field; `AssetAttachments` props match the show wiring (Task 2 Step 6); category options `{value,label}` consistent.
- **Reuse/no-touch:** `attachments.open` reused for `url` (view + thumbnail); `Attachment` model/observer/migration untouched; default disk via `$file->store('attachments')` + `Storage::disk()`.
- **Ownership guard:** `destroy` `abort_unless` on morph type+id before delete; tested (403 + row/file untouched).
- **`size_label`:** `humanSize` yields `2 KB` for 2048 bytes (matches the show test).
