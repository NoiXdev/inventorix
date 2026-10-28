<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\ManufacturerRequest;
use App\Models\Manufacturer;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManufacturerController extends Controller
{
    public function index(Request $request): Response
    {
        // withCount(['models', 'assets']) runs both counts as correlated
        // subqueries on the same query, avoiding a query per row (assets is a
        // hasManyThrough relation on the model, see Manufacturer::assets()).
        $manufacturers = TableQuery::for(
            Manufacturer::query()->withCount(['models', 'assets']),
            $request,
        )->searchable(['name'])->sortable(['name', 'models_count'])->paginate();

        $manufacturers->getCollection()->transform(fn (Manufacturer $m) => [
            'id' => $m->id,
            'name' => $m->name,
            'image_url' => $this->imageUrl($m),
            'models_count' => $m->models_count,
            'assets_count' => $m->assets_count,
        ]);

        return Inertia::render('manufacturers/index', [
            'manufacturers' => [
                'data' => $manufacturers->items(),
                'meta' => [
                    'current_page' => $manufacturers->currentPage(),
                    'last_page' => $manufacturers->lastPage(),
                    'per_page' => $manufacturers->perPage(),
                    'total' => $manufacturers->total(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('manufacturers/create');
    }

    public function store(ManufacturerRequest $request): RedirectResponse
    {
        $manufacturer = Manufacturer::create($this->attributes($request));

        if ($file = $request->file('image')) {
            $manufacturer->forceFill(['image_path' => $file->store('manufacturers')])->save();
        }

        return to_route('app.manufacturers.index')->with('success', 'Manufacturer created.');
    }

    public function edit(Manufacturer $manufacturer): Response
    {
        return Inertia::render('manufacturers/edit', [
            'manufacturer' => [
                'id' => $manufacturer->id,
                'name' => $manufacturer->name,
                'url' => $manufacturer->url,
                'support_url' => $manufacturer->support_url,
                'warranty_lookup_url' => $manufacturer->warranty_lookup_url,
                'support_phone' => $manufacturer->support_phone,
                'support_email' => $manufacturer->support_email,
                'notes' => $manufacturer->notes,
                'image_url' => $this->imageUrl($manufacturer),
            ],
        ]);
    }

    public function update(ManufacturerRequest $request, Manufacturer $manufacturer): RedirectResponse
    {
        $manufacturer->update($this->attributes($request));

        $file = $request->file('image');
        $remove = $request->boolean('remove_image');

        if ($file || $remove) {
            $this->deleteImage($manufacturer);

            $manufacturer->forceFill([
                'image_path' => $file ? $file->store('manufacturers') : null,
            ])->save();
        }

        return to_route('app.manufacturers.index')->with('success', 'Manufacturer updated.');
    }

    public function destroy(Manufacturer $manufacturer): RedirectResponse
    {
        // The logo file is left in place on purpose: manufacturers are
        // soft-deleted and can be restored, so the file must outlive the row.
        $manufacturer->delete();

        return to_route('app.manufacturers.index')->with('success', 'Manufacturer deleted.');
    }

    /**
     * Stream the logo from the private disk, the way attachments are served.
     */
    public function image(Manufacturer $manufacturer): StreamedResponse
    {
        abort_unless($manufacturer->image_path, 404);

        $disk = Storage::disk();
        abort_unless($disk->exists($manufacturer->image_path), 404);

        return $disk->response($manufacturer->image_path);
    }

    /**
     * The validated attributes that belong on the model — `image` is an upload
     * and `remove_image` a form-only flag, so neither may be mass-assigned.
     *
     * @return array<string, mixed>
     */
    private function attributes(ManufacturerRequest $request): array
    {
        return collect($request->validated())
            ->except(['image', 'remove_image'])
            ->all();
    }

    private function deleteImage(Manufacturer $manufacturer): void
    {
        if ($manufacturer->image_path) {
            Storage::disk()->delete($manufacturer->image_path);
        }
    }

    private function imageUrl(Manufacturer $manufacturer): ?string
    {
        return $manufacturer->image_path
            ? route('app.manufacturers.image', $manufacturer, absolute: false)
            : null;
    }
}
