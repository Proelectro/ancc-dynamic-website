import { useQueryClient } from "@tanstack/react-query";
import { KeyRound, LogIn } from "lucide-react";
import { useState } from "react";
import { Navigate, useNavigate, useSearchParams } from "react-router-dom";
import { toast } from "sonner";

import { BASE, errorMessage } from "@/api/client";
import { authApi } from "@/api/endpoints";
import { Badge, Button, Card, Field, Input, Spinner, buttonStyles } from "@/components/ui";
import { queryKeys, useAuthConfig, useMe } from "@/hooks/queries";
import { GRANTABLE_ROLES, ROLE_LABELS } from "@/lib/roles";
import { cn } from "@/lib/utils";

const QUICK = [
  { label: "Member", kerberos: "cs1240001", roles: [] as string[] },
  { label: "New student", kerberos: "me1260003", roles: [] },
  { label: "Coordinator", kerberos: "cs5230004", roles: ["coordinator"] },
  { label: "ICPC admin", kerberos: "mt1240005", roles: ["icpc_admin"] },
  { label: "Finance", kerberos: "ch1230006", roles: ["finance"] },
  { label: "Super admin", kerberos: "cs1230007", roles: ["super_admin"] },
];

const ERRORS: Record<string, string> = {
  iitd: "IITD login didn't complete. Please try again.",
  inactive: "This account has been deactivated. Contact the club if you think this is a mistake.",
};

export function LoginPage() {
  const { data: config, isLoading } = useAuthConfig();
  const { data: me } = useMe();
  const [params] = useSearchParams();
  const next = params.get("next") || "/dashboard";
  const error = params.get("error");

  if (me) return <Navigate to={next} replace />;
  if (isLoading || !config) return <Spinner />;

  return (
    <div className="mx-auto max-w-md px-4 py-16">
      <h1 className="text-2xl font-semibold tracking-tight">Log in</h1>
      <p className="mt-1 text-sm text-muted">Use your IIT Delhi account. Your first login creates your ANCC profile.</p>
      {error && ERRORS[error] && (
        <p className="mt-4 rounded-lg bg-danger/10 px-3 py-2 text-sm text-danger">{ERRORS[error]}</p>
      )}

      {config.mode === "iitd" ? (
        <a href={`${BASE}api/v1/auth/login`} className={buttonStyles({ size: "lg", className: "mt-6 w-full" })}>
          <KeyRound size={16} /> Login with IITD
        </a>
      ) : (
        <DevLogin next={next} />
      )}
    </div>
  );
}

function DevLogin({ next }: { next: string }) {
  const qc = useQueryClient();
  const navigate = useNavigate();
  const [kerberos, setKerberos] = useState("");
  const [name, setName] = useState("");
  const [roles, setRoles] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);

  async function go(kerberos_id: string, r: string[], n?: string) {
    setBusy(true);
    try {
      const user = await authApi.devLogin({ kerberos_id, roles: r, name: n || undefined });
      qc.setQueryData(queryKeys.me, user);
      toast.success(`Logged in as ${user.name}`);
      navigate(user.profile_complete ? next : "/profile?onboarding=1", { replace: true });
    } catch (e) {
      toast.error(errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card className="mt-6 p-5">
      <div className="mb-4 flex items-center gap-2">
        <Badge tone="warn">Dev mode</Badge>
        <span className="text-xs text-muted">IITD login is replaced by this form locally</span>
      </div>

      <p className="mb-2 text-xs font-medium text-muted">Quick login (seeded users)</p>
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
        {QUICK.map((q) => (
          <Button key={q.label} variant="secondary" size="sm" disabled={busy} onClick={() => go(q.kerberos, q.roles)}>
            {q.label}
          </Button>
        ))}
      </div>

      <form
        className="mt-6 space-y-4 border-t border-border pt-5"
        onSubmit={(e) => {
          e.preventDefault();
          go(kerberos, roles, name);
        }}
      >
        <Field label="Kerberos ID" htmlFor="kerberos" hint="e.g. cs1220123">
          <Input
            id="kerberos"
            value={kerberos}
            onChange={(e) => setKerberos(e.target.value)}
            placeholder="cs1220123"
            className="font-mono"
            required
          />
        </Field>
        <Field label="Name (optional)" htmlFor="name">
          <Input id="name" value={name} onChange={(e) => setName(e.target.value)} placeholder="Your name" />
        </Field>
        <fieldset>
          <legend className="mb-1.5 text-sm font-medium">Extra roles</legend>
          <div className="flex flex-wrap gap-2">
            {GRANTABLE_ROLES.map((r) => {
              const on = roles.includes(r);
              return (
                <button
                  type="button"
                  key={r}
                  aria-pressed={on}
                  onClick={() => setRoles((cur) => (on ? cur.filter((x) => x !== r) : [...cur, r]))}
                  className={cn(
                    "rounded-full border px-3 py-1 text-xs",
                    on ? "border-accent bg-accent-soft text-accent" : "border-border text-muted hover:text-fg",
                  )}
                >
                  {ROLE_LABELS[r]}
                </button>
              );
            })}
          </div>
        </fieldset>
        <Button type="submit" className="w-full" disabled={busy}>
          <LogIn size={16} /> Log in
        </Button>
      </form>
    </Card>
  );
}
