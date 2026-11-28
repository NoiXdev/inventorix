<?php

namespace App\Http\Requests\App;

use App\Enums\CategoryType;
use App\Models\Component;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the `auth` middleware; no per-resource policy exists
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            // Only component categories apply; the others are for assets, licenses etc.
            'category_id' => $this->related('category_id', 'categories', ['category_type' => CategoryType::COMPONENT->value]),
            'manufacturer_id' => $this->related('manufacturer_id', 'manufacturers'),
            'model_number' => ['nullable', 'string', 'max:191'],
            'serial' => ['nullable', 'string', 'max:191'],
            'purchase_date' => ['nullable', 'date'],
            'order_number' => ['nullable', 'string', 'max:191'],
            'location_id' => $this->related('location_id', 'locations'),
            'supplier_id' => $this->related('supplier_id', 'suppliers'),
            // Not below what is installed (as Snipe-IT).
            'qty' => ['required', 'integer', 'min:'.$this->checkedOut(), 'max:4294967295'],
            'min_amt' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999999999.99', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:5000'],

            // Deliberately no SVG: the image is streamed inline, and an SVG can
            // carry script — that would be stored XSS through the upload.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return $this->checkedOut() > 0
            ? ['qty.min' => 'The quantity cannot be lower than the :min units installed.']
            : [];
    }

    private function checkedOut(): int
    {
        $component = $this->route('component');

        return $component instanceof Component ? (int) $component->checkouts()->sum('assigned_qty') : 0;
    }

    /**
     * A new link must point at a live record (and match `$where`, e.g. the
     * category type). A link the component already has is kept as it is, even
     * if that record was deleted or changed since: otherwise deleting a
     * location would make every component stored there impossible to save.
     *
     * @param  array<string, string>  $where
     * @return list<mixed>
     */
    private function related(string $attribute, string $table, array $where = []): array
    {
        $component = $this->route('component');
        $unchanged = $component instanceof Component
            && $component->{$attribute} !== null
            && $this->input($attribute) === $component->{$attribute};

        $exists = Rule::exists($table, 'id');
        if (! $unchanged) {
            $exists->whereNull('deleted_at');
            foreach ($where as $column => $value) {
                $exists->where($column, $value);
            }
        }

        return ['nullable', 'uuid', $exists];
    }
}
