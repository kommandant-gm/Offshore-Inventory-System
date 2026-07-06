import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import daisyui from 'daisyui';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class'], // Uses .dark class for standard Tailwind
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                "surface-light": "#ffffff",
                "surface-dark": "#f6fbf4",
            },
            boxShadow: {
                'glow': '0 18px 40px -18px rgba(79, 159, 74, 0.28)',
                'soft': '0 14px 36px -20px rgba(79, 159, 74, 0.18)',
            },
            animation: {
                'spin-slow': 'spin 3s linear infinite',
            }
        },
    },

    plugins: [forms, daisyui],

    daisyui: {
        themes: [
            {
                dayangLight: {
                    "primary": "#4f9f4a",
                    "primary-content": "#ffffff",
                    "secondary": "#6fbb68",
                    "accent": "#86c87b",
                    "neutral": "#365f33",
                    "base-100": "#f7fcf5",
                    "base-200": "#ffffff",
                    "base-300": "#e3eee0",
                    "info": "#3abff8",
                    "success": "#4f9f4a",
                    "warning": "#fbbd23",
                    "error": "#ef4444",
                },
                dayangDark: {
                    "primary": "#4f9f4a",
                    "primary-content": "#ffffff",
                    "secondary": "#6fbb68",
                    "accent": "#86c87b",
                    "neutral": "#365f33",
                    "base-100": "#f7fcf5",
                    "base-200": "#ffffff",
                    "base-300": "#e3eee0",
                    "info": "#3abff8",
                    "success": "#4f9f4a",
                    "warning": "#fbbd23",
                    "error": "#ef4444",
                },
            },
        ],
        darkTheme: "dayangDark",
    },
};
