<?php

namespace App\Http\Requests\App;

/** A scanned asset tag or serial plus the audit fields shared by the whole scan run. */
class AssetAuditScanRequest extends AssetAuditRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'string', 'max:255'],
        ];
    }
}
