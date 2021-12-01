module.exports = {
  prefix: "ste-",
  purge: {
    enabled: true,
    content: ["./core/admin/*.php", "./core/admin/pages/**/*.php"],
  },
  darkMode: false,
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
  variants: {},
  plugins: [],
  corePlugins: {
    preflight: false,
  },
};
