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
        'brand-purple': '#7A1B5D',
        'brand-purple-dark': '#5E1547',
        'brand-teal': '#1B5275',
        'brand-teal-dark': '#144058',
        'brand-blue': '#1B5275',
        'brand-light': '#2A7A9E',
        'brand-gold': '#FFBC00',
        'brand-gold-dark': '#E5A800',
        'brand-mint': '#8DB063',
        'brand-accent': '#E8EEF2',
        'brand-accent-teal': '#E8EEF2',
        brand: { DEFAULT: '#1B5275', foreground: '#ffffff' },
      },
      boxShadow: {
        card: '0 1px 3px rgba(15, 23, 42, 0.08)',
      },
    },
  },
  plugins: [require('tailwindcss-animate')],
}
