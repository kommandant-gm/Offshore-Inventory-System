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
                // Keeping specific hexes for non-DaisyUI elements if needed
                "surface-light": "#ffffff",
                "surface-dark": "#1e293b",
            },
            boxShadow: {
                'glow': '0 0 20px -5px rgba(245, 158, 11, 0.3)',
                'soft': '0 4px 20px -2px rgba(0, 0, 0, 0.05)',
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
                    "primary": "#f59e0b", // Dayang Orange
                    "primary-content": "#ffffff",
                    "secondary": "#d97706",
                    "accent": "#f59e0b",
                    "neutral": "#334155",
                    "base-100": "#f1f5f9", // Background Light
                    "base-200": "#ffffff", // Surface Light
                    "base-300": "#e2e8f0",
                    "info": "#3abff8",
                    "success": "#22c55e",
                    "warning": "#fbbd23",
                    "error": "#ef4444",
                },
                dayangDark: {
                    "primary": "#f59e0b",
                    "primary-content": "#ffffff",
                    "secondary": "#d97706",
                    "accent": "#f59e0b",
                    "neutral": "#1e293b",
                    "base-100": "#0f172a", // Background Dark
                    "base-200": "#1e293b", // Surface Dark
                    "base-300": "#0f172a",
                    "info": "#3abff8",
                    "success": "#22c55e",
                    "warning": "#fbbd23",
                    "error": "#ef4444",
                },
            },
        ],
        darkTheme: "dayangDark",
    },
};