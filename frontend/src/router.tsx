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
            phase="Phase 2"
            what="Upcoming, ongoing and past events with registration forms, contest links, results and photos."
          />
        ),
      },
      {
        path: "/about",
        element: (
          <ComingSoonPage
            title="About ANCC"
            phase="Phase 6"
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
        path: "/dashboard/eca-caic",
        element: soon("ECA & CAIC", "Phase 3", "Your full ECA list and CAIC breakdown by year."),
      },
      { path: "/dashboard/events", element: soon("My events", "Phase 2", "Events you registered for.") },
      {
        path: "/dashboard/payouts",
        element: soon("Prizes & reimbursements", "Phase 4", "Track each prize and claim step by step."),
      },
      {
        path: "/icpc",
        element: soon("ICPC", "Phase 5", "Team formation, your icpc.global details and the registration guide."),
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
      { path: "/admin/events", element: soon("Events admin", "Phase 2", "Create events and registration forms.") },
      { path: "/admin/finance", element: soon("Finance", "Phase 4", "Move prizes and reimbursements through steps.") },
      { path: "/admin/icpc", element: soon("ICPC admin", "Phase 5", "Seasons, teams, issues and the worksheet.") },
      { path: "/admin/audit", element: soon("Audit log", "Phase 7", "Every admin change, with before/after.") },
    ],
  },
], { basename: import.meta.env.BASE_URL.replace(/\/$/, "") || "/" });
