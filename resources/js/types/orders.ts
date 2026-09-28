export type OrderStatus =
    | 'pending'
    | 'paid'
    | 'refunded'
    | 'expired'
    | 'failed';

export type OrderReceipt = {
    id: number;
    status: OrderStatus;
    status_label: string;
    amount_cents: number;
    fee_percent: number;
    fee_amount_cents: number;
    payout_cents: number;
    currency: string;
    provider: string | null;
    can_download?: boolean;
    paid_at: string | null;
    created_at: string;
};

export type DownloadAudit = {
    id: number;
    downloaded_at: string | null;
    created_at: string;
};

export const orderStatusLabels: Record<OrderStatus, string> = {
    pending: 'Pending',
    paid: 'Paid',
    refunded: 'Refunded',
    expired: 'Expired',
    failed: 'Failed',
};
