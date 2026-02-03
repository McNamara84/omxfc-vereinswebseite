import defaultTheme from 'tailwindcss/defaultTheme';
import daisyui from 'daisyui';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/css/**/*.css',
        './resources/js/**/*.js',
        // maryUI Komponenten
        './vendor/robsontenorio/mary/src/View/Components/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [daisyui],

    // daisyUI Konfiguration
    daisyui: {
        themes: ['caramellatte', 'coffee'],
        darkTheme: 'coffee',
    },
};
