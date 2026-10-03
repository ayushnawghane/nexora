import '../css/app.css';
import './bootstrap';

import { TooltipProvider } from '@/Components/ui/tooltip';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Nexora';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <TooltipProvider delayDuration={300}>
                <App {...props} />
            </TooltipProvider>,
        );
    },
    progress: {
        color: '#FF6B1A',
    },
});
