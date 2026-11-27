<?php

namespace App\Http\Requests\App;

use App\Support\Assets\BulkSelection;

/** The check-in form plus the selected assets. */
class AssetBulkCheckinRequest extends AssetCheckinRequest
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
            'asset_ids.required' => 'Select at least one asset that is checked out.',
            'asset_ids.*.exists' => 'One of the selected assets no longer exists.',
        ];
    }
}
