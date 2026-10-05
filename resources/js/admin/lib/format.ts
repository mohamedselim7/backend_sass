import type { Locale } from '@admin/i18n';

export function formatMoney(amount: number, currency: string, locale: Locale): string {
    try {
        return new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-US', {
            style: 'currency',
            currency: currency || 'SAR',
            maximumFractionDigits: 2,
        }).format(amount);
    } catch {
        return `${amount.toFixed(2)} ${currency}`;
    }
}

export function formatNumber(value: number, locale: Locale): string {
    return new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-US').format(value);
}

export function formatDate(value?: string | null, locale: Locale = 'ar'): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA' : 'en-GB', {
        dateStyle: 'medium',
    }).format(new Date(value));
}

export function formatDateTime(value?: string | null, locale: Locale = 'ar'): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA' : 'en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export function formatMonth(value: string, locale: Locale): string {
    const [year, month] = value.split('-');

    return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA' : 'en-GB', {
        month: 'short',
        year: '2-digit',
    }).format(new Date(Number(year), Number(month) - 1, 1));
}

export function percentChange(current: number, previous: number): number | null {
    if (!previous) return current > 0 ? 100 : null;

    return Math.round(((current - previous) / previous) * 100);
}

export function initials(name?: string | null): string {
    if (!name) return '—';

    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('');
}
