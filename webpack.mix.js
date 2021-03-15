const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/js/app.js', 'public/js')
   .sass('resources/sass/style.scss', 'public/css/app.css')
   .extract(['vue']);

mix.webpackConfig({
    resolve: {
        alias: {
            '@components': path.resolve(__dirname, 'resources/js/components'),
            '@views': path.resolve(__dirname, 'resources/js/views/tenant'),
            '@helpers': path.resolve(__dirname, 'resources/js/helpers'),
            '@mixins': path.resolve(__dirname, 'resources/js/mixins'),
            '@viewsModuleAccount': path.resolve(__dirname, 'modules/Account/Resources/assets/js/views'),
            '@viewsModuleFinance': path.resolve(__dirname, 'modules/Finance/Resources/assets/js/views'),
            '@viewsModuleItem': path.resolve(__dirname, 'modules/Item/Resources/assets/js/views'),
        }
    }
}).sourceMaps()

mix.disableSuccessNotifications();
