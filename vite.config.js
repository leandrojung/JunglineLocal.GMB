import { defineConfig } from 'vite'

// Static single-page site. Vite uses the root index.html as the entry
// and outputs the production build to ./dist (Hostinger output directory).
export default defineConfig({
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
})
