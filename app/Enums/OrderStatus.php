<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Expired = 'expired';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Refunded => 'Refunded',
            self::Expired => 'Expired',
            self::Failed => 'Failed',
        };
    }
}
