import { ArrowLeft, ShieldCheck, UserX } from "lucide-react";
import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { toast } from "sonner";

import { errorMessage } from "@/api/client";
import { Badge, Button, Card, CardHeader, Spinner } from "@/components/ui";
import { useAdminUpdateUser, useAdminUser, useMe, useSetRoles } from "@/hooks/queries";
import { PageHeader } from "@/layouts/Layouts";
import { GRANTABLE_ROLES, ROLE_HELP, ROLE_LABELS, hasRole } from "@/lib/roles";
import { cn, formatIST } from "@/lib/utils";
import { ComingSoon } from "@/pages/public/ErrorPages";

export function AdminUserDetailPage() {
  const { id = "" } = useParams();
  const { data: me } = useMe();
  const { data: user, isLoading } = useAdminUser(id);
  const setRoles = useSetRoles(id);
  const updateUser = useAdminUpdateUser(id);
  const [roles, setLocalRoles] = useState<string[]>([]);
  const canManage = hasRole(me?.roles, "super_admin");

  useEffect(() => {
    if (user) setLocalRoles(user.roles);
  }, [user]);

  if (isLoading || !user) return <Spinner />;
  const dirty = [...roles].sort().join() !== [...user.roles].sort().join();

  return (
    <div>
      <Link to="/admin/users" className="mb-3 inline-flex items-center gap-1 text-sm text-muted hover:text-fg">
        <ArrowLeft size={14} /> All users
      </Link>
      <PageHeader
        title={user.name}
        subtitle={
          <span className="font-mono">
            {user.kerberos_id} · last login {formatIST(user.last_login_at)}
          </span>
        }
        action={!user.is_active && <Badge tone="danger">Deactivated</Badge>}
      />

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader title="Profile" />
          <dl className="grid gap-3 p-5 text-sm sm:grid-cols-2">
            {[
              ["Department", user.department],
              ["Year", user.year_of_study ? `Year ${user.year_of_study}` : null],
              ["Hostel", user.hostel],
              ["Phone", user.phone],
              ["IITD email", user.iitd_email],
              ["Alt. email", user.alt_email],
              ["Codeforces", user.codeforces],
              ["CodeChef", user.codechef],
            ].map(([k, v]) => (
              <div key={k}>
                <dt className="text-xs text-muted">{k}</dt>
                <dd className="truncate">{v ?? "—"}</dd>
              </div>
            ))}
          </dl>
        </Card>

        <Card>
          <CardHeader
            title="Roles"
            icon={<ShieldCheck size={16} />}
            action={!canManage && <span className="text-xs text-muted">Only super admins can change roles</span>}
          />
          <div className="space-y-2 p-5">
            {GRANTABLE_ROLES.map((r) => {
              const on = roles.includes(r);
              return (
                <label
                  key={r}
                  className={cn(
                    "flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3",
                    on && "border-accent/50 bg-accent-soft/50",
                    !canManage && "cursor-default opacity-70",
                  )}
                >
                  <input
                    type="checkbox"
                    className="mt-0.5 accent-[var(--accent)]"
                    checked={on}
                    disabled={!canManage}
                    onChange={() => setLocalRoles((cur) => (on ? cur.filter((x) => x !== r) : [...cur, r]))}
                  />
                  <span>
                    <span className="block text-sm font-medium">{ROLE_LABELS[r]}</span>
                    <span className="block text-xs text-muted">{ROLE_HELP[r]}</span>
                  </span>
                </label>
              );
            })}
            {canManage && (
              <div className="flex justify-end pt-2">
                <Button
                  disabled={!dirty || setRoles.isPending}
                  onClick={() =>
                    setRoles.mutate(roles, {
                      onSuccess: () => toast.success("Roles updated"),
                      onError: (e) => toast.error(errorMessage(e)),
                    })
                  }
                >
                  Save roles
                </Button>
              </div>
            )}
          </div>
        </Card>

        <div className="lg:col-span-2">
          <ComingSoon
            title="ECA records, CAIC score, payouts and ICPC team"
            phase="Phases 3–5"
            what="Add or revoke ECA records and CAIC entries for this student and see their prizes, reimbursements and ICPC team here."
          />
        </div>

        {canManage && me?.id !== user.id && (
          <Card className="lg:col-span-2">
            <CardHeader title="Account" icon={<UserX size={16} />} />
            <div className="flex flex-wrap items-center justify-between gap-3 p-5 text-sm">
              <p className="text-muted">
                {user.is_active
                  ? "Deactivating bans this user from logging in. Their data is kept."
                  : "This user can't log in. Reactivate to restore access."}
              </p>
              <Button
                variant={user.is_active ? "danger" : "secondary"}
                disabled={updateUser.isPending}
                onClick={() => {
                  if (user.is_active && !confirm(`Deactivate ${user.name}?`)) return;
                  updateUser.mutate(
                    { is_active: !user.is_active },
                    {
                      onSuccess: (u) => toast.success(u.is_active ? "User reactivated" : "User deactivated"),
                      onError: (e) => toast.error(errorMessage(e)),
                    },
                  );
                }}
              >
                {user.is_active ? "Deactivate" : "Reactivate"}
              </Button>
            </div>
          </Card>
        )}
      </div>
    </div>
  );
}
