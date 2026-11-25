// About page and Meet the team, with the Jump Trading sponsor section (GitHub issue #7).
// Everything for this feature stays in this folder.
import { defineFeature } from "@/features/registry";
import { ComingSoonPage } from "@/pages/public/ErrorPages";

export default defineFeature({
  id: "about",
  publicRoutes: [
    {
      path: "/about",
      element: (
        <ComingSoonPage
          title="About ANCC"
          what="The club, our mission, the team by year, and our sponsor Jump Trading."
        />
      ),
    },
  ],
  topNav: [{ to: "/about", label: "About", order: 40 }],
});
