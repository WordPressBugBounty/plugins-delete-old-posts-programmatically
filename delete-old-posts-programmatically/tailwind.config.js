/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.{html,php}",
    './inc/**/*.{html,php}'
  ],
  theme: {
    extend: {},
  },
  variants: {
    extend: {},
  },
  plugins: [
    require('tailwindcss'),
    require('autoprefixer'),
  ],
}