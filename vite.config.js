import { defineConfig } from 'vite'
import { resolve } from 'path'

// Static multi-page site (Startseite + Impressum + Datenschutz). Vite uses
// the listed HTML files as entries and outputs the production build to
// ./dist (Hostinger output directory).
export default defineConfig({
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'index.html'),
        impressum: resolve(__dirname, 'impressum/index.html'),
        datenschutz: resolve(__dirname, 'datenschutz/index.html'),
      },
    },
  },
  plugins: [
    {
      // Bakes the current year into the footer copyright notice at build
      // time, so it's present in the static HTML even before client JS runs.
      name: 'inject-build-year',
      transformIndexHtml(html) {
        return html.replace(/%YEAR%/g, String(new Date().getFullYear()))
      },
    },
  ],
})
