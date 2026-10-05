import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useI18n } from '@admin/i18n';
import { Badge, Button, Card, EmptyState, Field, Table, Td, inputClass } from '@admin/components/ui';
import { formatMoney } from '@admin/lib/format';

export type CreditPackageRow = {
    id: string;
    name: string;
    description?: string | null;
    price: string | number;
    currency: string;
    credits: number;
    is_active: boolean;
    sort_order?: number | null;
};

type PackageForm = {
    id: string | null;
    name: string;
    description: string;
    price: string;
    currency: string;
    credits: number;
    is_active: boolean;
    sort_order: number;
};

const emptyPackage: PackageForm = {
    id: null,
    name: '',
    description: '',
    price: '',
    currency: 'EGP',
    credits: 0,
    is_active: true,
    sort_order: 0,
};

/**
 * One-time credit packages (independent of monthly plans). Everything is
 * dynamic: price, currency and credits are entered here, never hardcoded.
 * Packages are deactivated instead of deleted so past payments keep their link.
 */
export default function CreditPackagesCard({ packages }: { packages: CreditPackageRow[] }) {
    const { t, locale } = useI18n();
    const form = useForm<PackageForm>(emptyPackage);
    const [editingId, setEditingId] = useState<string | null>(null);

    const select = (row: CreditPackageRow) => {
        setEditingId(row.id);
        form.clearErrors();
        form.setData({
            id: row.id,
            name: row.name,
            description: row.description ?? '',
            price: String(row.price),
            currency: row.currency,
            credits: row.credits,
            is_active: row.is_active,
            sort_order: row.sort_order ?? 0,
        });
    };

    const reset = () => {
        setEditingId(null);
        form.clearErrors();
        form.setData(emptyPackage);
    };

    const toggle = (row: CreditPackageRow) => {
        form.transform(() => ({
            id: row.id,
            name: row.name,
            description: row.description ?? '',
            price: String(row.price),
            currency: row.currency,
            credits: row.credits,
            is_active: !row.is_active,
            sort_order: row.sort_order ?? 0,
        }));
        form.post('/admin/settings/credit-packages', { preserveScroll: true, onFinish: () => form.transform((d) => d) });
    };

    return (
        <div className="grid gap-4 lg:grid-cols-[1fr_22rem]">
            <Card
                title="باقات الكريدت (شراء لمرة واحدة)"
                padded={false}
                action={
                    <Button variant="ghost" className="px-3 py-1.5 text-xs" onClick={reset}>
                        إضافة باقة
                    </Button>
                }
            >
                {packages.length === 0 ? (
                    <EmptyState />
                ) : (
                    <Table head={[t('common.name'), t('common.price'), t('settings.credits'), 'الترتيب', t('common.status'), '']}>
                        {packages.map((row) => (
                            <tr key={row.id} className="cursor-pointer hover:bg-muted/40" onClick={() => select(row)}>
                                <Td className="font-bold">{row.name}</Td>
                                <Td className="tabular-nums">{formatMoney(Number(row.price), row.currency, locale)}</Td>
                                <Td className="tabular-nums">{row.credits}</Td>
                                <Td className="tabular-nums">{row.sort_order ?? 0}</Td>
                                <Td>
                                    <Badge tone={row.is_active ? 'success' : 'neutral'}>
                                        {row.is_active ? t('common.active') : t('common.inactive')}
                                    </Badge>
                                </Td>
                                <Td>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="px-3 py-1 text-xs"
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            toggle(row);
                                        }}
                                    >
                                        {row.is_active ? 'إيقاف' : 'تفعيل'}
                                    </Button>
                                </Td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>

            <Card title={editingId ? 'تعديل باقة كريدت' : 'إضافة باقة كريدت'}>
                <form
                    className="space-y-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((d) => d);
                        form.post('/admin/settings/credit-packages', { preserveScroll: true, onSuccess: reset });
                    }}
                >
                    <Field label={t('common.name')} error={form.errors.name}>
                        <input required className={inputClass} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <Field label="الوصف" error={form.errors.description}>
                        <textarea rows={2} className={inputClass} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('common.price')} error={form.errors.price}>
                            <input required type="number" min={0.01} step="0.01" className={inputClass} value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} />
                        </Field>
                        <Field label={t('common.currency')} error={form.errors.currency}>
                            <input required maxLength={3} className={inputClass} value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value.toUpperCase())} />
                        </Field>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('settings.credits')} error={form.errors.credits}>
                            <input required type="number" min={1} className={inputClass} value={form.data.credits} onChange={(e) => form.setData('credits', Number(e.target.value))} />
                        </Field>
                        <Field label="الترتيب" error={form.errors.sort_order}>
                            <input type="number" min={0} className={inputClass} value={form.data.sort_order} onChange={(e) => form.setData('sort_order', Number(e.target.value))} />
                        </Field>
                    </div>
                    <label className="flex items-center gap-2 text-sm font-bold">
                        <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
                        {t('common.active')}
                    </label>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? t('common.saving', 'جاري الحفظ...') : t('common.save', 'حفظ')}
                        </Button>
                        {editingId && (
                            <Button type="button" variant="ghost" onClick={reset}>
                                {t('common.cancel')}
                            </Button>
                        )}
                    </div>
                </form>
            </Card>
        </div>
    );
}
