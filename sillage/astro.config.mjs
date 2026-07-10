import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';

export default defineConfig({
  site: 'https://sillage.example.com',
  trailingSlash: 'never',
  integrations: [sitemap()],
});
