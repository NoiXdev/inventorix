<?php

namespace App\Http\Requests\App;

use App\Models\Asset;
use App\Models\Kit;
use App\Models\KitItem;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Checking a kit out to a user. Each model line needs exactly its quantity of
 * assets of that model, no asset chosen twice; each kind of line in the kit
 * needs the matching `<module>.checkout` permission.
 */
class KitCheckoutRequest extends FormRequest
{
    /** Line type => permission section. */
    public const MODULES = [
        'model' => 'assets',
        'license' => 'licenses',
        'accessory' => 'accessories',
        'consumable' => 'consumables',
    ];

    public function authorize(): bool
    {
        return true; // `can:kits.checkout` on the route
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', Rule::exists('users', 'id')],
            'assets' => ['nullable', 'array'],
            'assets.*' => ['array'],
            'assets.*.*' => ['required', 'uuid'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['user_id.required' => 'Choose who gets the kit.'];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $lines = $this->kit()->lines();

            if (($denied = self::permissionBlockers($this->user(), $lines)) !== []) {
                $validator->errors()->add('kit', $denied[0]);

                return;
            }
            if (($problem = $this->assetProblem($lines)) !== null) {
                $validator->errors()->add('assets', $problem);
            }
        }];
    }

    /**
     * Why the actor may not check out the kit's lines: one message per kind of
     * line whose check-out permission they lack.
     *
     * @param  Collection<int, KitItem>  $lines
     * @return list<string>
     */
    public static function permissionBlockers(User $actor, Collection $lines): array
    {
        return $lines->map(fn (KitItem $l) => self::MODULES[$l->typeKey()] ?? null)->filter()->unique()
            ->reject(fn (string $module) => $actor->can("{$module}.checkout"))
            ->map(fn (string $module) => "You may not check out {$module}.")
            ->values()->all();
    }

    /** @param  Collection<int, KitItem>  $lines */
    private function assetProblem(Collection $lines): ?string
    {
        $chosen = $this->assetIds();
        $modelLines = $lines->filter(fn (KitItem $l) => $l->typeKey() === 'model')->keyBy('id');

        if (array_diff(array_keys($chosen), $modelLines->keys()->all()) !== []) {
            return 'Assets were chosen for a line that is not an asset model of this kit.';
        }

        $all = [];
        foreach ($modelLines as $line) {
            $ids = $chosen[$line->id] ?? [];
            if (count($ids) !== $line->qty) {
                return "Choose {$line->qty} ".($line->qty === 1 ? 'asset' : 'assets').' of '.($line->item?->name ?? 'each model').'.';
            }
            array_push($all, ...$ids);
        }
        if (count($all) !== count(array_unique($all))) {
            return 'An asset can only be chosen once.';
        }

        $models = Asset::query()->whereIn('id', $all)->pluck('model_id', 'id');
        foreach ($modelLines as $line) {
            foreach ($chosen[$line->id] as $id) {
                if (($models[$id] ?? null) !== $line->item_id) {
                    return 'A chosen asset does not exist or is not a '.($line->item?->name ?? 'asset of this model').'.';
                }
            }
        }

        return null;
    }

    public function kit(): Kit
    {
        return $this->route('kit');
    }

    public function recipient(): User
    {
        return User::query()->findOrFail($this->validated('user_id'));
    }

    /** @return array<string, list<string>> kit item id => asset ids */
    public function assetIds(): array
    {
        return array_map(fn ($ids) => array_values((array) $ids), (array) ($this->validated('assets') ?? []));
    }
}
