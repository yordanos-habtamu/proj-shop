import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ShieldAlert, SlidersHorizontal } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { demoPay } from '@/routes/checkout';
import { formatPrice } from '@/types/projects';

type DemoOrder = {
    id: number;
    amount_cents: number;
    fee_percent: number | null;
    fee_amount_cents: number | null;
    payout_cents: number | null;
    currency: string;
};

type DemoProject = {
    id: number;
    title: string;
    slug: string;
};

type DemoCheckoutProps = {
    order: DemoOrder;
    project: DemoProject;
};

export default function DemoCheckout({ order, project }: DemoCheckoutProps) {
    return (
        <>
            <Head title="Checkout" />

            <Heading
                title="Complete your purchase"
                description={project.title ?? `Project #${project.id}`}
            />

            <Card>
                <CardContent className="space-y-6 py-6">
                    <div className="space-y-3">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">Price</span>
                            <span className="font-medium">
                                {formatPrice(
                                    order.amount_cents,
                                    order.currency,
                                )}
                            </span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground flex items-center gap-1.5">
                                <SlidersHorizontal className="size-3.5" />
                                Platform fee ({order.fee_percent ?? 0}%)
                            </span>
                            <span className="font-medium">
                                −{' '}
                                {formatPrice(
                                    order.fee_amount_cents ?? 0,
                                    order.currency,
                                )}
                            </span>
                        </div>
                        <div className="flex items-center justify-between border-t pt-3 text-sm">
                            <span className="text-muted-foreground">
                                Seller payout
                            </span>
                            <span className="font-medium">
                                {formatPrice(
                                    order.payout_cents ?? 0,
                                    order.currency,
                                )}
                            </span>
                        </div>
                    </div>

                    <div className="bg-muted flex items-start gap-2 rounded-md p-3 text-sm">
                        <ShieldAlert className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                        <p className="text-muted-foreground">
                            Demo checkout (STRIPE_ENABLED=false). No real
                            payment is taken — this confirms the order and marks
                            it paid.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href={showUrl(project.slug)}>
                                <ArrowLeft />
                                Cancel
                            </Link>
                        </Button>
                        <Button
                            onClick={() =>
                                router.post(demoPay.url({ order: order.id }))
                            }
                            data-test="demo-pay-button"
                        >
                            Confirm demo payment
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </>
    );
}

function showUrl(slug: string): string {
    return `/projects/${slug}`;
}
