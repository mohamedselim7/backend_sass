import { Link } from '@inertiajs/react';
import clsx from 'clsx';
import type { ReactNode } from 'react';
import { useI18n } from '@admin/i18n';
import { formatNumber } from '@admin/lib/format';
import type { Paginated } from '@admin/types';

/* Small shared building blocks for every admin screen. Colors come from the
   design tokens in styles.css — never hardcode them here. */

export function Card({
    title,
    action,
    children,
    className,
    padded = true,
}: {
    title?: ReactNode;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
    padded?: boolean;
}) {
    return (
        <section className={clsx('surface overflow-hidden', className)}>
            {(title || action) && (
                <header className="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
                    <h2 className="text-base font-bold">{title}</h2>
                    {action}
                </header>
            )}
            <div className={padded ? 'p-5' : undefined}>{children}</div>
        </section>
    );
}

export function StatCard({
    label,
    value,
    hint,
    trend,
    icon,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    trend?: number | null;
    icon?: ReactNode;
}) {
    return (
        <div className="surface p-5">
            <div className="flex items-start justify-between gap-3">
                <p className="text-sm text-muted-foreground">{label}</p>
                {icon && (
                    <span className="grid size-9 place-items-center rounded-xl bg-secondary text-primary">{icon}</span>
                )}
            </div>
            <p className="mt-2 text-2xl font-extrabold tabular-nums">{value}</p>
            <div className="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                {typeof trend === 'number' && (
                    <span
                        className={clsx(
                            'rounded-full px-2 py-0.5 font-bold',
                            trend >= 0 ? 'bg-success/12 text-success' : 'bg-destructive/12 text-destructive',
                        )}
                    >
                        {trend >= 0 ? '+' : ''}
                        {trend}%
                    </span>
                )}
                {hint}
            </div>
        </div>
    );
}

const badgeTones = {
    neutral: 'bg-muted text-muted-foreground',
    primary: 'bg-secondary text-primary',
    success: 'bg-success/12 text-success',
    warning: 'bg-warning/18 text-warning-foreground',
    danger: 'bg-destructive/12 text-destructive',
} as const;

export type BadgeTone = keyof typeof badgeTones;

export function Badge({ tone = 'neutral', children }: { tone?: BadgeTone; children: ReactNode }) {
    return (
        <span className={clsx('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold', badgeTones[tone])}>
            {children}
        </span>
    );
}

export function statusTone(status?: string | null): BadgeTone {
    switch (status) {
        case 'active':
        case 'paid':
        case 'published':
        case 'resolved':
        case 'connected':
            return 'success';
        case 'pending':
        case 'trialing':
        case 'queued':
        case 'scheduled':
        case 'draft':
            return 'warning';
        case 'failed':
        case 'suspended':
        case 'cancelled':
        case 'closed':
            return 'danger';
        case 'open':
        case 'approved':
            return 'primary';
        default:
            return 'neutral';
    }
}

export function Table({ head, children }: { head: ReactNode[]; children: ReactNode }) {
    return (
        <div className="scroll-thin overflow-x-auto">
            <table className="w-full text-start text-sm">
                <thead className="bg-muted/60 text-xs uppercase text-muted-foreground">
                    <tr>
                        {head.map((cell, index) => (
                            <th key={index} className="whitespace-nowrap px-4 py-3 text-start font-bold">
                                {cell}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-border">{children}</tbody>
            </table>
        </div>
    );
}

export function Td({ children, className }: { children?: ReactNode; className?: string }) {
    return <td className={clsx('px-4 py-3 align-middle', className)}>{children ?? '—'}</td>;
}

export function EmptyState({ message }: { message?: string }) {
    const { t } = useI18n();

    return <p className="px-4 py-10 text-center text-sm text-muted-foreground">{message ?? t('common.empty')}</p>;
}

export function Pagination<T>({ meta }: { meta: Paginated<T> }) {
    const { t, locale } = useI18n();

    if (meta.last_page <= 1) return null;

    return (
        <nav className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3 text-sm">
            <p className="text-muted-foreground">
                {t('common.showing')} {formatNumber(meta.from ?? 0, locale)}–{formatNumber(meta.to ?? 0, locale)}{' '}
                {t('common.of')} {formatNumber(meta.total, locale)}
            </p>
            <div className="flex flex-wrap items-center gap-1">
                {meta.links.map((link, index) => (
                    <Link
                        key={index}
                        href={link.url ?? '#'}
                        preserveScroll
                        className={clsx(
                            'min-w-9 rounded-lg px-3 py-1.5 text-center font-bold transition',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : link.url
                                  ? 'bg-muted text-foreground hover:bg-secondary'
                                  : 'cursor-not-allowed text-muted-foreground/60',
                        )}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </div>
        </nav>
    );
}

export function Field({
    label,
    error,
    hint,
    children,
}: {
    label: string;
    error?: string;
    hint?: ReactNode;
    children: ReactNode;
}) {
    return (
        <label className="block space-y-1.5">
            <span className="text-sm font-bold">{label}</span>
            {children}
            {hint && <span className="block text-xs text-muted-foreground">{hint}</span>}
            {error && <span className="block text-xs font-bold text-destructive">{error}</span>}
        </label>
    );
}

export const inputClass =
    'w-full rounded-xl border border-input bg-card px-3 py-2 text-sm outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/25';

export function Button({
    variant = 'primary',
    className,
    type = 'button',
    ...props
}: React.ButtonHTMLAttributes<HTMLButtonElement> & { variant?: 'primary' | 'ghost' | 'danger' | 'soft' }) {
    return (
        <button
            type={type}
            className={clsx(
                'inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition disabled:cursor-not-allowed disabled:opacity-60',
                variant === 'primary' && 'bg-primary text-primary-foreground hover:bg-accent',
                variant === 'soft' && 'bg-secondary text-primary hover:bg-secondary/70',
                variant === 'ghost' && 'border border-border bg-card hover:bg-muted',
                variant === 'danger' && 'bg-destructive text-destructive-foreground hover:opacity-90',
                className,
            )}
            {...props}
        />
    );
}

export function PageHeader({ title, subtitle, action }: { title: string; subtitle?: string; action?: ReactNode }) {
    return (
        <header className="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 className="text-2xl font-extrabold">{title}</h1>
                {subtitle && <p className="mt-1 text-sm text-muted-foreground">{subtitle}</p>}
            </div>
            {action}
        </header>
    );
}
