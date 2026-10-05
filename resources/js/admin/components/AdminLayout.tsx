import { Link, router, usePage } from '@inertiajs/react';
import clsx from 'clsx';
import {
    CreditCard,
    FileText,
    LayoutDashboard,
    LifeBuoy,
    Lightbulb,
    LogOut,
    Menu,
    Repeat,
    Settings,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { useI18n } from '@admin/i18n';
import { getEcho } from '@admin/lib/echo';
import { initials } from '@admin/lib/format';
import type { SharedProps } from '@admin/types';

type NavItem = {
    href: string;
    labelKey: string;
    icon: ReactNode;
    badge?: number;
    visible?: boolean;
};

export default function AdminLayout({ children }: { children: ReactNode }) {
    const page = usePage<SharedProps>();
    const { auth, badges, flash, locale } = page.props;
    const { t, dir } = useI18n();
    const [menuOpen, setMenuOpen] = useState(false);
    const [notice, setNotice] = useState<{ tone: 'success' | 'error'; text: string } | null>(null);

    useEffect(() => {
        document.documentElement.dir = dir;
        document.documentElement.lang = locale;
    }, [dir, locale]);

    useEffect(() => {
        if (flash?.success) setNotice({ tone: 'success', text: flash.success });
        else if (flash?.error) setNotice({ tone: 'error', text: flash.error });
        else setNotice(null);
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        if (!notice) return;
        const timer = window.setTimeout(() => setNotice(null), 5000);

        return () => window.clearTimeout(timer);
    }, [notice]);

    // Sidebar support badge follows conversation updates live.
    useEffect(() => {
        if (!auth.can.handleSupport) return;
        const echo = getEcho();
        if (!echo) return;
        const channel = echo.private('admin');
        const refresh = () => router.reload({ only: ['badges'] });
        channel.listen('.support.thread.updated', refresh);
        return () => {
            channel.stopListening('.support.thread.updated', refresh);
        };
    }, [auth.can.handleSupport]);

    const items: NavItem[] = [
        { href: '/admin', labelKey: 'nav.dashboard', icon: <LayoutDashboard className="size-4.5" /> },
        { href: '/admin/users', labelKey: 'nav.users', icon: <Users className="size-4.5" />, visible: auth.can.manageUsers },
        { href: '/admin/content', labelKey: 'nav.content', icon: <FileText className="size-4.5" /> },
        { href: '/admin/payments', labelKey: 'nav.payments', icon: <CreditCard className="size-4.5" /> },
        { href: '/admin/subscriptions', labelKey: 'nav.subscriptions', icon: <Repeat className="size-4.5" /> },
        {
            href: '/admin/support',
            labelKey: 'nav.support',
            icon: <LifeBuoy className="size-4.5" />,
            badge: badges?.support ?? 0,
            visible: auth.can.handleSupport,
        },
        { href: '/admin/credit-tips', labelKey: 'nav.creditTips', icon: <Lightbulb className="size-4.5" />, visible: auth.can.manageSettings },
        { href: '/admin/settings', labelKey: 'nav.settings', icon: <Settings className="size-4.5" />, visible: auth.can.manageSettings },
    ].filter((item) => item.visible !== false);

    const current = page.url.split('?')[0];
    const isActive = (href: string) => (href === '/admin' ? current === '/admin' : current.startsWith(href));

    const switchLocale = (next: 'ar' | 'en') => {
        if (next === locale) return;
        router.post('/admin/locale', { locale: next }, { preserveScroll: true });
    };

    return (
        <div dir={dir} className="min-h-screen lg:flex">
            {/* Sidebar */}
            <aside
                className={clsx(
                    'sidebar-canvas fixed inset-y-0 z-40 w-72 shrink-0 p-5 transition-transform lg:static lg:translate-x-0',
                    dir === 'rtl' ? 'right-0' : 'left-0',
                    menuOpen ? 'translate-x-0' : dir === 'rtl' ? 'translate-x-full' : '-translate-x-full',
                )}
            >
                <div className="flex items-center justify-between gap-3">
                    <Link href="/admin" className="flex items-center gap-3">
                        <img src="/images/iden-logo.png" alt="iden" className="size-10 rounded-xl bg-white/10 p-1" />
                        <span>
                            <span className="block text-base font-extrabold leading-tight">iden</span>
                            <span className="block text-xs opacity-70">{t('admin.title')}</span>
                        </span>
                    </Link>
                    <button className="lg:hidden" onClick={() => setMenuOpen(false)} aria-label={t('common.cancel')}>
                        <X className="size-5" />
                    </button>
                </div>

                <nav className="mt-8 space-y-1">
                    {items.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            onClick={() => setMenuOpen(false)}
                            className={clsx(
                                'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold transition',
                                isActive(item.href) ? 'bg-white/15' : 'hover:bg-white/8',
                            )}
                        >
                            {item.icon}
                            <span className="flex-1">{t(item.labelKey)}</span>
                            {!!item.badge && (
                                <span className="rounded-full bg-white/20 px-2 py-0.5 text-xs tabular-nums">{item.badge}</span>
                            )}
                        </Link>
                    ))}
                </nav>

                <div className="absolute bottom-5 start-5 end-5 space-y-3 text-xs opacity-80">
                    <div className="flex items-center gap-1 rounded-xl bg-white/10 p-1">
                        {(['ar', 'en'] as const).map((code) => (
                            <button
                                key={code}
                                onClick={() => switchLocale(code)}
                                className={clsx(
                                    'flex-1 rounded-lg px-2 py-1.5 font-bold transition',
                                    locale === code ? 'bg-white/25' : 'hover:bg-white/10',
                                )}
                            >
                                {code === 'ar' ? 'العربية' : 'English'}
                            </button>
                        ))}
                    </div>
                    <p>iden — The AI Arm of Your Business</p>
                </div>
            </aside>

            {menuOpen && (
                <button
                    className="fixed inset-0 z-30 bg-foreground/40 lg:hidden"
                    onClick={() => setMenuOpen(false)}
                    aria-hidden
                />
            )}

            {/* Content */}
            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-20 flex items-center gap-3 border-b border-border bg-card/90 px-4 py-3 backdrop-blur lg:px-8">
                    <button className="lg:hidden" onClick={() => setMenuOpen(true)} aria-label={t('common.filter')}>
                        <Menu className="size-5" />
                    </button>
                    <div className="flex-1" />
                    <div className="flex items-center gap-3">
                        <span className="hidden text-end text-sm leading-tight sm:block">
                            <span className="block font-bold">{auth.user?.name}</span>
                            <span className="block text-xs text-muted-foreground">{auth.user?.roles?.join(' · ')}</span>
                        </span>
                        <span className="grid size-9 place-items-center rounded-full bg-secondary text-sm font-extrabold text-primary">
                            {initials(auth.user?.name)}
                        </span>
                        <button
                            onClick={() => router.post('/admin/logout')}
                            className="rounded-xl border border-border p-2 transition hover:bg-muted"
                            aria-label={t('nav.signOut')}
                            title={t('nav.signOut')}
                        >
                            <LogOut className="size-4.5" />
                        </button>
                    </div>
                </header>

                {notice && (
                    <div
                        className={clsx(
                            'mx-4 mt-4 rounded-xl px-4 py-3 text-sm font-bold lg:mx-8',
                            notice.tone === 'success'
                                ? 'bg-success/12 text-success'
                                : 'bg-destructive/12 text-destructive',
                        )}
                    >
                        {notice.text}
                    </div>
                )}

                <main className="flex-1 space-y-6 p-4 lg:p-8">{children}</main>
            </div>
        </div>
    );
}
