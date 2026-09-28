import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    PackageOpen,
    ShieldCheck,
    ShoppingCart,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { login } from '@/routes';
import { store as checkoutStore } from '@/routes/checkout';
import { show as showOrder } from '@/routes/orders';
import { index as browse, mine } from '@/routes/projects';
import {
    completenessLabels,
    formatPrice,
    type ProjectListing,
} from '@/types/projects';

type ShowProps = {
    project: ProjectListing;
    is_owner: boolean;
    is_bought: boolean;
    order_id?: number | null;
    can_buy: boolean;
};

export default function ProjectShow({
    project,
    is_owner,
    is_bought,
    order_id,
    can_buy,
}: ShowProps) {
    const { auth } = usePage<{
        auth: { user: { id?: number; name?: string } | null };
    }>().props;

    const hasCover = Boolean(project.cover_url);

    return (
        <>
            <Head title={project.title} />

            <Link
                href={browse()}
                className="text-muted-foreground hover:text-foreground mb-6 inline-flex items-center gap-1.5 text-sm"
            >
                <ArrowLeft className="size-4" />
                Back to marketplace
            </Link>

            <div className="grid gap-8 lg:grid-cols-[1fr_360px]">
                <div className="space-y-6">
                    <div className="bg-muted flex h-72 items-center justify-center overflow-hidden rounded-lg border">
                        {hasCover ? (
                            <img
                                src={project.cover_url ?? undefined}
                                alt={`Cover for ${project.title}`}
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <PackageOpen className="text-muted-foreground size-10" />
                        )}
                    </div>

                    <div className="space-y-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">
                                {completenessLabels[project.completeness]}
                            </Badge>
                            {is_owner && (
                                <Badge variant="secondary">Your listing</Badge>
                            )}
                            {project.seller?.name && (
                                <span className="text-muted-foreground text-sm">
                                    by {project.seller.name}
                                </span>
                            )}
                        </div>

                        <h1 className="text-3xl font-semibold tracking-tight">
                            {project.title}
                        </h1>

                        {project.tagline && (
                            <p className="text-muted-foreground">
                                {project.tagline}
                            </p>
                        )}

                        <p className="text-sm leading-relaxed whitespace-pre-line">
                            {project.description}
                        </p>

                        {project.tech_stack &&
                            project.tech_stack.length > 0 && (
                                <div className="flex flex-wrap gap-1.5 pt-2">
                                    {project.tech_stack.map((tag) => (
                                        <span
                                            key={tag}
                                            className="bg-muted rounded px-1.5 py-0.5 font-mono text-xs"
                                        >
                                            {tag}
                                        </span>
                                    ))}
                                </div>
                            )}
                    </div>
                </div>

                <aside className="space-y-4">
                    <Card>
                        <CardContent className="space-y-4 py-5">
                            <p className="text-3xl font-semibold">
                                {formatPrice(
                                    project.price_cents,
                                    project.currency,
                                )}
                            </p>

                            {is_owner ? (
                                <div className="bg-muted rounded-md p-3 text-sm">
                                    <p className="font-medium">Your listing</p>
                                    <p className="text-muted-foreground mt-1">
                                        Manage it from My Projects.
                                    </p>
                                    <Button
                                        className="mt-3"
                                        variant="outline"
                                        size="sm"
                                        asChild
                                    >
                                        <Link href={mine()}>
                                            Manage listing
                                        </Link>
                                    </Button>
                                </div>
                            ) : is_bought ? (
                                <div className="bg-muted rounded-md p-3 text-sm">
                                    <p className="font-medium">Purchased</p>
                                    <p className="text-muted-foreground mt-1">
                                        Your copy is waiting on your receipt
                                        page.
                                    </p>
                                    {order_id && (
                                        <Button
                                            className="mt-3 w-full"
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={showOrder.url(order_id)}>
                                                Open receipt
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            ) : !auth.user ? (
                                <>
                                    <Button className="w-full" asChild>
                                        <Link href={login()}>
                                            Log in to purchase
                                        </Link>
                                    </Button>
                                    <p className="text-muted-foreground text-xs">
                                        You need an account to buy a project.
                                    </p>
                                </>
                            ) : (
                                <>
                                    <Button
                                        className="w-full"
                                        disabled={!can_buy}
                                        onClick={() =>
                                            can_buy &&
                                            router.post(
                                                checkoutStore.url({
                                                    project: project.id,
                                                }),
                                            )
                                        }
                                    >
                                        <ShoppingCart />
                                        Buy now
                                    </Button>
                                    <p className="text-muted-foreground text-xs">
                                        {can_buy
                                            ? 'You will complete payment with Stripe Checkout.'
                                            : 'Checkout is not available for this project.'}
                                    </p>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="space-y-3 py-5">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-sm">
                                    Seller
                                </span>
                                <span className="text-sm font-medium">
                                    {project.seller?.name ??
                                        `#${project.seller_id}`}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-sm">
                                    Sales
                                </span>
                                <span className="text-sm font-medium">
                                    {project.orders_count ?? 0}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-sm">
                                    Published
                                </span>
                                <span className="text-sm font-medium">
                                    {new Date(
                                        project.created_at,
                                    ).toLocaleDateString()}
                                </span>
                            </div>
                            <div className="flex items-center gap-2 border-t pt-3 text-xs">
                                <ShieldCheck className="text-primary size-4" />
                                <p className="text-muted-foreground">
                                    Scanned and human-approved before listing.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </>
    );
}
