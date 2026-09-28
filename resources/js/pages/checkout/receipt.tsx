import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    Clock,
    Download as DownloadIcon,
    FileArchive,
    SlidersHorizontal,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { issue as issueDownload } from '@/routes/downloads';
import { show as showProject } from '@/routes/projects';
import { formatPrice } from '@/types/projects';
import type { DownloadAudit, OrderReceipt, OrderStatus } from '@/types/orders';
import type { FlashDownload } from '@/types/ui';

type ReceiptProject = {
    id: number;
    title: string;
    slug: string;
    cover_url: string | null;
};

type ReceiptProps = {
    order: OrderReceipt;
    project: ReceiptProject;
    downloads?: DownloadAudit[];
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

export default function Receipt({
    order,
    project,
    downloads = [],
}: ReceiptProps) {
    const [activeDownload, setActiveDownload] = useState<FlashDownload | null>(
        null,
    );
    const [isGenerating, setIsGenerating] = useState(false);

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const download = flash?.download as FlashDownload | undefined;
            if (download) {
                setActiveDownload(download);
            }
        });
    }, []);

    const handleGenerateDownload = () => {
        setIsGenerating(true);
        router.post(
            issueDownload.url({ order: order.id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setIsGenerating(false),
            },
        );
    };
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
                    <CardContent className="space-y-4 py-6">
                        <div className="flex items-center justify-between">
                            <h2 className="font-medium flex items-center gap-2">
                                <FileArchive className="size-4 text-primary" />
                                Project Archive
                            </h2>
                            {order.can_download && (
                                <Badge variant="secondary" className="text-xs">
                                    Single-use links
                                </Badge>
                            )}
                        </div>

                        {order.status === 'paid' ? (
                            <div className="space-y-4">
                                {activeDownload ? (
                                    <div className="bg-primary/5 border-primary/20 rounded-lg border p-4 space-y-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-sm font-medium">
                                                    Your download link is ready!
                                                </p>
                                                <p className="text-muted-foreground text-xs mt-0.5 flex items-center gap-1.5">
                                                    <Clock className="size-3.5" />
                                                    Expires at{' '}
                                                    {new Date(
                                                        activeDownload.expires_at,
                                                    ).toLocaleTimeString()}
                                                </p>
                                            </div>
                                            <Button size="sm" asChild>
                                                <a
                                                    href={activeDownload.url}
                                                    download
                                                >
                                                    <DownloadIcon className="size-4 mr-1.5" />
                                                    Download ZIP
                                                </a>
                                            </Button>
                                        </div>
                                        <p className="text-muted-foreground text-xs">
                                            This link is valid for a single download. If interrupted or expired, you can generate another link below.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-muted/40 rounded-lg p-4">
                                        <div>
                                            <p className="text-sm font-medium">
                                                Ready to download {project.title}
                                            </p>
                                            <p className="text-muted-foreground text-xs mt-0.5">
                                                Generate an expiring signed link to securely fetch your project archive.
                                            </p>
                                        </div>
                                        <Button
                                            onClick={handleGenerateDownload}
                                            disabled={isGenerating || !order.can_download}
                                        >
                                            <DownloadIcon className="size-4 mr-1.5" />
                                            {isGenerating ? 'Preparing link...' : 'Get download link'}
                                        </Button>
                                    </div>
                                )}

                                {activeDownload && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handleGenerateDownload}
                                        disabled={isGenerating || !order.can_download}
                                    >
                                        Generate fresh link
                                    </Button>
                                )}

                                {downloads.length > 0 && (
                                    <div className="space-y-2 pt-2 border-t">
                                        <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Download History ({downloads.length})
                                        </h3>
                                        <div className="space-y-1.5">
                                            {downloads.map((d) => (
                                                <div
                                                    key={d.id}
                                                    className="flex items-center justify-between text-xs text-muted-foreground py-1 border-b border-dashed last:border-0"
                                                >
                                                    <span className="flex items-center gap-1.5">
                                                        {d.downloaded_at ? (
                                                            <CheckCircle2 className="size-3.5 text-primary" />
                                                        ) : (
                                                            <Clock className="size-3.5" />
                                                        )}
                                                        Token #{d.id}
                                                    </span>
                                                    <span>
                                                        {d.downloaded_at
                                                            ? `Downloaded ${new Date(d.downloaded_at).toLocaleString()}`
                                                            : `Generated ${new Date(d.created_at).toLocaleString()} (Unclaimed)`}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <AlertCircle className="size-4" />
                                <span>Downloads are only available once payment is confirmed.</span>
                            </div>
                        )}
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
