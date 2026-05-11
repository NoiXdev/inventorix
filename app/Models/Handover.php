<?php

namespace App\Models;

use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'type', 'recipient_kind',
    'recipient_user_id', 'recipient_name', 'recipient_email',
    'accessories', 'condition_notes', 'terms_text',
    'signature_path', 'signature_ip', 'signature_user_agent',
    'pdf_path', 'signed_at', 'created_by', 'email_sent_at',
])]
class Handover extends Model
{
    use HasUuids, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => HandoverType::class,
            'recipient_kind' => RecipientKind::class,
            'signed_at' => 'datetime',
            'email_sent_at' => 'datetime',
        ];
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'handover_asset')
            ->using(HandoverAsset::class)
            ->withPivot(['id', 'state_from', 'state_to', 'owner_from_id', 'owner_to_id'])
            ->withTimestamps();
    }
}
