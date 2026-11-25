import { LayoutDashboard, LogOut, Shield, User as UserIcon } from "lucide-react";
import { useState } from "react";
import { Link, NavLink, useNavigate } from "react-router-dom";
import { toast } from "sonner";

import { AnccMark } from "@/components/brand/AnccMark";
import { buttonStyles } from "@/components/ui";
import { useLogout, useMe } from "@/hooks/queries";
import { isAdmin } from "@/lib/roles";
import { cn, initials } from "@/lib/utils";

const NAV = [
  { to: "/events", label: "Events" },
  { to: "/icpc", label: "ICPC" },
  { to: "/about", label: "About" },
];

export function Logo() {
  return (
    <Link to="/" className="flex items-center gap-3 text-fg" aria-label="ANCC home">
      <AnccMark className="h-5 w-auto" />
      <span className="hidden border-l border-border pl-3 font-mono text-xs text-muted sm:inline">IIT Delhi</span>
    </Link>
  );
}

export function Navbar() {
  const { data: me } = useMe();
  const logout = useLogout();
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);

  return (
    <header className="sticky top-0 z-30 border-b border-border bg-bg/85 backdrop-blur">
      <div className="mx-auto flex h-14 max-w-6xl items-center justify-between gap-4 px-4">
        <div className="flex items-center gap-6">
          <Logo />
          <nav className="hidden items-center gap-1 sm:flex">
            {NAV.map((n) => (
              <NavLink
                key={n.to}
                to={n.to}
                className={({ isActive }) =>
                  cn("rounded-md px-3 py-1.5 text-sm text-muted hover:text-fg", isActive && "text-fg font-medium")
                }
              >
                {n.label}
              </NavLink>
            ))}
          </nav>
        </div>

        {me ? (
          <div className="relative">
            <button
              onClick={() => setOpen((o) => !o)}
              className="flex items-center gap-2 rounded-full border border-border bg-surface py-1 pl-1 pr-3 text-sm hover:bg-surface-2"
              aria-haspopup="menu"
              aria-expanded={open}
            >
              <span className="grid h-7 w-7 place-items-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">
                {initials(me.name)}
              </span>
              <span className="hidden max-w-32 truncate sm:inline">{me.name.split(" ")[0]}</span>
            </button>
            {open && (
              <div
                role="menu"
                className="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-border bg-surface py-1 shadow-lg"
                onClick={() => setOpen(false)}
              >
                <div className="border-b border-border px-3 py-2">
                  <p className="truncate text-sm font-medium">{me.name}</p>
                  <p className="font-mono text-xs text-muted">{me.kerberos_id}</p>
                </div>
                <MenuLink to="/dashboard" icon={<LayoutDashboard size={15} />} label="Dashboard" />
                <MenuLink to="/profile" icon={<UserIcon size={15} />} label="Profile" />
                {isAdmin(me.roles) && <MenuLink to="/admin/users" icon={<Shield size={15} />} label="Admin" />}
                <button
                  role="menuitem"
                  className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-muted hover:bg-surface-2 hover:text-fg"
                  onClick={async () => {
                    await logout((path) => navigate(path, { replace: true }));
                    toast.success("Logged out");
                  }}
                >
                  <LogOut size={15} /> Log out
                </button>
              </div>
            )}
          </div>
        ) : (
          <Link to="/login" className={buttonStyles({ size: "sm" })}>
            Log in
          </Link>
        )}
      </div>
      <nav className="flex gap-1 overflow-x-auto px-4 pb-2 sm:hidden">
        {NAV.map((n) => (
          <NavLink
            key={n.to}
            to={n.to}
            className={({ isActive }) =>
              cn("rounded-md px-3 py-1 text-sm text-muted", isActive && "bg-surface-2 text-fg")
            }
          >
            {n.label}
          </NavLink>
        ))}
      </nav>
    </header>
  );
}

function MenuLink({ to, icon, label }: { to: string; icon: React.ReactNode; label: string }) {
  return (
    <Link role="menuitem" to={to} className="flex items-center gap-2 px-3 py-2 text-sm hover:bg-surface-2">
      {icon} {label}
    </Link>
  );
}
