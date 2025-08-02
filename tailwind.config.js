import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                gray: {
                    50: '#fff7ed',
                    100: '#fed17e',
                    200: '#e0b96b',
                    300: '#c19d57',
                    400: '#a38244',
                    500: '#856733',
                    600: '#664d24',
                    700: '#473417',
                    800: '#281b0c',
                    900: '#000000',
                },
                indigo: {
                    50: '#fbe4e6',
                    100: '#f5ccd1',
                    200: '#ea9aa3',
                    300: '#df6875',
                    400: '#d53647',
                    500: '#900316',
                    600: '#720211',
                    700: '#54010c',
                    800: '#360107',
                    900: '#180003',
                    950: '#0c0001',
                },
            },
        },
    },

    plugins: [forms, typography],
};
