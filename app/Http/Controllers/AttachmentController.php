<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController
{
    /**
     * Stream an attachment inline so it opens in the browser.
     */
    public function show(Attachment $attachment): StreamedResponse
    {
        $disk = Storage::disk();

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->original_name);
    }
}
