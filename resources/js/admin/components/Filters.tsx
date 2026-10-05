import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { useI18n } from '@admin/i18n';
import { Button, inputClass } from '@admin/components/ui';

/**
 * Shared filter bar: submits as a GET to the current page so filters stay
 * shareable in the URL and the server keeps doing the querying.
 */
export function FilterBar({
    action,
    values,
    searchKey = 'search',
    searchPlaceholder,
    children,
}: {
    action: string;
    values: Record<string, string>;
    searchKey?: string;
    searchPlaceholder?: string;
    children?: ReactNode;
}) {
    const { t } = useI18n();
    const [form, setForm] = useState(values);

    const submit = (next: Record<string, string> = form) => {
        const query = Object.fromEntries(Object.entries(next).filter(([, value]) => value !== ''));
        router.get(action, query, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                submit();
            }}
            className="surface flex flex-wrap items-end gap-3 p-4"
        >
            <div className="relative min-w-56 flex-1">
                <Search className="pointer-events-none absolute top-1/2 size-4 -translate-y-1/2 text-muted-foreground start-3" />
                <input
                    className={`${inputClass} ps-9`}
                    placeholder={searchPlaceholder ?? t('common.search')}
                    value={form[searchKey] ?? ''}
                    onChange={(event) => setForm({ ...form, [searchKey]: event.target.value })}
                />
            </div>

            <FilterContext.Provider value={{ form, setForm, submit }}>{children}</FilterContext.Provider>

            <Button type="submit">{t('common.filter')}</Button>
            <Button
                variant="ghost"
                onClick={() => {
                    const cleared = Object.fromEntries(Object.keys(form).map((key) => [key, '']));
                    setForm(cleared);
                    submit(cleared);
                }}
            >
                {t('common.reset')}
            </Button>
        </form>
    );
}

import { createContext, useContext } from 'react';

const FilterContext = createContext<{
    form: Record<string, string>;
    setForm: (value: Record<string, string>) => void;
    submit: (next?: Record<string, string>) => void;
} | null>(null);

export function FilterSelect({
    name,
    label,
    options,
}: {
    name: string;
    label: string;
    options: { value: string; label: string }[];
}) {
    const { t } = useI18n();
    const context = useContext(FilterContext);

    if (!context) return null;

    return (
        <label className="space-y-1.5">
            <span className="block text-xs font-bold text-muted-foreground">{label}</span>
            <select
                className={inputClass}
                value={context.form[name] ?? ''}
                onChange={(event) => {
                    const next = { ...context.form, [name]: event.target.value };
                    context.setForm(next);
                    context.submit(next);
                }}
            >
                <option value="">{t('common.all')}</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </label>
    );
}

export function FilterDate({ name, label }: { name: string; label: string }) {
    const context = useContext(FilterContext);

    if (!context) return null;

    return (
        <label className="space-y-1.5">
            <span className="block text-xs font-bold text-muted-foreground">{label}</span>
            <input
                type="date"
                className={inputClass}
                value={context.form[name] ?? ''}
                onChange={(event) => context.setForm({ ...context.form, [name]: event.target.value })}
            />
        </label>
    );
}
