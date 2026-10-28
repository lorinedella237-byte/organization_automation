import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.scss',
                'resources/js/app.js',
            ],
            refresh: [
                'resources/views/**',
                'routes/**',
            ],
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3.x still uses Sass colour functions and @import,
                // both deprecated in Sass 1.80+. The warnings come from the
                // vendored library, not our stylesheets, and there is nothing to
                // fix on our side until Bootstrap 6. Silenced so real warnings
                // in our own SCSS stay visible.
                silenceDeprecations: [
                    'import',
                    'color-functions',
                    'global-builtin',
                    'if-function',
                ],
            },
        },
    },
    server: {
        host: '127.0.0.1',
    },
});
