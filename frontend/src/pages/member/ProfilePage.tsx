import { zodResolver } from "@hookform/resolvers/zod";
import { Lock } from "lucide-react";
import { useEffect } from "react";
import { useForm } from "react-hook-form";
import { useNavigate, useSearchParams } from "react-router-dom";
import { toast } from "sonner";
import { z } from "zod";

import { errorMessage } from "@/api/client";
import { Button, Card, Field, Input, Select, Spinner } from "@/components/ui";
import { useAuthConfig, useMe, useUpdateProfile } from "@/hooks/queries";
import { PageHeader } from "@/layouts/Layouts";

const handle = z
  .string()
  .regex(/^[A-Za-z0-9_.-]{2,50}$/, "Letters, digits, _ . and - only")
  .or(z.literal(""));

const schema = z.object({
  phone: z.string().regex(/^\+?[0-9 ]{10,15}$/, "Enter a valid phone number (10–15 digits)"),
  hostel: z.string().min(1, "Pick your hostel"),
  alt_email: z.string().email("Enter a valid email").or(z.literal("")),
  codeforces: handle,
  codechef: handle,
});
type FormValues = z.infer<typeof schema>;

export function ProfilePage() {
  const { data: me } = useMe();
  const { data: config } = useAuthConfig();
  const update = useUpdateProfile();
  const [params] = useSearchParams();
  const onboarding = params.get("onboarding") === "1";
  const navigate = useNavigate();

  const form = useForm<FormValues>({ resolver: zodResolver(schema) });
  const { register, handleSubmit, reset, formState } = form;

  useEffect(() => {
    if (me)
      reset({
        phone: me.phone ?? "",
        hostel: me.hostel ?? "",
        alt_email: me.alt_email ?? "",
        codeforces: me.codeforces ?? "",
        codechef: me.codechef ?? "",
      });
  }, [me, reset]);

  if (!me || !config) return <Spinner />;
  const err = formState.errors;

  const onSubmit = handleSubmit((values) =>
    update.mutate(values, {
      onSuccess: () => {
        toast.success("Profile saved");
        if (onboarding) navigate("/dashboard");
      },
      onError: (e) => toast.error(errorMessage(e)),
    }),
  );

  return (
    <div className="max-w-2xl">
      <PageHeader
        title={onboarding ? "Welcome to ANCC 👋" : "Profile"}
        subtitle={
          onboarding
            ? "Add your phone and hostel so coordinators can reach you. You can change these any time."
            : "Your contact details and coding handles."
        }
      />

      <Card className="mb-4 p-5">
        <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold">
          <Lock size={14} className="text-muted" /> From IITD (read-only)
        </h2>
        <dl className="grid gap-3 text-sm sm:grid-cols-2">
          <Item k="Name" v={me.name} />
          <Item k="Kerberos ID" v={<span className="font-mono">{me.kerberos_id}</span>} />
          <Item k="IITD email" v={me.iitd_email} />
          <Item k="Department" v={me.department ?? "—"} />
        </dl>
      </Card>

      <Card className="p-5">
        <form onSubmit={onSubmit} className="space-y-4" noValidate>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Phone" htmlFor="phone" error={err.phone?.message}>
              <Input id="phone" type="tel" placeholder="+91 98xxx xxxxx" {...register("phone")} />
            </Field>
            <Field label="Hostel" htmlFor="hostel" error={err.hostel?.message}>
              <Select id="hostel" {...register("hostel")}>
                <option value="">Select hostel…</option>
                {config.hostels.map((h) => (
                  <option key={h}>{h}</option>
                ))}
              </Select>
            </Field>
          </div>
          <Field label="Alternate email" htmlFor="alt_email" hint="Optional" error={err.alt_email?.message}>
            <Input id="alt_email" type="email" {...register("alt_email")} />
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Codeforces handle" htmlFor="codeforces" hint="Optional" error={err.codeforces?.message}>
              <Input id="codeforces" className="font-mono" {...register("codeforces")} />
            </Field>
            <Field label="CodeChef handle" htmlFor="codechef" hint="Optional" error={err.codechef?.message}>
              <Input id="codechef" className="font-mono" {...register("codechef")} />
            </Field>
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button type="submit" disabled={update.isPending}>
              {update.isPending ? "Saving…" : onboarding ? "Save and continue" : "Save changes"}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
}

function Item({ k, v }: { k: string; v: React.ReactNode }) {
  return (
    <div>
      <dt className="text-xs text-muted">{k}</dt>
      <dd>{v}</dd>
    </div>
  );
}
