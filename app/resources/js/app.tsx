import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { PgsToastViewport, ToastProvider } from '@/components/pgs-toast';

const appName = (import.meta.env.VITE_APP_NAME as string | undefined) ?? 'PGS';

void createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <ToastProvider>
                <App {...props} />
                <PgsToastViewport />
            </ToastProvider>,
        );
    },
    // Inertia's default progress bar injects CSS and mutates inline styles,
    // which is incompatible with the nonce-only CSP used by this app.
    progress: false,
});
