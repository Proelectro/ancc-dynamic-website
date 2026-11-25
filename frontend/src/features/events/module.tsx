// Event highlights (GitHub issue #4): past and upcoming events with write-ups, results, Google Drive
// photos and a link to wherever registration happens. Everything for this feature stays in this folder.
import { CalendarDays } from "lucide-react";

import { defineFeature } from "@/features/registry";
import { ComingSoonPage } from "@/pages/public/ErrorPages";

export default defineFeature({
  id: "events",
  publicRoutes: [
    {
      path: "/events",
      element: (
        <ComingSoonPage
          title="Events"
          what="Highlights from our contests and workshops: write-ups, results and photos, with a link to wherever registration happens."
        />
      ),
    },
  ],
  adminRoutes: [
    {
      path: "/admin/events",
      element: <ComingSoonPage title="Event highlights" what="Add write-ups, results and photo albums for events." />,
    },
  ],
  topNav: [{ to: "/events", label: "Events", order: 10 }],
  adminNav: [{ to: "/admin/events", label: "Events", icon: <CalendarDays size={16} />, order: 10, soon: true }],
});
