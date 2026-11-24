import { Construction } from "lucide-react";
import { Link } from "react-router-dom";

import { buttonStyles } from "@/components/ui";

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

export function ComingSoon({ title, phase, what }: { title: string; phase: string; what: string }) {
  return (
    <div className="rounded-xl border border-dashed border-border bg-surface px-6 py-14 text-center">
      <Construction className="mx-auto text-muted" size={28} />
      <h1 className="mt-3 text-lg font-semibold">{title}</h1>
      <p className="mx-auto mt-1 max-w-md text-sm text-muted">{what}</p>
      <p className="mt-4 font-mono text-xs text-accent">coming in {phase}</p>
    </div>
  );
}

export function ComingSoonPage(props: { title: string; phase: string; what: string }) {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <ComingSoon {...props} />
    </div>
  );
}
