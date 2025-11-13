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
