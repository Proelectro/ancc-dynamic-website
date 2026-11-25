import { createBrowserRouter } from "react-router-dom";

import { RequireAuth, RequireRole } from "@/components/auth/guards";
import { AdminLayout, DashboardLayout, PublicLayout } from "@/layouts/Layouts";
import { AdminUserDetailPage } from "@/pages/admin/AdminUserDetailPage";
import { AdminUsersPage } from "@/pages/admin/AdminUsersPage";
import { DashboardPage } from "@/pages/member/DashboardPage";
import { ProfilePage } from "@/pages/member/ProfilePage";
import { AuthCallbackPage } from "@/pages/public/AuthCallbackPage";
import { ComingSoon, ComingSoonPage, NotFoundPage } from "@/pages/public/ErrorPages";
import { HomePage } from "@/pages/public/HomePage";
import { LoginPage } from "@/pages/public/LoginPage";

const soon = (title: string, phase: string, what: string) => <ComingSoon title={title} phase={phase} what={what} />;

export const router = createBrowserRouter([
  {
    element: <PublicLayout />,
    children: [
      { path: "/", element: <HomePage /> },
      { path: "/login", element: <LoginPage /> },
      { path: "/auth/callback", element: <AuthCallbackPage /> },
      {
        path: "/events",
        element: (
          <ComingSoonPage
            title="Events"
            phase="coming soon"
            what="Highlights from our contests and workshops: write-ups, results and photos, with a link to wherever registration happens."
          />
        ),
      },
      {
        path: "/about",
        element: (
          <ComingSoonPage
            title="About ANCC"
            phase="coming soon"
            what="The club, our mission, the team by year, and our sponsor Jump Trading."
          />
        ),
      },
      { path: "*", element: <NotFoundPage /> },
    ],
  },
  {
    element: (
      <RequireAuth>
        <DashboardLayout />
      </RequireAuth>
    ),
    children: [
      { path: "/dashboard", element: <DashboardPage /> },
      { path: "/profile", element: <ProfilePage /> },
      {
        path: "/dashboard/payouts",
        element: soon("Prizes & reimbursements", "coming soon", "Track each prize and claim step by step."),
      },
      {
        path: "/icpc",
        element: soon("ICPC", "coming soon", "Team formation, your icpc.global details and the registration guide."),
      },
    ],
  },
  {
    element: (
      <RequireAuth>
        <RequireRole roles={["coordinator", "icpc_admin", "finance"]}>
          <AdminLayout />
        </RequireRole>
      </RequireAuth>
    ),
    children: [
      { path: "/admin", element: <AdminUsersPage /> },
      { path: "/admin/users", element: <AdminUsersPage /> },
      { path: "/admin/users/:id", element: <AdminUserDetailPage /> },
      { path: "/admin/events", element: soon("Event highlights", "coming soon", "Add write-ups, results and photo albums for events.") },
      { path: "/admin/finance", element: soon("Finance", "coming soon", "Move prizes and reimbursements through steps.") },
      { path: "/admin/icpc", element: soon("ICPC admin", "coming soon", "Seasons, teams, issues and the worksheet.") },
      { path: "/admin/audit", element: soon("Audit log", "coming soon", "Every admin change, with before/after.") },
    ],
  },
], { basename: import.meta.env.BASE_URL.replace(/\/$/, "") || "/" });
