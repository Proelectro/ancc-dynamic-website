// Prizes and reimbursements tracking (GitHub issue #5): the 4-step payout flow, claims with receipts,
// and the finance queue. Everything for this feature stays in this folder.
import { Gift } from "lucide-react";

import { defineFeature } from "@/features/registry";
import { ComingSoon, ComingSoonCard } from "@/pages/public/ErrorPages";

export default defineFeature({
  id: "payouts",
  memberRoutes: [
    {
      path: "/dashboard/payouts",
      element: <ComingSoon title="Prizes & reimbursements" what="Track each prize and claim step by step." />,
    },
  ],
  adminRoutes: [
    {
      path: "/admin/finance",
      element: <ComingSoon title="Finance" what="Move prizes and reimbursements through their steps." />,
    },
  ],
  memberNav: [{ to: "/dashboard/payouts", label: "Prizes & reimb.", icon: <Gift size={16} />, order: 20, soon: true }],
  adminNav: [{ to: "/admin/finance", label: "Finance", icon: <Gift size={16} />, order: 20, soon: true }],
  dashboardCards: [
    {
      id: "payouts",
      order: 20,
      element: (
        <ComingSoonCard
          icon={<Gift size={16} />}
          title="Prizes & reimbursements"
          body="Each prize or claim with its step: bank details → professor approval → accounts (≈21 days) → done."
        />
      ),
    },
  ],
});
