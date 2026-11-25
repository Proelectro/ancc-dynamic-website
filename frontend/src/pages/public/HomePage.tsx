import { ArrowRight, CalendarDays, Gift, Trophy } from "lucide-react";
import { Link } from "react-router-dom";

import { buttonStyles, Card } from "@/components/ui";
import { useMe } from "@/hooks/queries";

const PILLARS = [
  {
    icon: <CalendarDays size={18} />,
    title: "Contests & workshops",
    body: "Weekly contests, lecture series and workshops, with highlights, results and photos from each one.",
  },
  {
    icon: <Trophy size={18} />,
    title: "ICPC pipeline",
    body: "Form your team, follow the step-by-step icpc.global guide and track your team from prelims to regionals.",
  },
  {
    icon: <Gift size={18} />,
    title: "Prizes & reimbursements",
    body: "Track every prize and reimbursement claim step by step, from bank details to payment.",
  },
];

export function HomePage() {
  const { data: me } = useMe();
  return (
    <div>
      <section className="border-b border-border">
        <div className="mx-auto max-w-6xl px-4 py-16 sm:py-24">
          <p className="font-mono text-sm text-accent">$ ./ancc --iit-delhi</p>
          <h1 className="mt-4 max-w-2xl text-4xl font-semibold tracking-tight sm:text-5xl">
            Algorithm and Coding Club
          </h1>
          <p className="mt-4 max-w-xl text-lg text-muted">
            Competitive programming, ICPC preparation and contests for every IIT Delhi student.
          </p>
          <div className="mt-8 flex flex-wrap gap-3">
            {me ? (
              <Link to="/dashboard" className={buttonStyles({ size: "lg" })}>
                Go to dashboard <ArrowRight size={16} />
              </Link>
            ) : (
              <Link to="/login" className={buttonStyles({ size: "lg" })}>
                Log in with IITD <ArrowRight size={16} />
              </Link>
            )}
            <Link to="/events" className={buttonStyles({ size: "lg", variant: "secondary" })}>
              See events
            </Link>
          </div>
        </div>
      </section>

      <section className="mx-auto grid max-w-6xl gap-4 px-4 py-12 sm:grid-cols-3">
        {PILLARS.map((p) => (
          <Card key={p.title} className="p-5">
            <div className="grid h-9 w-9 place-items-center rounded-lg bg-accent-soft text-accent">{p.icon}</div>
            <h2 className="mt-4 font-semibold">{p.title}</h2>
            <p className="mt-1.5 text-sm text-muted">{p.body}</p>
          </Card>
        ))}
      </section>
    </div>
  );
}
