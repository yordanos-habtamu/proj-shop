import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, PackageOpen, Store } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import { index as browse, show } from '@/routes/projects';
import {
    completenessLabels,
    formatPrice,
    type ProjectListing,
} from '@/types/projects';

type WelcomeProps = {
    projects: ProjectListing[];
};

export default function Welcome({ projects }: WelcomeProps) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />

            <div className="bg-background flex min-h-screen flex-col">
                <header className="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-4">
                    <Link href={browse()} prefetch>
                        <AppLogo />
                    </Link>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" size="sm" asChild>
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button size="sm" asChild>
                                    <Link href={register()}>Register</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto w-full max-w-6xl flex-1 px-4">
                    <section className="flex flex-col items-center gap-6 py-20 text-center">
                        <div className="bg-muted inline-flex items-center gap-2 rounded-full px-3 py-1 font-mono text-xs">
                            <span className="text-primary">●</span>
                            every upload is scanned and reviewed
                        </div>
                        <h1 className="max-w-2xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                            Buy and sell projects, built by developers like you.
                        </h1>
                        <p className="text-muted-foreground max-w-xl text-pretty">
                            Discover vetted code — from quick MVPs to complete
                            SaaS starters. Upload your work, get it reviewed,
                            and get paid directly when it sells.
                        </p>
                        <div className="flex flex-wrap items-center justify-center gap-3">
                            <Button size="lg" asChild>
                                <Link href={browse()}>
                                    <Store />
                                    Browse the marketplace
                                </Link>
                            </Button>
                            <Button size="lg" variant="outline" asChild>
                                <Link
                                    href={
                                        auth.user
                                            ? dashboard()
                                            : register({
                                                  query: { role: 'seller' },
                                              })
                                    }
                                >
                                    {auth.user
                                        ? 'Go to dashboard'
                                        : 'Start selling'}
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </div>
                    </section>

                    <section className="pb-20">
                        <div className="mb-6 flex items-end justify-between">
                            <div>
                                <h2 className="text-xl font-semibold tracking-tight">
                                    Live on Prodhunt
                                </h2>
                                <p className="text-muted-foreground text-sm">
                                    Recently approved projects on the market.
                                </p>
                            </div>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={browse()}>
                                    See all
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </div>

                        {projects.length === 0 && (
                            <div className="bg-muted flex flex-col items-center gap-3 rounded-lg border px-6 py-16 text-center">
                                <PackageOpen className="text-muted-foreground size-8" />
                                <p className="font-medium">
                                    The first projects are being reviewed
                                </p>
                                <p className="text-muted-foreground max-w-sm text-sm">
                                    Listings appear here as soon as they are
                                    approved by the team.
                                </p>
                            </div>
                        )}

                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            {projects.map((project) => (
                                <Link
                                    key={project.id}
                                    href={show.url({ project: project.slug })}
                                    className="group overflow-hidden rounded-lg border transition-shadow hover:shadow-md"
                                >
                                    <div className="bg-muted flex h-36 items-center justify-center overflow-hidden">
                                        {project.cover_url ? (
                                            <img
                                                src={project.cover_url}
                                                alt={project.title}
                                                className="h-full w-full object-cover"
                                                loading="lazy"
                                            />
                                        ) : (
                                            <PackageOpen className="text-muted-foreground size-7" />
                                        )}
                                    </div>
                                    <div className="space-y-2 p-4">
                                        <div className="flex items-start justify-between gap-2">
                                            <h3 className="font-semibold group-hover:underline">
                                                {project.title}
                                            </h3>
                                            <p className="shrink-0 font-semibold">
                                                {formatPrice(
                                                    project.price_cents,
                                                    project.currency,
                                                )}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge variant="outline">
                                                {
                                                    completenessLabels[
                                                        project.completeness
                                                    ]
                                                }
                                            </Badge>
                                            <span className="text-muted-foreground text-xs">
                                                {project.seller?.name ??
                                                    'Seller'}{' '}
                                                · {project.orders_count ?? 0}{' '}
                                                sale
                                                {(project.orders_count ?? 0) ===
                                                1
                                                    ? ''
                                                    : 's'}
                                            </span>
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                </main>

                <footer className="border-t">
                    <div className="text-muted-foreground mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 py-6 text-sm sm:flex-row">
                        <p>
                            © {new Date().getFullYear()} Prodhunt — a
                            marketplace for developers.
                        </p>
                        <p className="font-mono text-xs">buy · sell · ship</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
