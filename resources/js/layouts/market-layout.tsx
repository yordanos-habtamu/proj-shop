import { Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import { index as browse } from '@/routes/projects';
import type { NavItem } from '@/types';

const navItems: NavItem[] = [
    {
        title: 'Browse',
        href: browse(),
    },
];

export default function MarketLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const { auth } = usePage<{
        auth: { user: { name?: string } | null };
    }>().props;

    return (
        <div className="bg-background min-h-screen">
            <header className="bg-card/80 sticky top-0 z-20 border-b backdrop-blur">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                    <div className="flex items-center gap-6">
                        <Link href={browse()} prefetch>
                            <AppLogo />
                        </Link>
                        <nav className="hidden items-center gap-1 sm:flex">
                            {navItems.map((item) => (
                                <Button
                                    key={item.title}
                                    variant="ghost"
                                    size="sm"
                                    asChild
                                >
                                    <Link href={item.href}>{item.title}</Link>
                                </Button>
                            ))}
                        </nav>
                    </div>

                    <div className="flex items-center gap-2">
                        {auth.user ? (
                            <Button size="sm" asChild>
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
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-6xl px-4 py-8">{children}</main>

            <footer className="border-t">
                <div className="text-muted-foreground mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 py-6 text-sm sm:flex-row">
                    <p>
                        © {new Date().getFullYear()} Prodhunt — a marketplace
                        for developers.
                    </p>
                    <p className="font-mono text-xs">buy · sell · ship</p>
                </div>
            </footer>
        </div>
    );
}
