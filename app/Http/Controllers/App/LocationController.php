<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\LocationRequest;
use App\Models\Location;
use App\Models\User;
use App\Support\Countries;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LocationController extends Controller
{
    public function index(Request $request): Response
    {
        $locations = TableQuery::for(Location::query()->with('manager')->withCount('assets'), $request)
            ->searchable(['name', 'city', 'address'])
            ->sortable(['name', 'city', 'country', 'assets_count'])
            ->filterable(['country', 'parent_id'])
            ->paginate();

        $paths = Location::paths(withTrashed: true);

        $locations->getCollection()->transform(fn (Location $l) => [
            'id' => $l->id,
            'name' => $l->name,
            'image_url' => $this->imageUrl($l),
            'parent_path' => $l->parent_id ? $paths->get($l->parent_id) : null,
            'city' => $l->city,
            'country' => $l->country,
            'country_name' => Countries::name($l->country),
            'manager_name' => $l->manager?->name,
            'assets_count' => $l->assets_count,
        ]);

        return Inertia::render('locations/index', [
            'locations' => [
                'data' => $locations->items(),
                'meta' => [
                    'current_page' => $locations->currentPage(),
                    'last_page' => $locations->lastPage(),
                    'per_page' => $locations->perPage(),
                    'total' => $locations->total(),
                ],
            ],
            // Only countries some location is in, so the filter is not 249 entries long.
            'countryOptions' => collect(Countries::options())
                ->whereIn('value', Location::query()->whereNotNull('country')->distinct()->pluck('country'))
                ->values(),
            'parentOptions' => Location::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('locations/create', $this->formOptions());
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        $location = Location::create($this->attributes($request));

        if ($file = $request->file('image')) {
            $location->forceFill(['image' => $file->store('locations')])->save();
        }

        return to_route('app.locations.index')->with('success', 'Location created.');
    }

    public function edit(Location $location): Response
    {
        return Inertia::render('locations/edit', [
            'location' => [
                ...$location->only([
                    'id', 'name', 'parent_id', 'manager_id', 'address', 'address2', 'city', 'state', 'zip',
                    'country', 'phone', 'fax', 'currency', 'ldap_ou', 'notes',
                ]),
                'image_url' => $this->imageUrl($location),
            ],
            ...$this->formOptions($location),
        ]);
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($this->attributes($request));

        $file = $request->file('image');
        $remove = $request->boolean('remove_image');

        if ($file || $remove) {
            $this->deleteImage($location);

            $location->forceFill([
                'image' => $file ? $file->store('locations') : null,
            ])->save();
        }

        return to_route('app.locations.index')->with('success', 'Location updated.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        // Soft delete: assets and sub-locations keep pointing here, so a
        // restore puts everything back. The image is kept for the same reason.
        $location->delete();

        return to_route('app.locations.index')->with('success', 'Location deleted.');
    }

    /**
     * Stream the image from the private disk, the way attachments are served.
     */
    public function image(Location $location): StreamedResponse
    {
        abort_unless($location->image, 404);

        $disk = Storage::disk();
        abort_unless($disk->exists($location->image), 404);

        return $disk->response($location->image);
    }

    /** @return array<string, mixed> */
    private function formOptions(?Location $location = null): array
    {
        // A location can't sit inside itself or below one of its own children.
        $exclude = $location ? [$location->id, ...$location->descendantIds()] : [];

        return [
            'parentOptions' => array_values(array_filter(
                Location::options(),
                fn (array $o) => ! in_array($o['value'], $exclude, true),
            )),
            'managerOptions' => User::query()->orderBy('name')->get()
                ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name ?? $u->email ?? '—'])->all(),
            'countryOptions' => Countries::options(),
        ];
    }

    /**
     * The validated attributes that belong on the model — `image` is an upload
     * and `remove_image` a form-only flag, so neither may be mass-assigned.
     *
     * @return array<string, mixed>
     */
    private function attributes(LocationRequest $request): array
    {
        return collect($request->validated())
            ->except(['image', 'remove_image'])
            ->all();
    }

    private function deleteImage(Location $location): void
    {
        if ($location->image) {
            Storage::disk()->delete($location->image);
        }
    }

    private function imageUrl(Location $location): ?string
    {
        return $location->image
            ? route('app.locations.image', $location, absolute: false)
            : null;
    }
}
