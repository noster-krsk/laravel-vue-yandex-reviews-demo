import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

export default defineConfig(({ command }) => ({
  base: command === 'build' ? '/dist/' : '/',
  plugins: [
    vue(),
    vueDevTools(),
  ],
  
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  
  server: {
    proxy: {
      '/api': {
        target: 'https://test1.one-vpn.ru',
        changeOrigin: true,
        secure: true
      },
      '/sanctum': {
        target: 'https://test1.one-vpn.ru',
        changeOrigin: true,
        secure: true
      }
    }
  },
  
  build: {
    outDir: '../backend/public/dist',
    emptyOutDir: true
  }
}))