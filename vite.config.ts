import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  base: './',
  resolve: { alias: { '@': fileURLToPath(new URL('./src', import.meta.url)), '@/components': fileURLToPath(new URL('./components', import.meta.url)) } },
  server: { host: '0.0.0.0', allowedHosts: ['terminal.local'] },
  publicDir: 'wordpress/joyrent/assets',
  build: { outDir: 'wordpress/joyrent/assets/dist', emptyOutDir: true, copyPublicDir: false, manifest: true, rollupOptions: { input: 'src/main.tsx' } },
});
