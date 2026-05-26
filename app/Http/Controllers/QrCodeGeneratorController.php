<?php

namespace App\Http\Controllers;

use App\Enums\QrCodeGeneratorType;
use App\Exceptions\QrCodeGeneratorException;
use App\Models\Asset;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class QrCodeGeneratorController
{
    /**
     * @throws QrCodeGeneratorException
     *
     * @noinspection PhpInconsistentReturnPointsInspection
     */
    public function generate(Request $request)
    {
        $amount = $request->get('amount', 10);
        $type = $request->get('type', QrCodeGeneratorType::TXT->value);

        if (QrCodeGeneratorType::tryFrom($type) === null) {
            throw new QrCodeGeneratorException("$type is not allowed. Allowed types are: ".implode(', ', array_map(fn ($case) => $case->value, QrCodeGeneratorType::cases())));
        }

        $generatedCodes = [];

        while (count($generatedCodes) < $amount) {
            $uuid = Uuid::uuid4();

            if (! Asset::find($uuid)) {
                $generatedCodes[] = $uuid;
            }
        }

        switch ($type) {
            case QrCodeGeneratorType::TXT->value:
                $content = implode("\n", $generatedCodes);

                return response($content, 200, [
                    'Content-Type' => 'text/plain',
                    'Content-Disposition' => 'attachment; filename="generated.txt"',
                ]);
                break;

            default:
                break;
        }
        // todo: Add default and create Excepetion
    }
}
