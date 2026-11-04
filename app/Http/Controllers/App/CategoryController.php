<?php

namespace App\Http\Controllers\App;

use App\Enums\CategoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\CategoryRequest;
use App\Models\Category;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = TableQuery::for(Category::query(), $request)
            ->searchable(['name'])
            ->sortable(['name', 'category_type'])
            ->filterable(['category_type'])
            ->paginate();

        $categories->getCollection()->transform(fn (Category $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'image_url' => $this->imageUrl($c),
            'category_type' => $c->category_type?->value,
            'category_type_label' => $c->category_type?->getLabel(),
            'require_acceptance' => $c->require_acceptance,
            'checkin_email' => $c->checkin_email,
        ]);

        return Inertia::render('categories/index', [
            'categories' => [
                'data' => $categories->items(),
                'meta' => [
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total(),
                ],
            ],
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('categories/create', ['typeOptions' => $this->typeOptions()]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create($this->attributes($request));

        if ($file = $request->file('image')) {
            $category->forceFill(['image' => $file->store('categories')])->save();
        }

        return to_route('app.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('categories/edit', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'category_type' => $category->category_type?->value,
                'eula_text' => $category->eula_text,
                'use_default_eula' => $category->use_default_eula,
                'require_acceptance' => $category->require_acceptance,
                'alert_on_response' => $category->alert_on_response,
                'checkin_email' => $category->checkin_email,
                'notes' => $category->notes,
                'image_url' => $this->imageUrl($category),
            ],
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($this->attributes($request));

        $file = $request->file('image');
        $remove = $request->boolean('remove_image');

        if ($file || $remove) {
            $this->deleteImage($category);

            $category->forceFill([
                'image' => $file ? $file->store('categories') : null,
            ])->save();
        }

        return to_route('app.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        // The image is left in place on purpose: categories are soft-deleted
        // and can be restored, so the file must outlive the row.
        $category->delete();

        return to_route('app.categories.index')->with('success', 'Category deleted.');
    }

    /**
     * Stream the image from the private disk, the way attachments are served.
     */
    public function image(Category $category): StreamedResponse
    {
        abort_unless($category->image, 404);

        $disk = Storage::disk();
        abort_unless($disk->exists($category->image), 404);

        return $disk->response($category->image);
    }

    /**
     * The validated attributes that belong on the model — `image` is an upload
     * and `remove_image` a form-only flag, so neither may be mass-assigned.
     *
     * @return array<string, mixed>
     */
    private function attributes(CategoryRequest $request): array
    {
        return collect($request->validated())
            ->except(['image', 'remove_image'])
            ->all();
    }

    private function deleteImage(Category $category): void
    {
        if ($category->image) {
            Storage::disk()->delete($category->image);
        }
    }

    private function imageUrl(Category $category): ?string
    {
        return $category->image
            ? route('app.categories.image', $category, absolute: false)
            : null;
    }

    /** @return list<array{value: string, label: ?string}> */
    private function typeOptions(): array
    {
        return array_map(
            fn (CategoryType $t) => ['value' => $t->value, 'label' => $t->getLabel()],
            CategoryType::cases(),
        );
    }
}
