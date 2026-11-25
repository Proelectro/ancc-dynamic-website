import { api, type AuthConfig, type Dashboard, type ProfileUpdate, type User, type UserList } from "./client";

export const authApi = {
  config: () => api.get<AuthConfig>("/auth/config").then((r) => r.data),
  me: () => api.get<User>("/auth/me").then((r) => r.data),
  devLogin: (body: { kerberos_id: string; name?: string; roles?: string[] }) =>
    api.post<User>("/auth/dev-login", body).then((r) => r.data),
  logout: () => api.post("/auth/logout"),
};

export const userApi = {
  updateMe: (body: ProfileUpdate) => api.patch<User>("/users/me", body).then((r) => r.data),
  dashboard: () => api.get<Dashboard>("/dashboard").then((r) => r.data),
};

export type UserFilters = { q?: string; hostel?: string; role?: string; page?: number };

export const adminApi = {
  users: (f: UserFilters) =>
    api
      .get<UserList>("/admin/users", {
        params: { ...f, q: f.q || undefined, hostel: f.hostel || undefined, role: f.role || undefined },
      })
      .then((r) => r.data),
  user: (id: string) => api.get<User>(`/admin/users/${id}`).then((r) => r.data),
  setRoles: (id: string, roles: string[]) => api.put<User>(`/admin/users/${id}/roles`, { roles }).then((r) => r.data),
  update: (id: string, body: { is_active?: boolean; bank_details_on_file?: boolean }) =>
    api.patch<User>(`/admin/users/${id}`, body).then((r) => r.data),
};
