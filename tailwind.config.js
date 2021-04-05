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
    extend: {},
  },
  variants: {},
  plugins: [],
  corePlugins: {
    preflight: false,
  },
};
