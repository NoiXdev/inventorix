<?php

namespace App\Http\Requests\App;

use Illuminate\Validation\Rule;

/** The check-out form plus the selected assets. */
class AssetBulkCheckoutRequest extends AssetCheckoutRequest
{
    public const MAX_ASSETS = 500;

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'asset_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_ASSETS],
            'asset_ids.*' => ['uuid', 'distinct', Rule::exists('assets', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'asset_ids.required' => 'Select at least one asset that can be checked out.',
            'asset_ids.*.exists' => 'One of the selected assets no longer exists.',
        ];
    }
}
