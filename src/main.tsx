import React from 'react';
import { createRoot } from 'react-dom/client';
import '@fontsource-variable/unbounded/index.css';
import '@fontsource-variable/manrope/index.css';
import './styles.css';
import App from './App';

const root = document.getElementById('joyrent-root');
if (root) createRoot(root).render(<React.StrictMode><App /></React.StrictMode>);
