import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    FolderGit2,
    LayoutGrid,
    Package,
    Percent,
    ShieldCheck,
    Store,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as feesIndex } from '@/routes/admin/fees';
import { show as connectShow } from '@/routes/connect';
import { index as browse, mine, review } from '@/routes/projects';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Marketplace',
        href: browse(),
        icon: Store,
    },
];

const sellerNavItems: NavItem[] = [
    {
        title: 'My Projects',
        href: mine(),
        icon: Package,
    },
    {
        title: 'Connect Stripe',
        href: connectShow(),
        icon: Wallet,
    },
];

const reviewerNavItems: NavItem[] = [
    {
        title: 'Review queue',
        href: review(),
        icon: ShieldCheck,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Platform fees',
        href: feesIndex(),
        icon: Percent,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { props } = usePage<{
        auth: { user: { role?: string } };
    }>();

    const canSell = ['seller', 'reviewer', 'admin'].includes(
        props.auth.user.role ?? '',
    );

    const canReview = ['reviewer', 'admin'].includes(
        props.auth.user.role ?? '',
    );

    const isAdmin = props.auth.user.role === 'admin';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain
                    items={[
                        ...mainNavItems,
                        ...(canSell ? sellerNavItems : []),
                        ...(canReview ? reviewerNavItems : []),
                        ...(isAdmin ? adminNavItems : []),
                    ]}
                />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
