import type { Config } from 'tailwindcss'

const config: Config = {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.tsx',
    './src/**/*.{js,ts,jsx,tsx}',
  ],
  darkMode: 'class',
  theme: {
    extend: {
      // Header-specific design tokens
      colors: {
        header: {
          bg: 'var(--hdr-bg)',
          fg: 'var(--hdr-fg)',
          border: 'var(--hdr-border)',
          'bg-hover': 'var(--hdr-bg-hover)',
          'fg-muted': 'var(--hdr-fg-muted)',
        },
        nav: {
          active: 'var(--nav-active)',
          hover: 'var(--nav-hover)',
          'active-bg': 'var(--nav-active-bg)',
        }
      },
      height: {
        'header': 'var(--hdr-h)',
        'header-condensed': 'var(--hdr-h-condensed)',
      },
      boxShadow: {
        'header': 'var(--hdr-shadow)',
        'header-condensed': 'var(--hdr-shadow-condensed)',
      },
      spacing: {
        'header': 'var(--hdr-h)',
        'header-condensed': 'var(--hdr-h-condensed)',
      },
      animation: {
        'header-condense': 'headerCondense 0.2s ease-out',
        'slide-in': 'slideIn 0.3s ease-out',
        'fade-in': 'fadeIn 0.2s ease-out',
      },
      keyframes: {
        headerCondense: {
          '0%': { height: 'var(--hdr-h)' },
          '100%': { height: 'var(--hdr-h-condensed)' },
        },
        slideIn: {
          '0%': { transform: 'translateX(-100%)' },
          '100%': { transform: 'translateX(0)' },
        },
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
      },
      zIndex: {
        // Base Layer (0-10)
        'base': '0',
        'content': '1',
        'elevated': '2',
        'sticky': '10',
        
        // Navigation Layer (20-30)
        'nav': '20',
        'nav-sticky': '25',
        'nav-fixed': '30',
        
        // Dropdown Layer (40-50)
        'dropdown': '40',
        'dropdown-menu': '45',
        'dropdown-user': '50',
        
        // Overlay Layer (60-70)
        'overlay': '60',
        'modal': '65',
        'drawer': '70',
        
        // Critical Layer (80-90)
        'critical': '80',
        'loading': '85',
        'error': '90',
        
        // Debug Layer (100+)
        'debug': '100',
        
        // Specific Components
        'header': '30',
        'header-dropdown': '50',
        'notification': '45',
        'focus-mode': '40',
        'mobile-fab': '60',
        'mobile-nav': '70',
        'admin-header': '30',
        'admin-dropdown': '50',
        
        // Modal System
        'modal-backdrop': '60',
        'modal-content': '65',
        'modal-dialog': '70',
        
        // Toast/Notification System
        'toast': '80',
        'toast-container': '85',
        
        // Loading States
        'loading-overlay': '90',
        'loading-spinner': '95',
        
        // Development/Debug
        'debug-panel': '100',
        'debug-console': '105',
      }
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
}

export default config
