// Alumni outreach (GitHub issue #8). Everything for this feature stays in this folder.
import { defineFeature } from "@/features/registry";
import { ComingSoonPage } from "@/pages/public/ErrorPages";

export default defineFeature({
  id: "alumni",
  publicRoutes: [
    {
      path: "/alumni",
      element: <ComingSoonPage title="Alumni" what="Where ANCC alumni are now, their stories, and how to reach out." />,
    },
  ],
  topNav: [{ to: "/alumni", label: "Alumni", order: 50 }],
});
