// TEMPLATE: copy this folder to src/features/<your-feature>/ (lowercase, no leading "_").
// Everything for your feature lives in that folder: pages, components, API calls (api.ts),
// TanStack Query hooks (hooks.ts) and types (types.ts). It is picked up automatically.
import { Sparkles } from "lucide-react";

import { defineFeature } from "@/features/registry";
import { ComingSoonCard, ComingSoonPage } from "@/pages/public/ErrorPages";

export default defineFeature({
  id: "template",
  publicRoutes: [{ path: "/template", element: <ComingSoonPage title="Template" what="A public page." /> }],
  memberRoutes: [{ path: "/dashboard/template", element: <ComingSoonPage title="Template" what="A member page." /> }],
  adminRoutes: [{ path: "/admin/template", element: <ComingSoonPage title="Template" what="An admin page." /> }],
  topNav: [{ to: "/template", label: "Template", order: 99 }],
  memberNav: [{ to: "/dashboard/template", label: "Template", icon: <Sparkles size={16} />, order: 99, soon: true }],
  adminNav: [{ to: "/admin/template", label: "Template", icon: <Sparkles size={16} />, order: 99, soon: true }],
  dashboardCards: [
    {
      id: "template",
      order: 99,
      element: <ComingSoonCard icon={<Sparkles size={16} />} title="Template" body="A dashboard card." />,
    },
  ],
});
