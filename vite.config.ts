import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => ({
  plugins: [react()],
  // En desarrollo el base path es '/'. En producción se lee VITE_BASE_PATH
  // (permite /staging/ en staging y /gestion/ en producción sin tocar este archivo).
  base: mode === 'development' ? '/' : (process.env.VITE_BASE_PATH ?? '/gestion/'),
  server: {
    port: 3000,
    open: true,
    proxy: {
      '/backend-php': {
        target: 'http://localhost:8080',
        changeOrigin: true,
        secure: false,
      }
    }
  },
  build: {
    outDir: 'dist',
    sourcemap: false,
    rollupOptions: {
      output: {
        manualChunks: {
          vendor: ['react', 'react-dom'],
          forms: ['react-hook-form', 'zod', '@hookform/resolvers'],
          pdf: ['jspdf', 'jspdf-autotable'],
          animations: ['framer-motion']
        }
      }
    }
  }
}))
