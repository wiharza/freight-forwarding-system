import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, Box, CircleDollarSign, Database, Folder, LayoutGrid, Receipt, Truck, Users } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Daftar Job',
        url: '/jobs',
        icon: Box,
    },
    {
        title: 'Trip Trucking',
        url: '/trips',
        icon: Truck,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Manajemen User',
        url: '/admin/users',
        icon: Users,
    },
    {
        title: 'Master Data',
        url: '/admin/master/importirs',
        icon: Database,
    },
];

const financeNavItems: NavItem[] = [
    {
        title: 'Kurs Internal',
        url: '/finance/rates',
        icon: CircleDollarSign,
    },
    {
        title: 'Invoice',
        url: '/finance/invoices',
        icon: Receipt,
    },
    {
        title: 'Eksposur Importir',
        url: '/finance/exposure',
        icon: CircleDollarSign,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        url: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        url: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const roles = (usePage().props.roles as string[] | undefined) ?? [];
    const isAdmin = roles.includes('admin');
    const isFinance = roles.includes('finance');

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                {isAdmin && <NavMain items={adminNavItems} />}
                {isFinance && <NavMain items={financeNavItems} />}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
