/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './**/*.php',
    './assets/**/*.js',
    '!./node_modules/**',
    '!./vendor/**',
  ],
  safelist: [
    // patient_view.php — wound status colours built via string interpolation: 'emerald','amber','red'
    { pattern: /^(bg|text|border|ring)-(emerald|amber|red)-(100|300|700)$/ },
    { pattern: /^(border|text)-(emerald|amber|red)-(300|700)$/, variants: ['hover'] },
    'ring-2',
    // medicare_awv.php — sectionHeader() builds sky-* classes via concatenation
    'bg-sky-50', 'border-sky-200', 'text-sky-600', 'text-sky-700',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
      colors: {
        brand: {
          50:  '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
          950: '#172554',
        },
      },
    },
  },
  plugins: [],
};
