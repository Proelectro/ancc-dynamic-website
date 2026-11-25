// ICPC flow (GitHub issue #6): teams, invites, icpc.global details, eligibility, the guide, and the
// admin side. Everything for this feature stays in this folder.
import { Trophy } from "lucide-react";

import { defineFeature } from "@/features/registry";
import { ComingSoon, ComingSoonCard } from "@/pages/public/ErrorPages";

export default defineFeature({
  id: "icpc",
  memberRoutes: [
    {
      path: "/icpc",
      element: <ComingSoon title="ICPC" what="Team formation, your icpc.global details and the registration guide." />,
    },
  ],
  adminRoutes: [
    {
      path: "/admin/icpc",
      element: <ComingSoon title="ICPC admin" what="Seasons, teams, issues and the registration worksheet." />,
    },
  ],
  topNav: [{ to: "/icpc", label: "ICPC", order: 30 }],
  memberNav: [{ to: "/icpc", label: "ICPC", icon: <Trophy size={16} />, order: 30, soon: true }],
  adminNav: [{ to: "/admin/icpc", label: "ICPC", icon: <Trophy size={16} />, order: 30, soon: true }],
  dashboardCards: [
    {
      id: "icpc",
      order: 30,
      element: (
        <ComingSoonCard
          icon={<Trophy size={16} />}
          title="ICPC team"
          body="Create a team, invite 2 teammates, fill your icpc.global details and follow your team's status."
        />
      ),
    },
  ],
});
