<?php

namespace App\Http\Requests\App;

use App\Support\Assets\BulkSelection;

/** The check-out form plus the selected assets. */
class AssetBulkCheckoutRequest extends AssetCheckoutRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            ...BulkSelection::rules(),
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
