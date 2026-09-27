<?php

namespace App\Models;

use Database\Factories\FeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $percent
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['percent', 'is_active', 'created_by'])]
class Fee extends Model
{
    /** @use HasFactory<FeeFactory> */
    use HasFactory;

    protected $table = 'platform_fees';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percent' => 'integer',
            'is_active' => 'boolean',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The currently-active platform fee.
     */
    public static function active(): ?self
    {
        return self::where('is_active', true)->latest('id')->first();
    }

    /**
     * The active fee percentage, defaulting to 0 when none is configured.
     */
    public static function activePercent(): int
    {
        $fee = self::active();

        return $fee instanceof self ? $fee->percent : 0;
    }

    /**
     * Split a gross sale amount into platform fee and seller payout.
     *
     * @return array{percent: int, fee_amount_cents: int, payout_cents: int}
     */
    public static function computeFor(int $amountCents): array
    {
        $percent = self::activePercent();
        $feeCents = (int) round($amountCents * $percent / 100);

        return [
            'percent' => $percent,
            'fee_amount_cents' => $feeCents,
            'payout_cents' => $amountCents - $feeCents,
        ];
    }

    /**
     * Activate a new fee, deactivating any previous active fee.
     */
    public static function setActive(int $percent, ?int $createdBy = null): self
    {
        self::query()->where('is_active', true)->update(['is_active' => false]);

        return self::create([
            'percent' => $percent,
            'is_active' => true,
            'created_by' => $createdBy,
        ]);
    }
}
