// Audit log page for super admins (part of GitHub issue #9). Everything for this feature stays in this folder.
import { ScrollText } from "lucide-react";

import { defineFeature } from "@/features/registry";
import { ComingSoon } from "@/pages/public/ErrorPages";

export default defineFeature({
  id: "audit",
  adminRoutes: [
    { path: "/admin/audit", element: <ComingSoon title="Audit log" what="Every admin change, with before/after." /> },
  ],
  adminNav: [{ to: "/admin/audit", label: "Audit log", icon: <ScrollText size={16} />, order: 90, soon: true }],
});
