<?php

namespace App\Jobs;

use App\Mail\HandoverSigned;
use App\Models\Handover;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class GenerateHandoverPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public string $handoverId) {}

    public function handle(): void
    {
        $handover = Handover::with(['assets.model', 'createdBy'])->findOrFail($this->handoverId);
        $disk = config('handover.disk');

        $signatureBytes = Storage::disk($disk)->get($handover->signature_path);
        $signatureBase64 = base64_encode($signatureBytes);

        $pdf = Pdf::loadView('pdf.handover', [
            'handover' => $handover,
            'signatureBase64' => $signatureBase64,
            'companyName' => config('handover.company.name'),
        ])->setPaper(
            config('handover.pdf.paper'),
            config('handover.pdf.orientation'),
        );

        $pdfPath = "handovers/{$handover->id}/handover.pdf";
        Storage::disk($disk)->put($pdfPath, $pdf->output());

        $handover->update(['pdf_path' => $pdfPath]);

        if ($handover->recipient_email) {
            Mail::to($handover->recipient_email)
                ->bcc(optional($handover->createdBy)->email)
                ->send(new HandoverSigned($handover->id));

            $handover->update(['email_sent_at' => now()]);
        }
    }
}
