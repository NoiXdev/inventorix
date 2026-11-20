<?php

namespace App\Http\Requests\App;

use App\Enums\StatusLabelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatusLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the `auth` middleware; no per-resource policy exists
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            // One type instead of the three flags, so they can't contradict each other.
            'type' => ['required', Rule::enum(StatusLabelType::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'show_in_nav' => ['boolean'],
            'default_label' => ['boolean'],
        ];
    }

    /**
     * The validated input as model attributes: the type becomes the
     * deployable / pending / archived flags.
     *
     * @return array<string, mixed>
     */
    public function labelAttributes(): array
    {
        $data = $this->validated();

        return [
            ...collect($data)->except('type')->all(),
            ...StatusLabelType::from($data['type'])->flags(),
        ];
    }
}
