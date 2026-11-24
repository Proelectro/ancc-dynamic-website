import type { ReactNode } from "react";
import { Navigate, useLocation } from "react-router-dom";

import { Spinner } from "@/components/ui";
import { useMe } from "@/hooks/queries";
import { hasRole, type Role } from "@/lib/roles";
import { ForbiddenPage } from "@/pages/public/ErrorPages";

/** Redirects to /login?next=... when not logged in. */
export function RequireAuth({ children }: { children: ReactNode }) {
  const { data: me, isLoading } = useMe();
  const location = useLocation();
  if (isLoading) return <Spinner />;
  if (!me) return <Navigate to={`/login?next=${encodeURIComponent(location.pathname)}`} replace />;
  return <>{children}</>;
}

/** UX-only guard; the backend enforces permissions on its own. */
export function RequireRole({ roles, children }: { roles: Role[]; children: ReactNode }) {
  const { data: me } = useMe();
  if (!hasRole(me?.roles, ...roles)) return <ForbiddenPage />;
  return <>{children}</>;
}
