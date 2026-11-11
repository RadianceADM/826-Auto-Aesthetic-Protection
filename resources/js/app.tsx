import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';

import '../css/app.css';
import Router from './Router';

const rootElement = document.getElementById('app');

// The demo bar makes the sticky header taller: anchors land under it, not behind it
if (window.APP826.demo) {
    document.documentElement.style.setProperty('--demo-bar', '2rem');
}

if (rootElement) {
    ReactDOM.createRoot(rootElement).render(
        <React.StrictMode>
            <BrowserRouter basename={window.APP826.base || undefined}>
                <Router />
            </BrowserRouter>
        </React.StrictMode>,
    );
}
