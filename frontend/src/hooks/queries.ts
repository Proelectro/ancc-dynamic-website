import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { adminApi, authApi, userApi, type UserFilters } from "@/api/endpoints";

export const queryKeys = {
  config: ["auth", "config"] as const,
  me: ["auth", "me"] as const,
  dashboard: ["dashboard"] as const,
  adminUsers: (f: UserFilters) => ["admin", "users", f] as const,
  adminUser: (id: string) => ["admin", "user", id] as const,
};

export function useAuthConfig() {
  return useQuery({ queryKey: queryKeys.config, queryFn: authApi.config, staleTime: Infinity });
}

/** The logged-in user, or null. Never throws for "not logged in". */
export function useMe() {
  return useQuery({
    queryKey: queryKeys.me,
    queryFn: () => authApi.me().catch(() => null),
    staleTime: 60_000,
  });
}

export function useDashboard() {
  return useQuery({ queryKey: queryKeys.dashboard, queryFn: userApi.dashboard });
}

export function useUpdateProfile() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: userApi.updateMe,
    onSuccess: (user) => {
      qc.setQueryData(queryKeys.me, user);
      qc.invalidateQueries({ queryKey: queryKeys.dashboard });
    },
  });
}

/**
 * Logs out, then leaves the page before clearing the cached user. Clearing first would make
 * the protected layout redirect to /login and unmount the caller mid-way.
 */
export function useLogout() {
  const qc = useQueryClient();
  return async (goTo: (path: string) => void) => {
    await authApi.logout().catch(() => undefined);
    goTo("/");
    qc.setQueryData(queryKeys.me, null);
    qc.removeQueries({ predicate: (q) => q.queryKey[0] !== "auth" });
  };
}

export function useAdminUsers(f: UserFilters) {
  return useQuery({ queryKey: queryKeys.adminUsers(f), queryFn: () => adminApi.users(f), placeholderData: (p) => p });
}

export function useAdminUser(id: string) {
  return useQuery({ queryKey: queryKeys.adminUser(id), queryFn: () => adminApi.user(id) });
}

export function useSetRoles(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (roles: string[]) => adminApi.setRoles(id, roles),
    onSuccess: (u) => {
      qc.setQueryData(queryKeys.adminUser(id), u);
      qc.invalidateQueries({ queryKey: ["admin", "users"] });
    },
  });
}

export function useAdminUpdateUser(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: { is_active?: boolean; bank_details_on_file?: boolean }) => adminApi.update(id, body),
    onSuccess: (u) => {
      qc.setQueryData(queryKeys.adminUser(id), u);
      qc.invalidateQueries({ queryKey: ["admin", "users"] });
    },
  });
}
