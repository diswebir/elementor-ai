import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'assets/build',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        editor: 'assets/src/editor/main.tsx',
        admin: 'assets/src/admin/main.tsx'
      }
    }
  }
})