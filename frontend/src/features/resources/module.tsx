// Resources (GitHub issue #17): learning material for competitive programming. Everything for this
// feature stays in this folder.
import { defineFeature } from "@/features/registry";
import { ComingSoonPage } from "@/pages/public/ErrorPages";

export default defineFeature({
  id: "resources",
  publicRoutes: [
    {
      path: "/resources",
      element: (
        <ComingSoonPage
          title="Resources"
          what="Curated material to learn competitive programming: topics, problem sets and editorials."
        />
      ),
    },
  ],
  topNav: [{ to: "/resources", label: "Resources", order: 20 }],
});
