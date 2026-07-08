import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'
import path from 'node:path'

export default defineConfig({
  plugins: [vue()],
  resolve: { alias: { '@': path.resolve(__dirname, 'resources/js') } },
  server: { host: '0.0.0.0', port: 5173, proxy: { '/api': { target: 'http://localhost:8000', changeOrigin: true }, '/sanctum': { target: 'http://localhost:8000', changeOrigin: true } } },
  build: { outDir: 'public/build', emptyOutDir: true, manifest: true }
})
