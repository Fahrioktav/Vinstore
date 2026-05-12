import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import '../css/app.css';

const defaultTitle = 'Vinstore';

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.jsx', { eager: true });
    return pages[`./pages/${name}.jsx`].default;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
  title: (title) => (title ? `${title} - ${defaultTitle}` : defaultTitle),
});

// Register Service Worker for PWA
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker
      .register('/service-worker.js')
      .then((registration) => {
        console.log('✅ Service Worker registered successfully:', registration.scope);
        
        // Check for updates periodically
        setInterval(() => {
          registration.update();
        }, 60000); // Check every minute
      })
      .catch((error) => {
        console.log('❌ Service Worker registration failed:', error);
      });
  });
}
