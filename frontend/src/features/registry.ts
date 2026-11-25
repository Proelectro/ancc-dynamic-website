import type { ReactNode } from "react";
import type { RouteObject } from "react-router-dom";

/**
 * Features plug themselves into the app. Each folder in src/features/ has a module.tsx whose default
 * export is a FeatureModule; this file finds them all automatically (import.meta.glob), so adding or
 * changing a feature never means editing router.tsx, the menus or the dashboard.
 * Folders starting with "_" (like _template) are skipped.
 */

/** A link in the top navigation bar. Lower `order` comes first. */
export type TopNavItem = { to: string; label: string; order: number };

/** A link in the member or admin side menu. */
export type SideNavItem = {
  to: string;
  label: string;
  icon: ReactNode;
  order: number;
  /** Shows a "soon" badge until the feature is built. */
  soon?: boolean;
  /** Match the URL exactly (for index pages). */
  end?: boolean;
};

/** A card on the member dashboard, below the profile. */
export type DashboardCard = { id: string; order: number; element: ReactNode; wide?: boolean };

export interface FeatureModule {
  /** Folder name, e.g. "events". */
  id: string;
  /** Pages anyone can open (inside the public layout with the navbar and footer). */
  publicRoutes?: RouteObject[];
  /** Pages for logged-in users (member layout with the side menu). */
  memberRoutes?: RouteObject[];
  /**
   * Admin pages. Anyone with coordinator, icpc_admin or finance (or super_admin) gets in;
   * wrap pages in <RequireRole roles={[...]}> for stricter checks.
   */
  adminRoutes?: RouteObject[];
  topNav?: TopNavItem[];
  memberNav?: SideNavItem[];
  adminNav?: SideNavItem[];
  dashboardCards?: DashboardCard[];
}

/** Helper so module files get type checking: `export default defineFeature({ ... })`. */
export const defineFeature = (m: FeatureModule): FeatureModule => m;

const found = import.meta.glob<{ default: FeatureModule }>("./*/module.tsx", { eager: true });

export const features: FeatureModule[] = Object.entries(found)
  .filter(([path]) => !path.startsWith("./_"))
  .map(([, mod]) => mod.default)
  .sort((a, b) => a.id.localeCompare(b.id));

const byOrder = <T extends { order: number }>(a: T, b: T) => a.order - b.order;

export const featureRoutes = {
  public: features.flatMap((f) => f.publicRoutes ?? []),
  member: features.flatMap((f) => f.memberRoutes ?? []),
  admin: features.flatMap((f) => f.adminRoutes ?? []),
};

export const topNav = features.flatMap((f) => f.topNav ?? []).sort(byOrder);
export const memberNav = features.flatMap((f) => f.memberNav ?? []).sort(byOrder);
export const adminNav = features.flatMap((f) => f.adminNav ?? []).sort(byOrder);
export const dashboardCards = features.flatMap((f) => f.dashboardCards ?? []).sort(byOrder);
