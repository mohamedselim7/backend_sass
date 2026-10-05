import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, RotateCcw } from 'lucide-react';
import { useEffect } from 'react';
import { useI18n } from '@admin/i18n';
import { Button } from '@admin/components/ui';

/**
 * Every admin error (403/404/419/429/500/503) renders through this one screen,
 * with a retry affordance rather than a dead end.
 */
export default function ErrorPage({ status, message }: { status: number; message?: string | null }) {
    const { t, dir, locale } = useI18n();
    const known = [403, 404, 419, 429, 500, 503].includes(status) ? status : 500;

    useEffect(() => {
        document.documentElement.dir = dir;
        document.documentElement.lang = locale;
    }, [dir, locale]);

    const title = t(`error.${known}.title`);
    const body = message || t(`error.${known}.body`);

    return (
        <div dir={dir} className="grid min-h-screen place-items-center bg-background p-6">
            <Head title={title} />

            <div className="surface w-full max-w-md p-7 text-center">
                <span className="mx-auto grid size-14 place-items-center rounded-2xl bg-destructive/12 text-destructive">
                    <AlertTriangle className="size-7" />
                </span>
                <p className="mt-4 text-xs font-extrabold tracking-widest text-muted-foreground">{known}</p>
                <h1 className="mt-1 text-xl font-extrabold">{title}</h1>
                <p className="mt-2 text-sm text-muted-foreground">{body}</p>

                <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-center">
                    <Link
                        href="/admin"
                        className="inline-flex items-center justify-center rounded-xl border border-border px-4 py-2 text-sm font-bold transition hover:bg-muted"
                    >
                        {t('error.goHome')}
                    </Link>
                    <Button onClick={() => router.reload()}>
                        <RotateCcw className="size-4" />
                        {t('common.retry')}
                    </Button>
                </div>
            </div>
        </div>
    );
}
