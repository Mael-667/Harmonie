import { defineConfig } from 'vite'
  import react from '@vitejs/plugin-react'

export default defineConfig({
    plugins: [react()],
    publicDir: false,              // Symfony gère public/, on coupe la copie Vite
    build: {
      outDir: 'public/build',
      emptyOutDir: true,
      rollupOptions: {
        input: 'src/React/main.jsx',
        output: { entryFileNames: 'islands.js' },
      },
    },
  })