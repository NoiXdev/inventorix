<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Incident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = Carbon::now()->startOfDay();

        return Inertia::render('dashboard', [
            'stats' => ['assets' => Asset::query()->count()],
            'warranty' => [
                'expired' => $this->warrantyEndsBetween(null, $today->copy()->subDay())->count(),
                'soon_30' => $this->warrantyEndsBetween($today, $today->copy()->addDays(30))->count(),
                'soon_90' => $this->warrantyEndsBetween($today, $today->copy()->addDays(90))->count(),
            ],
            'latestDocuments' => Attachment::query()->where('type', 'document')->with('uploadedBy')->latest()->limit(10)->get()
                ->map(fn (Attachment $a) => [
                    'id' => $a->id,
                    'title' => $a->title ?: $a->original_name,
                    'category_label' => $a->category?->getLabel(),
                    'attached_to' => class_basename($a->attachable_type),
                    'uploaded_by' => optional($a->uploadedBy)->name,
                    'created_at' => optional($a->created_at)->format('d.m.Y H:i'),
                    'url' => route('attachments.open', $a->id),
                ])->all(),
            'openIncidents' => Incident::query()->whereNull('closed_date')->with('asset.model')->orderByDesc('open_date')->limit(10)->get()
                ->map(fn (Incident $i) => [
                    'id' => $i->id,
                    'title' => $i->title,
                    'model' => optional(optional($i->asset)->model)->name,
                    'serial' => optional($i->asset)->serial,
                    'open_date' => optional($i->open_date)->format('d.m.Y'),
                    'days_open' => $i->open_date ? (int) $i->open_date->diffInDays(Carbon::now()) : null,
                    'asset_url' => $i->asset_id ? "/app/assets/{$i->asset_id}" : null,
                ])->all(),
            'warrantyExpiring' => Asset::query()->withWarranty()->with('assignee', 'model')
                ->orderByRaw(Asset::warrantyEndSql())->limit(10)->get()
                ->map(fn (Asset $a) => [
                    'id' => $a->id,
                    'assignee' => $a->assigneeName(),
                    'model' => optional($a->model)->name,
                    'serial' => $a->serial,
                    'warranty_end' => $a->warrantyEnd()->format('d.m.Y'),
                    'days_left' => (int) Carbon::now()->startOfDay()->diffInDays($a->warrantyEnd(), false),
                    'asset_url' => "/app/assets/{$a->id}",
                ])->all(),
        ]);
    }

    /** Assets whose warranty ends within the given days (inclusive; null = open). */
    private function warrantyEndsBetween(?Carbon $from, Carbon $to): Builder
    {
        $end = Asset::warrantyEndSql();

        return Asset::query()->withWarranty()
            ->when($from, fn (Builder $q) => $q->whereRaw("{$end} >= ?", [$from->toDateString()]))
            ->whereRaw("{$end} <= ?", [$to->toDateString()]);
    }
}
