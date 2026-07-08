/** @type {import('tailwindcss').Config} */
export default {
  darkMode: ['class'],
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.vue',
    './resources/**/*.ts',
    './resources/js/**/*.{vue,ts}',
  ],
  theme: {
    extend: {
      colors: {
        'brand-blue': '#1F4E79',
        'brand-light': '#2E75B6',
        'brand-accent': '#D6E4F0',
        brand: { DEFAULT: '#1F4E79', foreground: '#ffffff' },
      },
      boxShadow: {
        card: '0 10px 30px rgba(31, 78, 121, 0.10)',
      },
    },
  },
  plugins: [require('tailwindcss-animate')],
}
