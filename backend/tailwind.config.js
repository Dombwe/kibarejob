module.exports = {
  content: [
    './templates/**/*.html.twig',
    './src/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        primary: '#263849',
        accent: '#B8C7B4',
        secondary: '#5F7F99',
      },
      boxShadow: {
        soft: '0 24px 70px rgba(38, 56, 73, 0.12)',
      },
    },
  },
  plugins: [],
};
