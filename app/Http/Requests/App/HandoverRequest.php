<?php

namespace App\Http\Requests\App;

use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Models\Asset;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HandoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(HandoverType::class)],
            'recipient_kind' => ['required', Rule::enum(RecipientKind::class)],
            'recipient_person_id' => ['nullable', 'required_if:recipient_kind,internal', 'uuid', 'exists:people,id'],
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
            $type = HandoverType::tryFrom((string) $this->input('type'));
            $ids = (array) $this->input('asset_ids', []);
            if ($type === null || $ids === []) {
                return; // base rules already flagged these
            }
            $allowed = array_map(fn ($s) => $s->value, $type->allowedStateFrom());
            $bad = Asset::query()->whereIn('id', $ids)->get()
                ->filter(fn (Asset $a) => ! in_array($a->state->value, $allowed, true));
            if ($bad->isNotEmpty()) {
                $validator->errors()->add('asset_ids', 'One or more selected assets are not in an allowed state for this handover type.');
            }
        }];
    }
}
