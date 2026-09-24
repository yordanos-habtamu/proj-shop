<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $buyer_id
 * @property int $project_id
 * @property int $amount_cents
 * @property string $currency
 * @property string|null $provider
 * @property string|null $provider_tx_id
 * @property OrderStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'buyer_id',
    'project_id',
    'amount_cents',
    'currency',
    'provider',
    'provider_tx_id',
    'status',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'amount_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<Download, $this>
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    public function markPaid(?string $provider, ?string $providerTxId): void
    {
        $this->update([
            'provider' => $provider,
            'provider_tx_id' => $providerTxId,
            'status' => OrderStatus::Paid,
        ]);
    }

    public function markRefunded(): void
    {
        $this->update(['status' => OrderStatus::Refunded]);
    }
}
