import { type BreadcrumbItem, type NavItem } from '@/types';
import { PlugIcon, SettingsIcon, UsersIcon } from 'lucide-react';
import { ReactNode } from 'react';
import Layout from '@/layouts/app/layout';

const sidebarNavItems: NavItem[] = [
  {
    title: 'Users',
    href: route('users'),
    icon: UsersIcon,
  },
  {
    title: 'Plugins',
    href: route('plugins'),
    icon: PlugIcon,
  },
  {
    title: 'System Settings',
    href: route('vito-settings'),
    icon: SettingsIcon,
  },
];

export default function SettingsLayout({ children, breadcrumbs }: { children: ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
  // When server-side rendering, we only render the layout on the client...
  if (typeof window === 'undefined') {
    return null;
  }

  return (
    <Layout breadcrumbs={breadcrumbs} secondNavItems={sidebarNavItems} secondNavTitle="Admin">
      {children}
    </Layout>
  );
}
