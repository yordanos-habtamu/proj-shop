import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, SlidersHorizontal } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { show as showProject } from '@/routes/projects';
import { formatPrice } from '@/types/projects';
import type { OrderReceipt, OrderStatus } from '@/types/orders';

type ReceiptProject = {
    id: number;
    title: string;
    slug: string;
    cover_url: string | null;
};

type ReceiptProps = {
    order: OrderReceipt;
    project: ReceiptProject;
};

const statusVariant: Record<
    OrderStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    paid: 'default',
    pending: 'secondary',
    failed: 'destructive',
    expired: 'outline',
    refunded: 'destructive',
};

const statusNote: Record<OrderStatus, string> = {
    paid: 'Payment confirmed. Thank you for your purchase.',
    pending: 'This order is awaiting payment.',
    expired:
        'This checkout session expired before it was paid. Start a new purchase from the project page.',
    failed: 'The payment for this order failed. Start a new purchase from the project page.',
    refunded: 'This order was refunded.',
};

export default function Receipt({ order, project }: ReceiptProps) {
    return (
        <>
            <Head title={`Order #${order.id}`} />

            <Heading
                title={`Order #${order.id}`}
                description={`Receipt for ${project.title}`}
            />

            <div className="flex flex-col gap-6">
                <Card>
                    <CardContent className="space-y-4 py-6">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Badge variant={statusVariant[order.status]}>
                                {order.status_label}
                            </Badge>
                            <span className="text-muted-foreground text-sm">
                                {formatPrice(
                                    order.amount_cents,
                                    order.currency,
                                )}
                            </span>
                        </div>

                        <div className="space-y-3">
                            <Row label="Price">
                                {formatPrice(
                                    order.amount_cents,
                                    order.currency,
                                )}
                            </Row>
                            <Row
                                label={
                                    <span className="flex items-center gap-1.5">
                                        <SlidersHorizontal className="size-3.5" />
                                        Platform fee ({order.fee_percent}%)
                                    </span>
                                }
                            >
                                −{' '}
                                {formatPrice(
                                    order.fee_amount_cents,
                                    order.currency,
                                )}
                            </Row>
                            <Row label="Seller payout">
                                {formatPrice(
                                    order.payout_cents,
                                    order.currency,
                                )}
                            </Row>
                        </div>

                        <div className="text-muted-foreground flex flex-wrap gap-x-6 gap-y-1 text-xs">
                            <span>
                                Placed{' '}
                                {new Date(order.created_at).toLocaleString()}
                            </span>
                            {order.provider && (
                                <span>Via {order.provider}</span>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="space-y-2 py-6">
                        <h2 className="font-medium">Status</h2>
                        <p className="text-muted-foreground text-sm">
                            {statusNote[order.status]}
                        </p>
                    </CardContent>
                </Card>

                <div>
                    <Button variant="outline" asChild>
                        <Link href={showProject.url(project.slug)}>
                            <ArrowLeft />
                            Back to project
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

function Row({
    label,
    children,
}: {
    label: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-center justify-between text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{children}</span>
        </div>
    );
}
