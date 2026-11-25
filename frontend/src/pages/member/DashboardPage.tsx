import { AlertTriangle, Gift, Pencil, Trophy, User as UserIcon } from "lucide-react";
import type { ReactNode } from "react";
import { Link } from "react-router-dom";

import type { User } from "@/api/client";
import { Badge, Card, CardHeader, Skeleton, buttonStyles } from "@/components/ui";
import { useDashboard } from "@/hooks/queries";
import { PageHeader } from "@/layouts/Layouts";
import { ROLE_LABELS, type Role } from "@/lib/roles";
import { initials } from "@/lib/utils";

export function DashboardPage() {
  const { data, isLoading } = useDashboard();

  if (isLoading || !data) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-44 w-full" />
        <div className="grid gap-4 md:grid-cols-2">
          <Skeleton className="h-36" />
          <Skeleton className="h-36" />
        </div>
      </div>
    );
  }

  const p = data.profile;
  return (
    <div>
      <PageHeader title={`Hi, ${p.name.split(" ")[0]}`} subtitle="Everything about you and ANCC in one place." />

      {data.profile_missing.length > 0 && (
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-warn/30 bg-warn-soft px-4 py-3 text-sm text-warn">
          <span className="flex items-center gap-2">
            <AlertTriangle size={16} /> Add your {data.profile_missing.join(" and ")} so coordinators can reach you.
          </span>
          <Link to="/profile" className="font-medium underline underline-offset-2">
            Complete profile
          </Link>
        </div>
      )}

      <ProfileCard user={p} />

      <div className="mt-4 grid gap-4 md:grid-cols-2">
        <StubCard
          icon={<Gift size={16} />}
          title="Prizes & reimbursements"
          phase={data.payouts.phase}
          body="Each prize or claim with its step: bank details → professor approval → accounts (≈21 days) → done."
        />
        <StubCard
          icon={<Trophy size={16} />}
          title="ICPC team"
          phase={data.icpc.phase}
          body="Create a team, invite 2 teammates, fill your icpc.global details and follow your team's status."
        />
      </div>
    </div>
  );
}

function ProfileCard({ user }: { user: User }) {
  const rows: [string, ReactNode][] = [
    ["Kerberos ID", <span className="font-mono">{user.kerberos_id}</span>],
    ["Department", user.department ?? "—"],
    ["Year", user.year_of_study ? `Year ${user.year_of_study}` : "—"],
    ["Hostel", user.hostel ?? <Missing />],
    ["Phone", user.phone ?? <Missing />],
    ["Email", user.iitd_email],
    ["Alt. email", user.alt_email ?? "—"],
    ["Codeforces", user.codeforces ? <span className="font-mono">{user.codeforces}</span> : "—"],
    ["CodeChef", user.codechef ? <span className="font-mono">{user.codechef}</span> : "—"],
  ];
  return (
    <Card>
      <CardHeader
        title="Profile"
        icon={<UserIcon size={16} />}
        action={
          <Link to="/profile" className={buttonStyles({ variant: "ghost", size: "sm" })}>
            <Pencil size={14} /> Edit
          </Link>
        }
      />
      <div className="flex flex-col gap-5 p-5 sm:flex-row">
        <div className="flex items-center gap-3 sm:w-56 sm:flex-col sm:items-start">
          <div className="grid h-14 w-14 place-items-center rounded-full bg-accent-soft font-mono text-lg font-semibold text-accent">
            {initials(user.name)}
          </div>
          <div>
            <p className="font-semibold">{user.name}</p>
            <div className="mt-1 flex flex-wrap gap-1">
              {(user.roles.length ? user.roles : ["member"]).map((r) => (
                <Badge key={r} tone={r === "member" ? "neutral" : "accent"}>
                  {ROLE_LABELS[r as Role] ?? r}
                </Badge>
              ))}
            </div>
          </div>
        </div>
        <dl className="grid flex-1 grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
          {rows.map(([k, v]) => (
            <div key={k} className="min-w-0">
              <dt className="text-xs text-muted">{k}</dt>
              <dd className="truncate">{v}</dd>
            </div>
          ))}
        </dl>
      </div>
    </Card>
  );
}

const Missing = () => <span className="text-warn">Not added</span>;

function StubCard({
  icon,
  title,
  phase,
  body,
  className,
}: {
  icon: ReactNode;
  title: string;
  phase: string;
  body: string;
  className?: string;
}) {
  return (
    <Card className={className}>
      <CardHeader title={title} icon={icon} action={<Badge>{phase}</Badge>} />
      <p className="px-5 py-4 text-sm text-muted">{body}</p>
    </Card>
  );
}
