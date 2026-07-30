<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\AssetAttachmentRequest;
use App\Models\Asset;
use App\Models\Attachment;
use Illuminate\Http\RedirectResponse;

class AssetAttachmentController extends Controller
{
    public function store(AssetAttachmentRequest $request, Asset $asset): RedirectResponse
    {
        $data = $request->validated();

        foreach ($request->file('files') as $file) {
            $path = $file->store('attachments');
            $asset->attachments()->create([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'type' => Attachment::detectType((string) $file->getMimeType()),
                'category' => $data['category'] ?? null,
                'title' => $data['title'] ?? null,
                'note' => $data['note'] ?? null,
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', 'Attachment uploaded.');
    }

    public function destroy(Asset $asset, Attachment $attachment): RedirectResponse
    {
        abort_unless(
            $attachment->attachable_type === $asset->getMorphClass() && $attachment->attachable_id === $asset->id,
            403,
        );

        $attachment->delete(); // AttachmentObserver removes the file + logs

        return back()->with('success', 'Attachment deleted.');
    }
}
