<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Delisted = 'delisted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Delisted => 'Delisted',
        };
    }
}
