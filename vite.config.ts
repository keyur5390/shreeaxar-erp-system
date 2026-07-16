import vue from '@vitejs/plugin-vue'
import { defineConfig, loadEnv } from 'vite'
import path from 'node:path'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const proxyTarget = env.VITE_DEV_PROXY_TARGET || 'http://localhost:8000'

  return {
    plugins: [vue()],
    resolve: { alias: { '@': path.resolve(__dirname, 'resources/js') } },
    server: {
      host: '0.0.0.0',
      port: Number(env.VITE_PORT || 5173),
      proxy: {
        '/api': { target: proxyTarget, changeOrigin: true },
        '/sanctum': { target: proxyTarget, changeOrigin: true },
      },
    },
    build: { outDir: 'public/build', emptyOutDir: true, manifest: true },
  }
})
