import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/js/app.js',
                // The in-room player gets its own bundle so the kiosk screen does
                // not carry the admin UI. Added back once those files exist.
            ],
            /*
             * The plugin's default list only covers views, routes, and lang.
             * The backend directories are added because editing a controller or
             * service and then wondering why the page looks unchanged is a
             * needless five seconds lost, many times a day.
             *
             * Deliberately not `app/**`: that would reload the browser on
             * changes to console commands and jobs, which never affect what is
             * on screen.
             */
            refresh: [
                'resources/views/**',
                'routes/**',
                'lang/**',
                'app/View/Components/**',
                'app/Http/**',
                'app/Services/**',
                'app/Repositories/**',
                'app/Models/**',
                'app/Enums/**',
                'app/Support/**',
                'config/**',
            ],
            fonts: [
                bunny('Plus Jakarta Sans', {
                    weights: [400, 500, 600, 700, 800],
                }),
                /*
                 * Display type, and only on the public front page's hero.
                 *
                 * Italic alone, because that is the only cut `.font-playfair`
                 * ever asks for, and not preloaded: every page would pay for a
                 * file one heading uses, and the heading fades in behind an
                 * animation long enough to cover the swap.
                 */
                bunny('Playfair Display', {
                    weights: [400],
                    styles: ['italic'],
                    preload: false,
                }),
            ],
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 still emits legacy @import deprecations under Dart Sass 3.
                silenceDeprecations: ['import', 'global-builtin', 'color-functions'],
            },
        },
    },
});
