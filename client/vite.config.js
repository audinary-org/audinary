import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { resolve } from "path";

export default defineConfig({
  plugins: [vue()],

  // Build configuration
  build: {
    outDir: "dist",
    sourcemap: false,
    rollupOptions: {
      output: {
        manualChunks: {
          // Vendor chunks for better caching
          "vue-vendor": ["vue", "vue-router", "pinia"],
          "audio-vendor": ["howler"],
        },
      },
    },
  },

  // Path aliases
  resolve: {
    alias: {
      "@": resolve(__dirname, "src"),
      "@components": resolve(__dirname, "src/components"),
      "@views": resolve(__dirname, "src/views"),
      "@stores": resolve(__dirname, "src/stores"),
      "@utils": resolve(__dirname, "src/utils"),
      "@assets": resolve(__dirname, "src/assets"),
      "@types": resolve(__dirname, "src/types"),
    },
  },

  // Development server
  server: {
    port: 3000,
    host: true, // Allow external connections
    allowedHosts: [".localhost", "dev.audinary.org"],
    cors: true, // Enable CORS for dev server
    proxy: {
      // Proxy API calls to backend server
      "/api": {
        target: "http://localhost:8080",
        changeOrigin: true,
        secure: false,
        configure: (proxy, options) => {
          proxy.on("proxyRes", (proxyRes, req, res) => {
            // Don't modify CORS headers - let backend handle them
            delete proxyRes.headers["access-control-allow-origin"];
          });
        },
      },
      // WebSocket proxy for real-time features
      "/ws": {
        target: "ws://localhost:8080",
        ws: true,
        changeOrigin: true,
      },
    },
  },

  // Environment variables
  define: {
    __VUE_OPTIONS_API__: true,
    __VUE_PROD_DEVTOOLS__: false,
    __APP_VERSION__: JSON.stringify(process.env.npm_package_version || "1.0.0"),
    __API_BASE_URL__: JSON.stringify(
      process.env.VITE_API_BASE_URL || "http://localhost:8080",
    ),
  },

  // CSS configuration
  css: {
    preprocessorOptions: {
      scss: {
        additionalData: `
          @import "@/styles/variables.scss";
          @import "@/styles/mixins.scss";
        `,
      },
    },
  },

  // Optimization
  optimizeDeps: {
    include: ["vue", "vue-router", "pinia", "howler"],
  },
});
