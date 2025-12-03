import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import colors from 'tailwindcss/colors';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        colors: {
            transparent: 'transparent',
            current: 'currentColor',
            // Blues
            primary: {
              DEFAULT: '#274690',
              50: '#E6E9F7',
              100: '#CBD2F0',
              200: '#A6B4E4',
              300: '#7F95D7',
              400: '#5976CB',
              500: '#274690',
              600: '#20387A',
              700: '#1B2C5F',
              800: '#152144',
              900: '#0F1630',
              950: '#0B0E20',
            },
            secondary: {
              50: '#e6f9f7',
              100: '#c0f0eb',
              200: '#95e6de',
              300: '#66dcd0',
              400: '#3ccfbe',
              500: '#14c2ad',  // default
              600: '#0fa99b',  // slightly darker
              700: '#0b877a',
              800: '#076358',
              900: '#034137',
              950: '#01241f'
            },
            lightblue: {
              DEFAULT: '#576CA8',
              50: '#EBEEF7',
              100: '#D7DFF0',
              200: '#B0BAE0',
              300: '#8995D1',
              400: '#6170C1',
              500: '#576CA8',
              600: '#495992',
              700: '#3C4676',
              800: '#2F345B',
              900: '#222541',
              950: '#181A2A',
            },
            navy: {
              DEFAULT: '#1B264F',
              50: '#E3E6F2',
              100: '#C7CEE6',
              200: '#8EA0D3',
              300: '#566EBF',
              400: '#2B4FA9',
              500: '#1B264F',
              600: '#161F40',
              700: '#111731',
              800: '#0B0F22',
              900: '#060811',
              950: '#030406',
            },

            // Red Accent
            red: {
              DEFAULT: '#D1495B',
              50: '#FDE6E9',
              100: '#F9CBD2',
              200: '#F2A6B1',
              300: '#EB7F90',
              400: '#E35970',
              500: '#D1495B',
              600: '#B93B4F',
              700: '#972F40',
              800: '#751F30',
              900: '#52131F',
              950: '#3A0B15',
            },
          black: colors.black,
          gray: colors.gray,
          white: colors.white,
          emerald: colors.emerald,
          green: colors.green,
          indigo: colors.indigo,
          yellow: colors.yellow,
          cyan: colors.cyan,
          pink: colors.pink,
          amber: colors.amber,
          violet: colors.violet,
          red: colors.red,
          purple: colors.purple,
          rose: colors.rose,
          blue: colors.blue,
          teal: colors.teal,
          fuchsia: colors.fuchsia,
          brown: colors.brown,
        },
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms, typography],
};
