<?php

namespace App\Http\Requests\App;

use App\Models\Kit;
use App\Models\KitItem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the `auth` middleware; no per-resource policy exists
    }

    public function rules(): array
    {
        $kit = $this->route('kit');

        return [
            'name' => ['required', 'string', 'max:191', Rule::unique('kits', 'name')->whereNull('deleted_at')->ignore($kit instanceof Kit ? $kit->id : null)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['nullable', 'array', 'max:200'],
            'items.*.type' => ['required', 'string', Rule::in(array_keys(KitItem::TYPES))],
            'items.*.id' => ['required', 'uuid'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * Per line: no (type, id) twice, and the item must be a live record. A
     * line the kit already has is kept even if its item was deleted since, so
     * deleting an accessory doesn't make every kit holding it unsaveable.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $current = $this->currentLines();
            $seen = [];

            foreach ((array) $this->input('items', []) as $i => $line) {
                $class = KitItem::TYPES[$line['type'] ?? null] ?? null;
                $id = $line['id'] ?? null;
                if (! $class || ! is_string($id) || $validator->errors()->has("items.$i.id")) {
                    continue;
                }

                if (isset($seen["$class|$id"])) {
                    $validator->errors()->add("items.$i.id", 'This item is already in the kit.');

                    continue;
                }
                $seen["$class|$id"] = true;

                if (! isset($current["$class|$id"]) && ! $class::query()->whereKey($id)->exists()) {
                    $validator->errors()->add("items.$i.id", 'The selected item does not exist.');
                }
            }
        }];
    }

    /** @return array<string, true> "class|id" of the lines the kit has now */
    private function currentLines(): array
    {
        $kit = $this->route('kit');
        if (! $kit instanceof Kit) {
            return [];
        }

        return $kit->items()->get(['item_type', 'item_id'])
            ->mapWithKeys(fn (KitItem $i) => ["{$i->item_type}|{$i->item_id}" => true])->all();
    }
}
