import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import colors from 'tailwindcss/colors';
import typography from '@tailwindcss/typography';
import { getTertiaryColor } from '@/Utils/colorHelpers';

/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',

  content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    './resources/js/**/*.vue',
    './app/Domain/**/Schema/*.json',
  ],

  theme: {
    colors: {
      transparent: 'transparent',
      current: 'currentColor',
      // Blues
      primary: colors.blue,
      secondary: colors.emerald,
      tertiary: colors.purple,
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
      white: {
        DEFAULT: '#FFFFFF',
        50: '#FFFFFF',
        100: '#FDFBFD',
        200: '#FAF8FB',
        300: '#F7F4F8',
        400: '#F3F1F5',
        500: '#F5F3F5',
        600: '#EAE7EA',
        700: '#DAD6DA',
        800: '#C0BCBF',
        900: '#9C979C',
        950: '#7A7579',
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
      purple: {
        50:  '#F3F0FF',
        100: '#E8E1FF',
        200: '#D1C2FF',
        300: '#B39BFF',
        400: '#8C6CFF',
        500: '#6D46F7',
        600: '#5428EC', // Base color
        700: '#451FC7',
        800: '#3719A1',
        900: '#29137A',
        950: '#180A4D',
      },
      black: colors.black,
      gray: colors.gray,
      emerald: colors.emerald,
      orange: colors.orange,
      green: colors.green,
      indigo: colors.indigo,
      yellow: colors.yellow,
      cyan: colors.cyan,
      pink: colors.pink,
      amber: colors.amber,
      violet: colors.violet,
      red: colors.red,
      rose: colors.rose,
      blue: colors.blue,
      teal: colors.teal,
      fuchsia: colors.fuchsia,
      brown: colors.brown,
      slate: colors.slate,
      stone: colors.stone,
      sky: colors.sky,
    },
    extend: {
      fontFamily: {
        sans: ['Figtree', ...defaultTheme.fontFamily.sans],
      },
      // Slightly wider defaults so small / mini tablets keep “mobile” and `md`
      // layouts longer (e.g. iPad mini landscape stays below `lg`).
      screens: {
        sm: '704px',
        md: '832px',
        lg: '1184px',
        xl: '1344px',
        '2xl': '1600px',
      },
    },
  },

  plugins: [forms, typography],
};
