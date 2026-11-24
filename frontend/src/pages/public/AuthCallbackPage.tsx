import { Navigate } from "react-router-dom";

import { Spinner } from "@/components/ui";
import { useMe } from "@/hooks/queries";

/** IITD redirects here after login; cookies are already set by the backend. */
export function AuthCallbackPage() {
  const { data: me, isLoading } = useMe();
  if (isLoading) return <Spinner />;
  if (!me) return <Navigate to="/login?error=iitd" replace />;
  return <Navigate to={me.profile_complete ? "/dashboard" : "/profile?onboarding=1"} replace />;
}
