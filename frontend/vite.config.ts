import path from "node:path";
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import { defineConfig } from "vite";

// VITE_BASE lets the app live under a sub-path, e.g. VITE_BASE=/new/ for ancc.iitd.ac.in/new/
export default defineConfig({
  base: process.env.VITE_BASE || "/",
  plugins: [react(), tailwindcss()],
  resolve: { alias: { "@": path.resolve(__dirname, "src") } },
  server: {
    port: 5173,
    // The backend runs on :8000; proxying keeps cookies same-origin in dev.
    proxy: { "/api": "http://localhost:8000" },
  },
});
