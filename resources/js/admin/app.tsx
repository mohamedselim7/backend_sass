import './styles.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { I18nProvider, type Locale } from '@admin/i18n';
import AdminLayout from '@admin/components/AdminLayout';
import type { ComponentType, ReactNode } from 'react';

type PageModule = {
    default: ComponentType<Record<string, unknown>> & {
        layout?: (page: ReactNode) => ReactNode;
    };
};

const pages = import.meta.glob<PageModule>('./pages/**/*.tsx');

createInertiaApp({
    title: (title) => (title ? `${title} — iden` : 'iden Admin'),
    resolve: async (name) => {
        const importPage = pages[`./pages/${name}.tsx`];

        if (!importPage) {
            throw new Error(`Admin page not found: ${name}`);
        }

        const page = await importPage();

        // Auth and error screens render standalone; everything else gets the shell.
        if (!page.default.layout && !name.startsWith('auth/') && !name.startsWith('errors/')) {
            page.default.layout = (rendered: ReactNode) => <AdminLayout>{rendered}</AdminLayout>;
        }

        return page;
    },
    setup({ el, App, props }) {
        const locale = ((props.initialPage.props as { locale?: Locale }).locale ?? 'ar') as Locale;

        createRoot(el).render(
            <I18nProvider locale={locale}>
                <App {...props} />
            </I18nProvider>,
        );
    },
    progress: { color: '#5c1f85' },
});
