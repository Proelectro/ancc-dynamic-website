import { ChevronLeft, ChevronRight, Search } from "lucide-react";
import { useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";

import { Badge, Button, Card, Input, Select, Skeleton } from "@/components/ui";
import { useAdminUsers, useAuthConfig } from "@/hooks/queries";
import { PageHeader } from "@/layouts/Layouts";
import { GRANTABLE_ROLES, ROLE_LABELS, type Role } from "@/lib/roles";

export function AdminUsersPage() {
  // Filters live in the URL so a filtered view can be bookmarked or shared.
  const [params, setParams] = useSearchParams();
  const q = params.get("q") ?? "";
  const hostel = params.get("hostel") ?? "";
  const role = params.get("role") ?? "";
  const page = Number(params.get("page") ?? 1);

  const [search, setSearch] = useState(q);
  useEffect(() => {
    const t = setTimeout(() => {
      if (search !== q) update({ q: search, page: "1" });
    }, 300);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  function update(patch: Record<string, string>) {
    const next = new URLSearchParams(params);
    for (const [k, v] of Object.entries(patch)) {
      if (v) next.set(k, v);
      else next.delete(k);
    }
    setParams(next, { replace: true });
  }

  const { data: config } = useAuthConfig();
  const { data, isLoading, isFetching } = useAdminUsers({ q, hostel, role, page });
  const pages = data ? Math.max(1, Math.ceil(data.total / data.page_size)) : 1;

  return (
    <div>
      <PageHeader title="Users & roles" subtitle={data ? `${data.total} users` : " "} />

      <div className="mb-4 grid gap-2 sm:grid-cols-[1fr_180px_180px]">
        <div className="relative">
          <Search size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
          <Input
            aria-label="Search by name or kerberos ID"
            placeholder="Search name or kerberos ID"
            className="pl-9"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
        <Select aria-label="Filter by hostel" value={hostel} onChange={(e) => update({ hostel: e.target.value, page: "1" })}>
          <option value="">All hostels</option>
          {config?.hostels.map((h) => (
            <option key={h}>{h}</option>
          ))}
        </Select>
        <Select aria-label="Filter by role" value={role} onChange={(e) => update({ role: e.target.value, page: "1" })}>
          <option value="">All roles</option>
          <option value="member">Members only</option>
          {GRANTABLE_ROLES.map((r) => (
            <option key={r} value={r}>
              {ROLE_LABELS[r]}
            </option>
          ))}
        </Select>
      </div>

      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[640px] text-sm">
            <thead className="bg-surface-2 text-left text-xs text-muted">
              <tr>
                <th className="px-4 py-2.5 font-medium">Name</th>
                <th className="px-4 py-2.5 font-medium">Kerberos</th>
                <th className="px-4 py-2.5 font-medium">Hostel</th>
                <th className="px-4 py-2.5 font-medium">Roles</th>
                <th className="px-4 py-2.5 font-medium">Status</th>
              </tr>
            </thead>
            <tbody className={isFetching ? "opacity-60" : undefined}>
              {isLoading &&
                Array.from({ length: 5 }).map((_, i) => (
                  <tr key={i} className="border-t border-border">
                    <td colSpan={5} className="px-4 py-3">
                      <Skeleton className="h-5 w-full" />
                    </td>
                  </tr>
                ))}
              {data?.items.map((u) => (
                <tr key={u.id} className="border-t border-border hover:bg-surface-2/60">
                  <td className="px-4 py-3">
                    <Link to={`/admin/users/${u.id}`} className="font-medium hover:text-accent">
                      {u.name}
                    </Link>
                    <p className="text-xs text-muted">{u.department ?? "—"}</p>
                  </td>
                  <td className="px-4 py-3 font-mono text-xs">{u.kerberos_id}</td>
                  <td className="px-4 py-3">{u.hostel ?? <span className="text-muted">—</span>}</td>
                  <td className="px-4 py-3">
                    <div className="flex flex-wrap gap-1">
                      {u.roles.length === 0 ? (
                        <Badge>Member</Badge>
                      ) : (
                        u.roles.map((r) => (
                          <Badge key={r} tone="accent">
                            {ROLE_LABELS[r as Role] ?? r}
                          </Badge>
                        ))
                      )}
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    {u.is_active ? <Badge>Active</Badge> : <Badge tone="danger">Deactivated</Badge>}
                  </td>
                </tr>
              ))}
              {data && data.items.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-10 text-center text-muted">
                    No users match these filters.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <div className="flex items-center justify-between border-t border-border px-4 py-2.5 text-sm text-muted">
          <span>
            Page {page} of {pages}
          </span>
          <div className="flex gap-1">
            <Button
              variant="ghost"
              size="sm"
              aria-label="Previous page"
              disabled={page <= 1}
              onClick={() => update({ page: String(page - 1) })}
            >
              <ChevronLeft size={16} />
            </Button>
            <Button
              variant="ghost"
              size="sm"
              aria-label="Next page"
              disabled={page >= pages}
              onClick={() => update({ page: String(page + 1) })}
            >
              <ChevronRight size={16} />
            </Button>
          </div>
        </div>
      </Card>
    </div>
  );
}
