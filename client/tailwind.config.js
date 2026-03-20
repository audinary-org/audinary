import tailwindcss from "tailwindcss";

/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{vue,js,ts,jsx,tsx}"],
  theme: {
    extend: {
      colors: {
        primary: {
          50: "#eff6ff",
          100: "#dbeafe",
          200: "#bfdbfe",
          300: "#93c5fd",
          400: "#60a5fa",
          500: "#3b82f6",
          600: "#2563eb",
          700: "#1d4ed8",
          800: "#1e40af",
          900: "#1e3a8a",
        },
      },
      fontFamily: {
        sans: ["Segoe UI", "Tahoma", "Geneva", "Verdana", "sans-serif"],
      },
      spacing: {
        18: "4.5rem",
        88: "22rem",
      },
      screens: {
        xs: "475px",
      },
    },
  },
  plugins: [
    tailwindcss.plugin(({ addUtilities }) => {
      addUtilities({
        ".mobile-touch-target": {
          "min-height": "44px",
          "min-width": "44px",
          display: "flex",
          "align-items": "center",
          "justify-content": "center",
        },
        ".mobile-safe-area": {
          "padding-top": "env(safe-area-inset-top)",
          "padding-bottom": "env(safe-area-inset-bottom)",
          "padding-left": "env(safe-area-inset-left)",
          "padding-right": "env(safe-area-inset-right)",
        },
        ".mobile-keyboard-aware": {
          height: "100dvh",
          "max-height": "100dvh",
        },
      });
    }),
  ],
};
