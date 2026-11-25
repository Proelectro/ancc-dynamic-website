import axios, { AxiosError, type InternalAxiosRequestConfig } from "axios";

export type { AuthConfig, Dashboard, ProfileUpdate, User, UserList } from "./types";

/** "/" locally, "/new/" on the preview server (set by VITE_BASE at build time). */
export const BASE = import.meta.env.BASE_URL;

export const api = axios.create({ baseURL: `${BASE}api/v1`, withCredentials: true });

// On a 401, try one silent refresh (rotates the refresh cookie), then retry the request.
let refreshing: Promise<boolean> | null = null;

async function refreshOnce(): Promise<boolean> {
  refreshing ??= api
    .post("/auth/refresh", undefined, { headers: { "x-skip-refresh": "1" } })
    .then(() => true)
    .catch(() => false)
    .finally(() => {
      refreshing = null;
    });
  return refreshing;
}

api.interceptors.response.use(undefined, async (error: AxiosError) => {
  const config = error.config as (InternalAxiosRequestConfig & { _retried?: boolean }) | undefined;
  const skip = config?.headers?.["x-skip-refresh"];
  if (error.response?.status === 401 && config && !config._retried && !skip) {
    config._retried = true;
    if (await refreshOnce()) return api(config);
  }
  return Promise.reject(error);
});

/** Turns API error bodies (string or validation list) into one readable message. */
export function errorMessage(err: unknown, fallback = "Something went wrong"): string {
  if (err instanceof AxiosError) {
    const detail = (err.response?.data as { detail?: unknown } | undefined)?.detail;
    if (typeof detail === "string") return detail;
    if (Array.isArray(detail) && detail.length) {
      return detail.map((d: { msg?: string }) => (d.msg ?? "").replace(/^Value error, /, "")).join(" · ");
    }
  }
  return fallback;
}
