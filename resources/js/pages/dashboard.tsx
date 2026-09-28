import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    DollarSign,
    Download,
    FileArchive,
    Layers,
    Package,
    PackagePlus,
    Receipt,
    ShieldAlert,
    ShoppingBag,
    Store,
    TrendingUp,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { show as connectShow } from '@/routes/connect';
import { show as showOrder } from '@/routes/orders';
import {
    create as createProject,
    index as browse,
    mine as myProjects,
    review as reviewQueue,
    show as showProject,
} from '@/routes/projects';
import {
    formatPrice,
    type ProjectCompleteness,
    type ProjectStatus,
} from '@/types/projects';

export type LibraryItem = {
    order_id: number;
    purchased_at: string;
    amount_cents: number;
    currency: string;
    can_download: boolean;
    has_downloaded: boolean;
    project: {
        id: number;
        title: string;
        slug: string;
        tagline: string | null;
        completeness: ProjectCompleteness;
        completeness_label: string;
        tech_stack: string[];
        seller_name: string;
        cover_url: string | null;
    };
};

export type SellerRecentSale = {
    id: number;
    project_id: number;
    project_title: string;
    project_slug: string;
    buyer_name: string;
    amount_cents: number;
    fee_amount_cents: number;
    payout_cents: number;
    currency: string;
    created_at: string;
};

export type SellerProjectItem = {
    id: number;
    title: string;
    slug: string;
    status: ProjectStatus;
    status_label: string;
    price_cents: number;
    currency: string;
    sales_count: number;
    gross_volume_cents: number;
    net_payout_cents: number;
};

export type SellerMetrics = {
    total_sales_count: number;
    gross_revenue_cents: number;
    net_payout_cents: number;
    projects_count: number;
    published_count: number;
    pending_count: number;
    draft_count: number;
};

export type SellerData = {
    stripe_connected: boolean;
    stripe_connect_status: string | null;
    metrics: SellerMetrics;
    recent_sales: SellerRecentSale[];
    projects: SellerProjectItem[];
};

export type DashboardProps = {
    library: LibraryItem[];
    seller: SellerData | null;
    is_seller: boolean;
    pending_reviews_count: number;
};

const completenessBadgeVariant: Record<
    ProjectCompleteness,
    'default' | 'secondary' | 'outline'
> = {
    concept: 'outline',
    starter: 'secondary',
    mvp: 'secondary',
    complete: 'default',
};

const statusBadgeVariant: Record<
    ProjectStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    draft: 'outline',
    pending_review: 'secondary',
    approved: 'default',
    rejected: 'destructive',
    delisted: 'outline',
};

export default function Dashboard({
    library,
    seller,
    is_seller,
    pending_reviews_count,
}: DashboardProps) {
    const { auth } = usePage<{
        auth: { user: { id: number; name: string; email: string } };
    }>().props;

    const [activeTab, setActiveTab] = useState<'library' | 'seller'>(
        library.length === 0 && is_seller ? 'seller' : 'library',
    );

    return (
        <>
            <Head title="Dashboard" />

            <div className="space-y-6">
                {/* Header with Quick Actions */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <Heading
                            title={`Welcome back, ${auth.user?.name ?? 'Creator'}`}
                            description="Access your project library, download archives, and view earnings"
                        />
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={browse()}>
                                <Store className="mr-1.5 size-4" />
                                Browse Marketplace
                            </Link>
                        </Button>
                        {is_seller && (
                            <Button size="sm" asChild>
                                <Link href={createProject()}>
                                    <PackagePlus className="mr-1.5 size-4" />
                                    Upload Project
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Reviewer Alert Banner */}
                {pending_reviews_count > 0 && (
                    <div className="border-primary/20 bg-primary/5 flex flex-col justify-between gap-3 rounded-lg border p-4 text-sm sm:flex-row sm:items-center">
                        <div className="flex items-center gap-2.5">
                            <ShieldAlert className="text-primary size-5" />
                            <div>
                                <p className="text-foreground font-medium">
                                    {pending_reviews_count}{' '}
                                    {pending_reviews_count === 1
                                        ? 'project is'
                                        : 'projects are'}{' '}
                                    waiting in the moderation queue
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    Automated security scans are ready for human
                                    verification.
                                </p>
                            </div>
                        </div>
                        <Button size="sm" variant="default" asChild>
                            <Link href={reviewQueue()}>
                                Open Review Queue
                                <ArrowRight className="ml-1.5 size-3.5" />
                            </Link>
                        </Button>
                    </div>
                )}

                {/* Seller Stripe Onboarding Banner */}
                {is_seller && seller && !seller.stripe_connected && (
                    <div className="border-accent/30 bg-accent/10 flex flex-col justify-between gap-3 rounded-lg border p-4 text-sm sm:flex-row sm:items-center">
                        <div className="flex items-center gap-2.5">
                            <Wallet className="text-accent-foreground size-5" />
                            <div>
                                <p className="text-accent-foreground font-medium">
                                    Connect Stripe to receive payouts
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    Buyers pay directly to your connected
                                    account using Stripe Connect.
                                </p>
                            </div>
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            className="border-accent/40 bg-background"
                            asChild
                        >
                            <Link href={connectShow()}>
                                Connect Stripe
                                <ArrowRight className="ml-1.5 size-3.5" />
                            </Link>
                        </Button>
                    </div>
                )}

                {/* Navigation Tabs (if seller or has purchases) */}
                {is_seller && (
                    <div className="border-border/80 flex items-center border-b">
                        <button
                            id="tab-library"
                            onClick={() => setActiveTab('library')}
                            className={`flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors ${
                                activeTab === 'library'
                                    ? 'border-primary text-foreground'
                                    : 'text-muted-foreground hover:text-foreground border-transparent'
                            }`}
                        >
                            <ShoppingBag className="size-4" />
                            <span>My Purchases</span>
                            <Badge
                                variant="secondary"
                                className="font-mono text-xs"
                            >
                                {library.length}
                            </Badge>
                        </button>
                        <button
                            id="tab-seller"
                            onClick={() => setActiveTab('seller')}
                            className={`flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors ${
                                activeTab === 'seller'
                                    ? 'border-primary text-foreground'
                                    : 'text-muted-foreground hover:text-foreground border-transparent'
                            }`}
                        >
                            <TrendingUp className="size-4" />
                            <span>Seller Studio</span>
                            {seller && (
                                <Badge
                                    variant="secondary"
                                    className="font-mono text-xs"
                                >
                                    {seller.metrics.total_sales_count} sales
                                </Badge>
                            )}
                        </button>
                    </div>
                )}

                {/* 1. BUYER LIBRARY TAB */}
                {(!is_seller || activeTab === 'library') && (
                    <section id="buyer-library" className="space-y-4">
                        {library.length === 0 ? (
                            <Card className="border-dashed py-12 text-center">
                                <CardContent className="space-y-3">
                                    <div className="bg-primary/10 text-primary mx-auto flex size-12 items-center justify-center rounded-full">
                                        <ShoppingBag className="size-6" />
                                    </div>
                                    <h3 className="text-lg font-semibold">
                                        Your library is empty
                                    </h3>
                                    <p className="text-muted-foreground mx-auto max-w-md text-sm">
                                        You haven't purchased any projects yet.
                                        Discover curated starters, boilerplates,
                                        and production-ready MVPs.
                                    </p>
                                    <Button className="mt-2" asChild>
                                        <Link href={browse()}>
                                            <Store className="mr-2 size-4" />
                                            Explore Marketplace
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        ) : (
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                                {library.map((item) => (
                                    <Card
                                        key={item.order_id}
                                        className="group border-border/80 flex flex-col justify-between overflow-hidden transition-shadow hover:shadow-md"
                                    >
                                        <div>
                                            {/* Project Cover / Header */}
                                            {item.project.cover_url ? (
                                                <div className="bg-muted aspect-video w-full overflow-hidden">
                                                    <img
                                                        src={
                                                            item.project
                                                                .cover_url
                                                        }
                                                        alt={item.project.title}
                                                        className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                    />
                                                </div>
                                            ) : (
                                                <div className="from-primary/5 via-muted to-primary/10 flex aspect-video w-full items-center justify-center bg-gradient-to-br p-6">
                                                    <FileArchive className="text-primary/40 size-10" />
                                                </div>
                                            )}

                                            <CardContent className="space-y-3 pt-4">
                                                <div className="flex items-center justify-between gap-2">
                                                    <Badge
                                                        variant={
                                                            completenessBadgeVariant[
                                                                item.project
                                                                    .completeness
                                                            ]
                                                        }
                                                        className="text-xs"
                                                    >
                                                        {
                                                            item.project
                                                                .completeness_label
                                                        }
                                                    </Badge>
                                                    <span className="text-muted-foreground font-mono text-xs">
                                                        Order #{item.order_id}
                                                    </span>
                                                </div>

                                                <div>
                                                    <h3 className="text-foreground leading-tight font-semibold">
                                                        <Link
                                                            href={showProject.url(
                                                                item.project
                                                                    .slug,
                                                            )}
                                                            className="hover:text-primary inline-flex items-center gap-1 transition-colors"
                                                        >
                                                            {item.project.title}
                                                            <ArrowUpRight className="size-3.5 opacity-60" />
                                                        </Link>
                                                    </h3>
                                                    {item.project.tagline && (
                                                        <p className="text-muted-foreground mt-1 line-clamp-2 text-xs">
                                                            {
                                                                item.project
                                                                    .tagline
                                                            }
                                                        </p>
                                                    )}
                                                </div>

                                                {/* Tech Stack Chips */}
                                                {item.project.tech_stack &&
                                                    item.project.tech_stack
                                                        .length > 0 && (
                                                        <div className="flex flex-wrap gap-1 pt-1">
                                                            {item.project.tech_stack
                                                                .slice(0, 4)
                                                                .map((tech) => (
                                                                    <span
                                                                        key={
                                                                            tech
                                                                        }
                                                                        className="bg-muted text-muted-foreground rounded px-1.5 py-0.5 font-mono text-[10px]"
                                                                    >
                                                                        {tech}
                                                                    </span>
                                                                ))}
                                                            {item.project
                                                                .tech_stack
                                                                .length > 4 && (
                                                                <span className="text-muted-foreground text-[10px]">
                                                                    +
                                                                    {item
                                                                        .project
                                                                        .tech_stack
                                                                        .length -
                                                                        4}
                                                                </span>
                                                            )}
                                                        </div>
                                                    )}

                                                <div className="border-border/60 text-muted-foreground flex items-center justify-between border-t pt-3 text-xs">
                                                    <span>
                                                        By{' '}
                                                        {
                                                            item.project
                                                                .seller_name
                                                        }
                                                    </span>
                                                    <span>
                                                        {formatPrice(
                                                            item.amount_cents,
                                                            item.currency,
                                                        )}
                                                    </span>
                                                </div>
                                            </CardContent>
                                        </div>

                                        <div className="border-border/80 flex items-center gap-2 border-t p-4 pt-3">
                                            <Button
                                                size="sm"
                                                className="w-full gap-1.5"
                                                asChild
                                            >
                                                <Link
                                                    href={showOrder.url(
                                                        item.order_id,
                                                    )}
                                                >
                                                    <Download className="size-4" />
                                                    Download ZIP
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={showOrder.url(
                                                        item.order_id,
                                                    )}
                                                    title="View Receipt"
                                                >
                                                    <Receipt className="size-4" />
                                                </Link>
                                            </Button>
                                        </div>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </section>
                )}

                {/* 2. SELLER STUDIO TAB */}
                {is_seller && activeTab === 'seller' && seller && (
                    <section id="seller-studio" className="space-y-6">
                        {/* KPI Metrics Grid */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-muted-foreground text-xs font-medium tracking-wider uppercase">
                                        Gross Volume
                                    </CardTitle>
                                    <DollarSign className="text-primary size-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {formatPrice(
                                            seller.metrics.gross_revenue_cents,
                                        )}
                                    </div>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Total transaction volume
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-muted-foreground text-xs font-medium tracking-wider uppercase">
                                        Net Payouts
                                    </CardTitle>
                                    <Wallet className="text-primary size-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {formatPrice(
                                            seller.metrics.net_payout_cents,
                                        )}
                                    </div>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Earned after platform fees
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-muted-foreground text-xs font-medium tracking-wider uppercase">
                                        Completed Sales
                                    </CardTitle>
                                    <ShoppingBag className="text-primary size-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {seller.metrics.total_sales_count}
                                    </div>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Across all active listings
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-muted-foreground text-xs font-medium tracking-wider uppercase">
                                        Projects Listed
                                    </CardTitle>
                                    <Layers className="text-primary size-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {seller.metrics.published_count}
                                    </div>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {seller.metrics.pending_count > 0 &&
                                            `${seller.metrics.pending_count} pending review • `}
                                        {seller.metrics.projects_count} total
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Two Columns: Recent Sales & Project Performance */}
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                            {/* Left: Recent Transactions Feed (2 cols) */}
                            <Card className="lg:col-span-2">
                                <CardHeader className="flex flex-row items-center justify-between pb-4">
                                    <div>
                                        <CardTitle className="text-base font-semibold">
                                            Recent Sales
                                        </CardTitle>
                                        <CardDescription>
                                            Real-time sales made on your
                                            published projects
                                        </CardDescription>
                                    </div>
                                    <Badge
                                        variant="outline"
                                        className="text-xs"
                                    >
                                        {seller.recent_sales.length} latest
                                    </Badge>
                                </CardHeader>
                                <CardContent>
                                    {seller.recent_sales.length === 0 ? (
                                        <div className="text-muted-foreground py-8 text-center text-sm">
                                            <ShoppingBag className="mx-auto mb-2 size-8 opacity-40" />
                                            <p className="font-medium">
                                                No sales yet
                                            </p>
                                            <p className="mx-auto mt-1 max-w-sm text-xs">
                                                Once buyers purchase your
                                                verified projects, each order
                                                and direct payout will appear
                                                here.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="divide-border/60 divide-y">
                                            {seller.recent_sales.map((sale) => (
                                                <div
                                                    key={sale.id}
                                                    className="flex flex-col justify-between gap-2 py-3 text-sm sm:flex-row sm:items-center"
                                                >
                                                    <div>
                                                        <Link
                                                            href={showProject.url(
                                                                sale.project_slug,
                                                            )}
                                                            className="hover:text-primary font-medium transition-colors"
                                                        >
                                                            {sale.project_title}
                                                        </Link>
                                                        <div className="text-muted-foreground mt-0.5 flex items-center gap-2 text-xs">
                                                            <span>
                                                                Buyer:{' '}
                                                                {
                                                                    sale.buyer_name
                                                                }
                                                            </span>
                                                            <span>•</span>
                                                            <span>
                                                                {new Date(
                                                                    sale.created_at,
                                                                ).toLocaleDateString()}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div className="text-right">
                                                        <div className="text-foreground font-semibold">
                                                            +
                                                            {formatPrice(
                                                                sale.payout_cents,
                                                                sale.currency,
                                                            )}
                                                        </div>
                                                        <div className="text-muted-foreground text-xs">
                                                            Gross:{' '}
                                                            {formatPrice(
                                                                sale.amount_cents,
                                                                sale.currency,
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Right: Project Portfolio Overview (1 col) */}
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-4">
                                    <div>
                                        <CardTitle className="text-base font-semibold">
                                            Your Projects
                                        </CardTitle>
                                        <CardDescription>
                                            Performance by listing
                                        </CardDescription>
                                    </div>
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link
                                            href={myProjects()}
                                            className="text-xs"
                                        >
                                            View all
                                        </Link>
                                    </Button>
                                </CardHeader>
                                <CardContent>
                                    {seller.projects.length === 0 ? (
                                        <div className="text-muted-foreground py-6 text-center text-sm">
                                            <Package className="mx-auto mb-2 size-7 opacity-40" />
                                            <p className="font-medium">
                                                No projects created
                                            </p>
                                            <Button
                                                size="sm"
                                                className="mt-3"
                                                asChild
                                            >
                                                <Link href={createProject()}>
                                                    Upload Project
                                                </Link>
                                            </Button>
                                        </div>
                                    ) : (
                                        <div className="space-y-3.5">
                                            {seller.projects
                                                .slice(0, 5)
                                                .map((project) => (
                                                    <div
                                                        key={project.id}
                                                        className="border-border/70 space-y-1.5 rounded-lg border p-3 text-xs"
                                                    >
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span className="text-foreground max-w-[160px] truncate font-semibold">
                                                                {project.title}
                                                            </span>
                                                            <Badge
                                                                variant={
                                                                    statusBadgeVariant[
                                                                        project
                                                                            .status
                                                                    ]
                                                                }
                                                                className="px-1.5 py-0 font-mono text-[10px] uppercase"
                                                            >
                                                                {
                                                                    project.status_label
                                                                }
                                                            </Badge>
                                                        </div>
                                                        <div className="text-muted-foreground flex items-center justify-between border-t border-dashed pt-1">
                                                            <span>
                                                                {formatPrice(
                                                                    project.price_cents,
                                                                    project.currency,
                                                                )}
                                                            </span>
                                                            <span>
                                                                {
                                                                    project.sales_count
                                                                }{' '}
                                                                {project.sales_count ===
                                                                1
                                                                    ? 'sale'
                                                                    : 'sales'}{' '}
                                                                (
                                                                {formatPrice(
                                                                    project.net_payout_cents,
                                                                    project.currency,
                                                                )}
                                                                )
                                                            </span>
                                                        </div>
                                                    </div>
                                                ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
