import { defineConfig } from 'vite';

export default defineConfig({
  publicDir: false,
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
  },
  build: {
    manifest: true,
    outDir: 'public/build',
    emptyOutDir: true,
    rollupOptions: {
      input: 'resources/js/app.js',
    },
  },
});
