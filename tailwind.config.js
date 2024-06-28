/** @type {import('tailwindcss').Config} */

const colors = {
  primary: {
      50: "#e8ecfe",
      100: "#d2dafd",
      200: "#a7b6fa",
      300: "#7b92f8",
      400: "#506ef5",
      500: "#244af3",
      600: "#0c34e2",
      700: "#0a2bbb",
      800: "#082294",
      900: "#06196e",
  },
};

module.exports = {
  content: [
    './js/**/*.vue',
    './js/**/*.js',
    './**/*.php'
  ],
  prefix: 'tw-',
  theme: {
    extend: {
      colors
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
}
