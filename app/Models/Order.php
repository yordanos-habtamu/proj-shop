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
 * @property int|null $fee_percent
 * @property int|null $fee_amount_cents
 * @property int|null $payout_cents
 * @property string $currency
 * @property string|null $provider
 * @property string|null $provider_tx_id
 * @property string|null $stripe_checkout_id
 * @property OrderStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'buyer_id',
    'project_id',
    'amount_cents',
    'fee_percent',
    'fee_amount_cents',
    'payout_cents',
    'currency',
    'provider',
    'provider_tx_id',
    'stripe_checkout_id',
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
            'fee_percent' => 'integer',
            'fee_amount_cents' => 'integer',
            'payout_cents' => 'integer',
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

    public function isPending(): bool
    {
        return $this->status === OrderStatus::Pending;
    }

    public function isRefunded(): bool
    {
        return $this->status === OrderStatus::Refunded;
    }

    /**
     * Whether a payment attempt may still be resolved into a paid order.
     */
    public function isSettleable(): bool
    {
        return in_array($this->status, [
            OrderStatus::Pending,
            OrderStatus::Expired,
            OrderStatus::Failed,
        ], true);
    }

    /**
     * Settle the order as paid. Idempotent: returns false when already paid.
     */
    public function markPaid(?string $provider, ?string $providerTxId, ?string $checkoutId = null): bool
    {
        if (! $this->isSettleable()) {
            return false;
        }

        $this->update([
            'provider' => $provider,
            'provider_tx_id' => $providerTxId,
            'stripe_checkout_id' => $checkoutId ?? $this->stripe_checkout_id,
            'status' => OrderStatus::Paid,
        ]);

        return true;
    }

    public function markRefunded(): bool
    {
        return $this->transitionTo(OrderStatus::Refunded, [OrderStatus::Paid]);
    }

    public function markExpired(): bool
    {
        return $this->transitionTo(OrderStatus::Expired, [OrderStatus::Pending]);
    }

    public function markFailed(): bool
    {
        return $this->transitionTo(OrderStatus::Failed, [OrderStatus::Pending]);
    }

    /**
     * Move the order to a new status when the current status is allowed.
     *
     * @param  list<OrderStatus>  $from
     */
    private function transitionTo(OrderStatus $status, array $from): bool
    {
        if (! in_array($this->status, $from, true)) {
            return false;
        }

        $this->update(['status' => $status]);

        return true;
    }
}
