import vue from "@vitejs/plugin-vue";
import { defineConfig } from "vite";

// https://vite.dev/config/
export default defineConfig({
  base: "/cos30043/s105684522/nomnom/",
  plugins: [vue()],

  // request works like:
  // Vue requests:     /api/health.php
  // Vite forwards to: http://localhost/nomnom-api/health.php
  server: {
    proxy: {
      "/api": {
        target: "http://localhost",
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, "/nomnom-api"),
      },
    },
  },
});
