<?php

namespace App\Models;

use App\Enums\ProjectCompleteness;
use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $seller_id
 * @property string $title
 * @property string $slug
 * @property string|null $tagline
 * @property string $description
 * @property int $price_cents
 * @property string $currency
 * @property ProjectCompleteness $completeness
 * @property ProjectStatus $status
 * @property string|null $cover_image_path
 * @property string|null $zip_path
 * @property array<string, mixed>|null $tech_stack
 * @property array<string, mixed>|null $scan_report
 * @property string|null $review_notes
 * @property Carbon|null $reviewed_at
 * @property int|null $orders_count
 * @property int|null $sales_count
 * @property int|null $gross_volume_cents
 * @property int|null $net_payout_cents
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'seller_id',
    'title',
    'slug',
    'tagline',
    'description',
    'price_cents',
    'currency',
    'completeness',
    'status',
    'cover_image_path',
    'zip_path',
    'tech_stack',
    'scan_report',
    'review_notes',
    'reviewed_at',
])]
#[Hidden(['zip_path', 'scan_report'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * @return HasMany<ProjectReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProjectReview::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completeness' => ProjectCompleteness::class,
            'status' => ProjectStatus::class,
            'tech_stack' => 'array',
            'scan_report' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isPublished(): bool
    {
        return $this->status === ProjectStatus::Approved;
    }

    /**
     * Public-facing array shape for the storefront, including the private cover URL.
     *
     * @return array<string, mixed>
     */
    public function presentForMarketplace(): array
    {
        return [
            ...$this->only([
                'id',
                'seller_id',
                'title',
                'slug',
                'tagline',
                'description',
                'price_cents',
                'currency',
                'completeness',
                'status',
                'cover_image_path',
                'tech_stack',
                'review_notes',
                'reviewed_at',
                'created_at',
                'updated_at',
                'orders_count',
            ]),
            'cover_url' => $this->cover_image_path !== null
                ? route('projects.cover', $this)
                : null,
        ];
    }

    public function isPendingReview(): bool
    {
        return $this->status === ProjectStatus::PendingReview;
    }

    public function markApproved(?string $notes = null): void
    {
        $this->update([
            'status' => ProjectStatus::Approved,
            'review_notes' => $notes,
            'reviewed_at' => now(),
        ]);
    }

    public function markRejected(?string $notes = null): void
    {
        $this->update([
            'status' => ProjectStatus::Rejected,
            'review_notes' => $notes,
            'reviewed_at' => now(),
        ]);
    }
}
