import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { Toaster } from '@/components/ui/sonner';
import { ThemeProvider } from '@/components/theme-provider';

const appName = (import.meta.env.VITE_APP_NAME as string | undefined) ?? 'PGS';

void createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <ThemeProvider defaultTheme="system" storageKey="pgs-theme">
                <App {...props} />
                <Toaster richColors position="top-right" />
            </ThemeProvider>,
        );
    },
    progress: {
        color: '#0b4aa2',
    },
});
