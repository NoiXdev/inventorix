<?php

namespace Database\Factories;

use App\Enums\SystemStatus;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'status_id' => fn () => StatusLabel::system(SystemStatus::NEW)->id,
            'model_id' => AssetModel::factory(),
            'assignee_type' => User::class,
            'assignee_id' => User::factory()->withoutLogin(),
            'location_id' => Location::factory(),
            'serial' => fake()->bothify('SN-#####??'),
            'purchase_date' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
            'purchase_cost' => fake()->randomFloat(2, 100, 5000),
            'warranty_months' => fake()->randomElement([12, 24, 36]),
            'order_number' => fake()->bothify('PO-####'),
        ];
    }

    /** The asset is in the label the application uses for this system status. */
    public function inStatus(SystemStatus $status): static
    {
        return $this->state(fn () => ['status_id' => StatusLabel::system($status)->id]);
    }

    /** Assigned to nobody. */
    public function unassigned(): static
    {
        return $this->state(fn () => ['assignee_type' => null, 'assignee_id' => null]);
    }

    /** Assigned to the given user, location or asset. */
    public function assignedTo(User|Location|Asset $assignee): static
    {
        return $this->state(fn () => ['assignee_type' => $assignee->getMorphClass(), 'assignee_id' => $assignee->getKey()]);
    }

    /**
     * Warranty ending on the given date: bought 12 months before, with a
     * 12 month warranty (no warranty months when that does not land on the
     * date, e.g. around 29 February, so the end is exact either way).
     */
    public function warrantyEndingOn(CarbonInterface|string $date): static
    {
        $end = Carbon::parse($date)->startOfDay();
        $purchased = $end->copy()->subMonthsNoOverflow(12);
        $exact = $purchased->copy()->addMonthsNoOverflow(12)->equalTo($end);

        return $this->state(fn () => [
            'purchase_date' => ($exact ? $purchased : $end)->toDateString(),
            'warranty_months' => $exact ? 12 : 0,
        ]);
    }

    public function withoutWarranty(): static
    {
        return $this->state(fn () => ['warranty_months' => null]);
    }
}
