<?php

use App\Enums\QrCodeGeneratorType;
use App\Models\Asset;
use Ramsey\Uuid\Uuid;

Route::get('/gq', function () {
    $amount = request()->get('amount');
    $type = request()->get('type');

    ray($amount, $type);
    $generatedCodes = [];

    while (count($generatedCodes) < $amount) {
        $uuid = Uuid::uuid4();

        if(!Asset::find($uuid)){
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
        //todo: Add default and create Excepetion
    }
});
