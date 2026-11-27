<?php

namespace App\Http\Requests\App;

use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Models\Asset;
use App\Models\StatusLabel;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HandoverRequest extends FormRequest
{
    /**
     * A handover moves assets, so it needs the matching check-out/check-in
     * permission on top of handovers.create (route middleware). An unknown
     * type is left to validation.
     */
    public function authorize(): bool
    {
        $type = is_string($t = $this->input('type')) ? HandoverType::tryFrom($t) : null;

        return $type === null || $this->user()->can($type->requiredPermission()->value);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(HandoverType::class)],
            'recipient_kind' => ['required', Rule::enum(RecipientKind::class)],
            'recipient_user_id' => ['nullable', 'required_if:recipient_kind,internal', 'uuid', 'exists:users,id'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'asset_ids' => ['required', 'array', 'min:1'],
            'asset_ids.*' => ['uuid', 'exists:assets,id'],
            'accessories' => ['nullable', 'string'],
            'condition_notes' => ['nullable', 'string'],
            'terms_text' => ['required', 'string'],
            'signature_png' => ['required', 'string'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = is_string($t = $this->input('type')) ? HandoverType::tryFrom($t) : null;
            $ids = (array) $this->input('asset_ids', []);
            if ($type === null || $ids === []) {
                return; // base rules already flagged these
            }
            $allowed = StatusLabel::systemIds($type->allowedStateFrom());
            $bad = Asset::query()->whereIn('id', $ids)->get()
                ->filter(fn (Asset $a) => ! in_array($a->status_id, $allowed, true));
            if ($bad->isNotEmpty()) {
                $validator->errors()->add('asset_ids', 'One or more selected assets are not in an allowed state for this handover type.');
            }
        }];
    }
}
