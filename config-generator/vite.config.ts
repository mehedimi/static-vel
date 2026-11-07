import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
    // Relative base so the built app works on GitHub Pages project URLs
    // (https://<user>.github.io/<repo>/) as well as locally.
    base: './',
    plugins: [vue(), tailwindcss()],
})
