import type { Config } from 'tailwindcss';

const config: Config = {
  content: ['./src/**/*.{html,js,svelte,ts}'],
  theme: {
    extend: {
      colors: {
        ink: { 50:'#fafafa', 100:'#f5f5f5', 200:'#e5e5e5', 300:'#d4d4d4', 400:'#a3a3a3', 500:'#737373', 600:'#525252', 700:'#404040', 800:'#262626', 900:'#171717', 950:'#0a0a0a' },
        accent: { DEFAULT:'#6366f1', light:'#818cf8', dark:'#4f46e5' }
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
        display: ['"SF Pro Display"', 'Inter', 'system-ui', 'sans-serif']
      },
      letterSpacing: { tightest: '-0.04em' },
      maxWidth: { container: '1240px' },
      boxShadow: {
        soft: '0 1px 2px 0 rgb(0 0 0 / 0.04), 0 1px 3px 0 rgb(0 0 0 / 0.06)',
        elevated: '0 8px 30px -12px rgb(0 0 0 / 0.12)',
        glow: '0 0 0 4px rgb(99 102 241 / 0.12)'
      },
      animation: { fadeIn: 'fadeIn .25s ease', slideUp: 'slideUp .25s ease' },
      keyframes: {
        fadeIn:  { '0%':{opacity:'0'}, '100%':{opacity:'1'} },
        slideUp: { '0%':{opacity:'0', transform:'translateY(8px)'}, '100%':{opacity:'1', transform:'translateY(0)'} }
      }
    }
  },
  plugins: []
};
export default config;
