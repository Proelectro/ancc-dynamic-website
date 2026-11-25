/*
 * API shapes returned by the PHP backend (backend/src). Keep in sync with Users::out()
 * and the controllers when you change a response.
 */

export interface User {
  id: string;
  kerberos_id: string;
  name: string;
  iitd_email: string;
  department: string | null;
  year_of_study: number | null;
  phone: string | null;
  alt_email: string | null;
  hostel: string | null;
  codeforces: string | null;
  codechef: string | null;
  avatar_url: string | null;
  is_active: boolean;
  profile_complete: boolean;
  roles: string[];
  last_login_at: string | null;
}

export interface UserList {
  items: User[];
  total: number;
  page: number;
  page_size: number;
}

/** A dashboard section whose feature isn't built yet. */
export interface SectionStub {
  available: boolean;
  phase: string;
}

export interface Dashboard {
  profile: User;
  profile_missing: string[];
  payouts: SectionStub;
  icpc: SectionStub;
}

/** Fields a member may edit. Empty string clears a field. */
export interface ProfileUpdate {
  phone?: string | null;
  alt_email?: string | null;
  hostel?: string | null;
  codeforces?: string | null;
  codechef?: string | null;
}

export interface AuthConfig {
  mode: "dev" | "iitd";
  hostels: string[];
}
