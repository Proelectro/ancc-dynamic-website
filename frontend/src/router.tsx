import { createBrowserRouter } from "react-router-dom";

import { RequireAuth, RequireRole } from "@/components/auth/guards";
import { featureRoutes } from "@/features/registry";
import { AdminLayout, DashboardLayout, PublicLayout } from "@/layouts/Layouts";
import { AdminUserDetailPage } from "@/pages/admin/AdminUserDetailPage";
import { AdminUsersPage } from "@/pages/admin/AdminUsersPage";
import { DashboardPage } from "@/pages/member/DashboardPage";
import { ProfilePage } from "@/pages/member/ProfilePage";
import { AuthCallbackPage } from "@/pages/public/AuthCallbackPage";
import { NotFoundPage } from "@/pages/public/ErrorPages";
import { HomePage } from "@/pages/public/HomePage";
import { LoginPage } from "@/pages/public/LoginPage";

// Core pages only. Feature pages, menus and dashboard cards come from src/features/*/module.tsx
// (see src/features/registry.ts), so adding a feature doesn't touch this file.
export const router = createBrowserRouter(
  [
    {
      element: <PublicLayout />,
      children: [
        { path: "/", element: <HomePage /> },
        { path: "/login", element: <LoginPage /> },
        { path: "/auth/callback", element: <AuthCallbackPage /> },
        ...featureRoutes.public,
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
        ...featureRoutes.member,
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
        ...featureRoutes.admin,
      ],
    },
  ],
  { basename: import.meta.env.BASE_URL.replace(/\/$/, "") || "/" },
);
