import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useI18n } from '@admin/i18n';
import { Badge, Button, Card, EmptyState, Field, PageHeader, inputClass } from '@admin/components/ui';

type Tip = { id: string; title: string; body: string; is_active: boolean; sort_order: number };

const EMPTY = { title: '', body: '', is_active: true, sort_order: 0 };

export default function CreditTipsIndex({ tips }: { tips: Tip[] }) {
    const { t } = useI18n();
    const [editingId, setEditingId] = useState<string | null>(null);
    const form = useForm(EMPTY);

    const startEdit = (tip: Tip) => {
        setEditingId(tip.id);
        form.setData({ title: tip.title, body: tip.body, is_active: tip.is_active, sort_order: tip.sort_order });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const reset = () => {
        setEditingId(null);
        form.setData(EMPTY);
        form.clearErrors();
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: reset };
        if (editingId) form.patch(`/admin/credit-tips/${editingId}`, options);
        else form.post('/admin/credit-tips', options);
    };

    const remove = (id: string) => {
        if (!window.confirm(t('tips.confirmDelete'))) return;
        router.delete(`/admin/credit-tips/${id}`, { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('tips.heading')} />
            <PageHeader title={t('tips.heading')} subtitle={t('tips.subheading')} />

            <div className="mt-6 grid gap-6 lg:grid-cols-[minmax(0,420px)_1fr]">
                <Card title={editingId ? t('tips.edit') : t('tips.add')}>
                    <form onSubmit={submit} className="space-y-4">
                        <Field label={t('tips.title')} error={form.errors.title}>
                            <input className={inputClass} value={form.data.title} maxLength={160} onChange={(e) => form.setData('title', e.target.value)} />
                        </Field>
                        <Field label={t('tips.body')} error={form.errors.body}>
                            <textarea className={inputClass} rows={5} value={form.data.body} maxLength={4000} onChange={(e) => form.setData('body', e.target.value)} />
                        </Field>
                        <Field label={t('tips.order')}>
                            <input type="number" min={0} className={inputClass} value={form.data.sort_order} onChange={(e) => form.setData('sort_order', Number(e.target.value) || 0)} />
                        </Field>
                        <label className="flex items-center gap-2 text-sm font-bold">
                            <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
                            {t('tips.active')}
                        </label>
                        <div className="flex gap-2">
                            <Button type="submit" disabled={form.processing}>{t('tips.save')}</Button>
                            {editingId && <Button variant="ghost" onClick={reset}>{t('common.cancel')}</Button>}
                        </div>
                    </form>
                </Card>

                <div className="space-y-3">
                    {tips.length === 0 && <EmptyState message={t('tips.empty')} />}
                    {tips.map((tip) => (
                        <Card key={tip.id}>
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <h3 className="font-bold">{tip.title}</h3>
                                        <Badge tone={tip.is_active ? 'success' : 'neutral'}>{tip.is_active ? t('tips.active') : t('tips.hidden')}</Badge>
                                    </div>
                                    <p className="mt-2 whitespace-pre-line text-sm text-muted-foreground">{tip.body}</p>
                                </div>
                                <div className="flex gap-2">
                                    <Button variant="soft" onClick={() => startEdit(tip)}>{t('tips.edit')}</Button>
                                    <Button variant="danger" onClick={() => remove(tip.id)}>{t('tips.delete')}</Button>
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}
