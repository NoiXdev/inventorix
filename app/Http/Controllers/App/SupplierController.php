<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\SupplierRequest;
use App\Models\Supplier;
use App\Support\Countries;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $suppliers = TableQuery::for(Supplier::query(), $request)
            ->searchable(['name', 'city', 'contact', 'email', 'phone'])
            ->sortable(['name', 'city', 'country', 'contact'])
            ->filterable(['country'])
            ->paginate();

        $suppliers->getCollection()->transform(fn (Supplier $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'image_url' => $this->imageUrl($s),
            'city' => $s->city,
            'country' => $s->country,
            'country_name' => Countries::name($s->country),
            'contact' => $s->contact,
            'email' => $s->email,
            'phone' => $s->phone,
            'url' => $s->url,
        ]);

        return Inertia::render('suppliers/index', [
            'suppliers' => [
                'data' => $suppliers->items(),
                'meta' => [
                    'current_page' => $suppliers->currentPage(),
                    'last_page' => $suppliers->lastPage(),
                    'per_page' => $suppliers->perPage(),
                    'total' => $suppliers->total(),
                ],
            ],
            // Only countries some supplier is in, so the filter is not 249 entries long.
            'countryOptions' => collect(Countries::options())
                ->whereIn('value', Supplier::query()->whereNotNull('country')->distinct()->pluck('country'))
                ->values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('suppliers/create', ['countryOptions' => Countries::options()]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($this->attributes($request));

        if ($file = $request->file('image')) {
            $supplier->forceFill(['image' => $file->store('suppliers')])->save();
        }

        return to_route('app.suppliers.index')->with('success', 'Supplier created.');
    }

    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('suppliers/edit', [
            'supplier' => [
                ...$supplier->only([
                    'id', 'name', 'address', 'address2', 'city', 'state', 'zip', 'country',
                    'phone', 'fax', 'email', 'contact', 'url', 'notes',
                ]),
                'image_url' => $this->imageUrl($supplier),
            ],
            'countryOptions' => Countries::options(),
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->attributes($request));

        $file = $request->file('image');
        $remove = $request->boolean('remove_image');

        if ($file || $remove) {
            $this->deleteImage($supplier);

            $supplier->forceFill([
                'image' => $file ? $file->store('suppliers') : null,
            ])->save();
        }

        return to_route('app.suppliers.index')->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        // The image is left in place on purpose: suppliers are soft-deleted
        // and can be restored, so the file must outlive the row.
        $supplier->delete();

        return to_route('app.suppliers.index')->with('success', 'Supplier deleted.');
    }

    /**
     * Stream the image from the private disk, the way attachments are served.
     */
    public function image(Supplier $supplier): StreamedResponse
    {
        abort_unless($supplier->image, 404);

        $disk = Storage::disk();
        abort_unless($disk->exists($supplier->image), 404);

        return $disk->response($supplier->image);
    }

    /**
     * The validated attributes that belong on the model — `image` is an upload
     * and `remove_image` a form-only flag, so neither may be mass-assigned.
     *
     * @return array<string, mixed>
     */
    private function attributes(SupplierRequest $request): array
    {
        return collect($request->validated())
            ->except(['image', 'remove_image'])
            ->all();
    }

    private function deleteImage(Supplier $supplier): void
    {
        if ($supplier->image) {
            Storage::disk()->delete($supplier->image);
        }
    }

    private function imageUrl(Supplier $supplier): ?string
    {
        return $supplier->image
            ? route('app.suppliers.image', $supplier, absolute: false)
            : null;
    }
}
