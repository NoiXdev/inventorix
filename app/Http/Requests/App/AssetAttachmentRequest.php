<?php

namespace App\Http\Requests\App;

use App\Enums\AttachmentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,mp4,mov,webm'],
            'title' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'category' => ['nullable', Rule::enum(AttachmentCategory::class)],
        ];
    }
}
