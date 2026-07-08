/** @type {import('tailwindcss').Config} */
export default { darkMode: ['class'], content: ['./resources/**/*.blade.php', './resources/**/*.vue', './resources/**/*.ts'], theme: { extend: { colors: { brand: { DEFAULT: '#7c3aed', foreground: '#ffffff' } } } }, plugins: [require('tailwindcss-animate')] }
