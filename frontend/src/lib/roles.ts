export const GRANTABLE_ROLES = ["coordinator", "icpc_admin", "finance", "super_admin"] as const;
export type Role = "member" | (typeof GRANTABLE_ROLES)[number];

export const ROLE_LABELS: Record<Role, string> = {
  member: "Member",
  coordinator: "Coordinator",
  icpc_admin: "ICPC admin",
  finance: "Finance",
  super_admin: "Super admin",
};

export const ROLE_HELP: Record<(typeof GRANTABLE_ROLES)[number], string> = {
  coordinator: "Event highlights, results and photos",
  icpc_admin: "ICPC seasons, teams and registration on icpc.global",
  finance: "Prize and reimbursement status",
  super_admin: "Everything, plus roles, About page and audit log",
};

export function hasRole(userRoles: string[] | undefined, ...required: Role[]) {
  if (!userRoles) return false;
  if (userRoles.includes("super_admin")) return true;
  if (required.includes("member")) return true;
  return required.some((r) => userRoles.includes(r));
}

export function isAdmin(userRoles: string[] | undefined) {
  return hasRole(userRoles, "coordinator", "icpc_admin", "finance");
}
