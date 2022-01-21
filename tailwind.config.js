const colors = require("tailwindcss/colors");

module.exports = {
  prefix: "ste-",
  content: ["./core/admin/*.php", "./core/admin/pages/**/*.php"],
  theme: {
    container: {
      screens: {
        sm: "640px",
        md: "768px",
        lg: "1024px",
        xl: "1280px",
      },
    },
    extend: {
      colors: {
        current: "currentColor",
        slate: colors.slate,
        gray: colors.gray,
        zinc: colors.zinc,
        neutral: colors.neutral,
        stone: colors.stone,
        red: colors.red,
        orange: colors.orange,
        amber: colors.amber,
        yellow: colors.yellow,
        lime: colors.lime,
        green: colors.green,
        emerald: colors.emerald,
        teal: colors.teal,
        cyan: colors.cyan,
        sky: colors.sky,
        blue: colors.blue,
        indigo: colors.indigo,
        violet: colors.violet,
        purple: colors.purple,
        fuchsia: colors.fuchsia,
        pink: colors.pink,
        rose: colors.rose,
        ash: {
          100: "#FCFCFC",
          200: "#F8F8F8",
          300: "#F4F4F4",
          400: "#EBEBEB",
          500: "#E3E3E3",
          600: "#CCCCCC",
          700: "#888888",
          800: "#666666",
          900: "#444444",
        },
      },
    },
  },
  plugins: [],
  corePlugins: {
    preflight: false,
  },
};
