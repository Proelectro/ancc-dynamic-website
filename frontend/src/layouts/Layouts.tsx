import {
  CalendarDays,
  Gift,
  LayoutDashboard,
  ScrollText,
  Trophy,
  User as UserIcon,
  Users,
} from "lucide-react";
import type { ReactNode } from "react";
import { NavLink, Outlet } from "react-router-dom";

import { Navbar } from "@/components/layout/Navbar";
import { Badge } from "@/components/ui";
import { cn } from "@/lib/utils";

function Footer() {
  return (
    <footer className="mt-16 border-t border-border">
      <div className="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-6 text-sm text-muted sm:flex-row sm:justify-between">
        <p>
          <span className="font-mono">ANCC</span> · Algorithm and Coding Club, IIT Delhi
        </p>
        <p>
          Sponsored by <span className="text-fg">Jump Trading</span>
        </p>
      </div>
    </footer>
  );
}

export function PublicLayout() {
  return (
    <div className="flex min-h-screen flex-col">
      <Navbar />
      <main className="flex-1">
        <Outlet />
      </main>
      <Footer />
    </div>
  );
}

type SideItem = { to: string; label: string; icon: ReactNode; soon?: string; end?: boolean };

function SideNav({ items, title }: { items: SideItem[]; title: string }) {
  return (
    <aside className="lg:w-56 lg:shrink-0">
      <p className="mb-2 hidden px-3 text-xs font-semibold uppercase tracking-wider text-muted lg:block">{title}</p>
      <nav className="-mx-4 flex gap-1 overflow-x-auto px-4 pb-2 lg:mx-0 lg:flex-col lg:px-0">
        {items.map((i) => (
          <NavLink
            key={i.to}
            to={i.to}
            end={i.end}
            className={({ isActive }) =>
              cn(
                "flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm text-muted hover:bg-surface-2 hover:text-fg",
                isActive && "bg-surface-2 font-medium text-fg",
              )
            }
          >
            {i.icon}
            <span>{i.label}</span>
            {i.soon && (
              <Badge className="ml-auto hidden lg:inline-flex" tone="neutral">
                {i.soon}
              </Badge>
            )}
          </NavLink>
        ))}
      </nav>
    </aside>
  );
}

const DASH_ITEMS: SideItem[] = [
  { to: "/dashboard", label: "Dashboard", icon: <LayoutDashboard size={16} />, end: true },
  { to: "/dashboard/payouts", label: "Prizes & reimb.", icon: <Gift size={16} />, soon: "soon" },
  { to: "/icpc", label: "ICPC", icon: <Trophy size={16} />, soon: "soon" },
  { to: "/profile", label: "Profile", icon: <UserIcon size={16} /> },
];

const ADMIN_ITEMS: SideItem[] = [
  { to: "/admin/users", label: "Users & roles", icon: <Users size={16} /> },
  { to: "/admin/events", label: "Events", icon: <CalendarDays size={16} />, soon: "soon" },
  { to: "/admin/finance", label: "Finance", icon: <Gift size={16} />, soon: "soon" },
  { to: "/admin/icpc", label: "ICPC", icon: <Trophy size={16} />, soon: "soon" },
  { to: "/admin/audit", label: "Audit log", icon: <ScrollText size={16} />, soon: "soon" },
];

function ShellLayout({ items, title }: { items: SideItem[]; title: string }) {
  return (
    <div className="flex min-h-screen flex-col">
      <Navbar />
      <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-4 py-6 lg:flex-row lg:py-8">
        <SideNav items={items} title={title} />
        <main className="min-w-0 flex-1">
          <Outlet />
        </main>
      </div>
    </div>
  );
}

export const DashboardLayout = () => <ShellLayout items={DASH_ITEMS} title="Member" />;
export const AdminLayout = () => <ShellLayout items={ADMIN_ITEMS} title="Admin" />;

export function PageHeader({ title, subtitle, action }: { title: string; subtitle?: ReactNode; action?: ReactNode }) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-muted">{subtitle}</p>}
      </div>
      {action}
    </div>
  );
}
