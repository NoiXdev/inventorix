<?php

namespace Database\Seeders;

use App\Models\Manufacturer;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds a curated set of manufacturers and copies their logos from
 * database/assets/manufacturers onto the upload disk, so seeded records are
 * indistinguishable from ones created through the form.
 *
 * Idempotent: re-running updates the rows in place and leaves existing logo
 * files alone, rather than piling up a fresh copy per run.
 */
class ManufacturerSeeder extends Seeder
{
    private const ASSET_DIR = 'assets/manufacturers';

    private const UPLOAD_DIR = 'manufacturers';

    /**
     * Phone numbers are deliberately fictional placeholders — a guessed number
     * that happens to be real would be dialled by whoever uses the demo data.
     *
     * The logo directory also holds dell*icon*.png and lenovo*icon*.png; those
     * are icon variants of the two entries below, not separate manufacturers.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function entries(): array
    {
        return [
            [
                'name' => 'Adobe',
                'url' => 'https://www.adobe.com',
                'support_url' => 'https://helpx.adobe.com/support.html',
                'warranty_lookup_url' => null,
                'support_phone' => '+49 30 000 0001',
                'support_email' => 'support@adobe.test',
                'notes' => 'Software only — licences are tracked, no hardware warranty.',
                'image' => 'adobe.jpg',
            ],
            [
                'name' => 'Apple',
                'url' => 'https://www.apple.com',
                'support_url' => 'https://support.apple.com',
                'warranty_lookup_url' => 'https://checkcoverage.apple.com',
                'support_phone' => '+49 30 000 0002',
                'support_email' => 'support@apple.test',
                'notes' => 'Coverage check needs the serial number from the device.',
                'image' => 'apple.jpg',
            ],
            [
                'name' => 'ASUS',
                'url' => 'https://www.asus.com',
                'support_url' => 'https://www.asus.com/support',
                'warranty_lookup_url' => 'https://www.asus.com/support/warranty-status-inquiry',
                'support_phone' => '+49 30 000 0003',
                'support_email' => 'support@asus.test',
                'notes' => null,
                'image' => 'asus.png',
            ],
            [
                'name' => 'Avery',
                'url' => 'https://www.avery.com',
                'support_url' => 'https://www.avery.com/help',
                'warranty_lookup_url' => null,
                'support_phone' => '+49 30 000 0004',
                'support_email' => 'support@avery.test',
                'notes' => 'Label stock supplier — used for asset tags.',
                'image' => 'avery.png',
            ],
            [
                'name' => 'Crucial',
                'url' => 'https://www.crucial.com',
                'support_url' => 'https://www.crucial.com/support',
                'warranty_lookup_url' => null,
                'support_phone' => '+49 30 000 0005',
                'support_email' => 'support@crucial.test',
                'notes' => 'Memory and SSDs; most parts carry a limited lifetime warranty.',
                'image' => 'crucial.jpg',
            ],
            [
                'name' => 'Dell',
                'url' => 'https://www.dell.com',
                'support_url' => 'https://www.dell.com/support',
                'warranty_lookup_url' => 'https://www.dell.com/support/home/en-us/product-support/servicetag',
                'support_phone' => '+49 30 000 0006',
                'support_email' => 'support@dell.test',
                'notes' => 'Warranty lookup needs the service tag, not the serial number.',
                'image' => 'dell.png',
            ],
            [
                'name' => 'Google',
                'url' => 'https://store.google.com',
                'support_url' => 'https://support.google.com',
                'warranty_lookup_url' => null,
                'support_phone' => '+49 30 000 0007',
                'support_email' => 'support@google.test',
                'notes' => null,
                'image' => 'google.webp',
            ],
            [
                'name' => 'HP',
                'url' => 'https://www.hp.com',
                'support_url' => 'https://support.hp.com',
                'warranty_lookup_url' => 'https://support.hp.com/us-en/checkwarranty',
                'support_phone' => '+49 30 000 0008',
                'support_email' => 'support@hp.test',
                'notes' => null,
                'image' => 'hp.png',
            ],
            [
                'name' => 'Huawei',
                'url' => 'https://www.huawei.com',
                'support_url' => 'https://consumer.huawei.com/en/support',
                'warranty_lookup_url' => null,
                'support_phone' => '+49 30 000 0009',
                'support_email' => 'support@huawei.test',
                'notes' => null,
                'image' => 'huawei.webp',
            ],
            [
                'name' => 'Lenovo',
                'url' => 'https://www.lenovo.com',
                'support_url' => 'https://support.lenovo.com',
                'warranty_lookup_url' => 'https://pcsupport.lenovo.com/warrantylookup',
                'support_phone' => '+49 30 000 0010',
                'support_email' => 'support@lenovo.test',
                'notes' => 'Docking stations are warranted separately from the laptop.',
                'image' => 'lenovo.jpg',
            ],
            [
                'name' => 'LG',
                'url' => 'https://www.lg.com',
                'support_url' => 'https://www.lg.com/support',
                'warranty_lookup_url' => null,
                'support_phone' => '+49 30 000 0011',
                'support_email' => 'support@lg.test',
                'notes' => null,
                'image' => 'lg.jpg',
            ],
            [
                'name' => 'Microsoft',
                'url' => 'https://www.microsoft.com',
                'support_url' => 'https://support.microsoft.com',
                'warranty_lookup_url' => 'https://account.microsoft.com/devices',
                'support_phone' => '+49 30 000 0012',
                'support_email' => 'support@microsoft.test',
                'notes' => 'Surface devices must be registered to appear in the device list.',
                'image' => 'microsoft.png',
            ],
            [
                'name' => 'Poly',
                'url' => 'https://www.poly.com',
                'support_url' => 'https://www.poly.com/support',
                'warranty_lookup_url' => null,
                'support_phone' => '+49 30 000 0013',
                'support_email' => 'support@poly.test',
                'notes' => 'Formerly Polycom — older assets may still carry that label.',
                'image' => 'polycom.png',
            ],
            [
                'name' => 'Samsung',
                'url' => 'https://www.samsung.com',
                'support_url' => 'https://www.samsung.com/support',
                'warranty_lookup_url' => null,
                'support_phone' => '+49 30 000 0014',
                'support_email' => 'support@samsung.test',
                'notes' => null,
                'image' => 'samsung.png',
            ],
            [
                'name' => 'Sony',
                'url' => 'https://www.sony.com',
                'support_url' => 'https://www.sony.com/electronics/support',
                'warranty_lookup_url' => null,
                'support_phone' => '+49 30 000 0015',
                'support_email' => 'support@sony.test',
                'notes' => null,
                'image' => 'sony.png',
            ],
        ];
    }

    public function run(): void
    {
        foreach (static::entries() as $entry) {
            $manufacturer = Manufacturer::updateOrCreate(
                ['name' => $entry['name']],
                collect($entry)->except(['name', 'image'])->all(),
            );

            $this->attachLogo($manufacturer, $entry['image']);
        }
    }

    /**
     * Copy the logo onto the upload disk exactly the way an upload would, but
     * only when the record does not already have a file there.
     */
    private function attachLogo(Manufacturer $manufacturer, string $filename): void
    {
        $disk = Storage::disk();

        if ($manufacturer->image_path && $disk->exists($manufacturer->image_path)) {
            return;
        }

        $source = database_path(self::ASSET_DIR.'/'.$filename);

        if (! is_file($source)) {
            $this->command?->warn("Logo not found, skipping: {$filename}");

            return;
        }

        $manufacturer->forceFill([
            'image_path' => $disk->putFile(self::UPLOAD_DIR, new File($source)),
        ])->save();
    }
}
