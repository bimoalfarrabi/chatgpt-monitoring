/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './app/**/*.php',
    './app/Views/**/*.php',
    './resources/**/*.{js,css}'
  ],
  theme: {
    extend: {
      fontFamily: {
        display: ['Archivo', 'Avenir Next Condensed', 'Helvetica Neue', 'Helvetica', 'Arial', 'sans-serif'],
        serif: ['"Source Serif 4"', 'Iowan Old Style', 'Palatino Linotype', 'URW Palladio L', 'P052', 'Georgia', 'serif'],
        mono: ['"IBM Plex Mono"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', 'Liberation Mono', 'Courier New', 'monospace'],
        ui: ['Archivo', 'system-ui', '-apple-system', 'Segoe UI', 'Helvetica Neue', 'Arial', 'sans-serif'],
      },
      colors: {
        cream: '#f2f1ed',
        ink: '#26251e',
        accent: '#f54e00',
        danger: '#cf2d56',
        success: '#1f8a65',
        gold: '#c08532',
        surface100: '#f7f7f4',
        surface200: '#f2f1ed',
        surface300: '#ebeae5',
        surface400: '#e6e5e0',
        surface500: '#e1e0db',
      }
    },
  },
  plugins: [],
};
