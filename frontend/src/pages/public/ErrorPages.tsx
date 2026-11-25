import { Construction } from "lucide-react";
import type { ReactNode } from "react";
import { Link } from "react-router-dom";

import { Badge, buttonStyles, Card, CardHeader } from "@/components/ui";

function Message({ code, title, body }: { code: string; title: string; body: string }) {
  return (
    <div className="mx-auto max-w-md px-4 py-24 text-center">
      <p className="font-mono text-sm text-accent">{code}</p>
      <h1 className="mt-2 text-2xl font-semibold">{title}</h1>
      <p className="mt-2 text-muted">{body}</p>
      <Link to="/" className={buttonStyles({ variant: "secondary", className: "mt-6" })}>
        Back to home
      </Link>
    </div>
  );
}

export const NotFoundPage = () => (
  <Message code="404" title="Page not found" body="The page you're looking for doesn't exist." />
);

export const ForbiddenPage = () => (
  <Message code="403" title="No access" body="Your account doesn't have the role needed for this page." />
);

/** Placeholder for a page whose feature isn't built yet. */
export function ComingSoon({ title, phase = "coming soon", what }: { title: string; phase?: string; what: string }) {
  return (
    <div className="rounded-xl border border-dashed border-border bg-surface px-6 py-14 text-center">
      <Construction className="mx-auto text-muted" size={28} />
      <h1 className="mt-3 text-lg font-semibold">{title}</h1>
      <p className="mx-auto mt-1 max-w-md text-sm text-muted">{what}</p>
      <p className="mt-4 font-mono text-xs text-accent">{phase}</p>
    </div>
  );
}

export function ComingSoonPage(props: { title: string; phase?: string; what: string }) {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <ComingSoon {...props} />
    </div>
  );
}

/** Placeholder card for the member dashboard (see DashboardCard in src/features/registry.ts). */
export function ComingSoonCard({ icon, title, body }: { icon: ReactNode; title: string; body: string }) {
  return (
    <Card>
      <CardHeader title={title} icon={icon} action={<Badge>Soon</Badge>} />
      <p className="px-5 py-4 text-sm text-muted">{body}</p>
    </Card>
  );
}
