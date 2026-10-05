import { Head, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useI18n } from '@admin/i18n';
import { Badge, Button, Card, EmptyState, Field, PageHeader, Table, Td, inputClass, statusTone } from '@admin/components/ui';
import { formatMoney } from '@admin/lib/format';
import CreditPackagesCard, { type CreditPackageRow } from '@admin/components/CreditPackagesCard';

type Plan = {
    id: string | null;
    code: string;
    name: string;
    description: string;
    price: string;
    currency: string;
    interval: 'month' | 'year';
    monthly_credits: number;
    features: string[];
    is_active: boolean;
    sort_order: number;
    ai_model: string;
};

type PlanRow = {
    id: string;
    code: string;
    name: string;
    description?: string | null;
    price: string | number;
    currency: string;
    interval: 'month' | 'year';
    monthly_credits: number;
    features?: string[] | null;
    is_active: boolean;
    sort_order?: number | null;
    ai_model?: string | null;
};

type ProviderRow = {
    id: string;
    provider: string;
    default_model?: string | null;
    is_active: boolean;
    status?: string | null;
    key_hint: string;
    last_error?: string | null;
    verified_at?: string | null;
};

type Props = {
    settings: Record<string, string>;
    plans: PlanRow[];
    creditPackages?: CreditPackageRow[];
    providers: ProviderRow[];
    aiModels?: string[];
};

const AI_MODEL_LABELS: Record<string, string> = { openai: 'ChatGPT (OpenAI)', gemini: 'Gemini', nvidia: 'NVIDIA' };

const emptyPlan: Plan = {
    id: null,
    code: '',
    name: '',
    description: '',
    price: '0',
    currency: 'SAR',
    interval: 'month',
    monthly_credits: 0,
    features: [],
    is_active: true,
    sort_order: 0,
    ai_model: 'openai',
};

export default function SettingsIndex({ settings, plans, creditPackages = [], providers, aiModels = ['openai', 'gemini', 'nvidia'] }: Props) {
    const { t, locale } = useI18n();

    // The general form posts a single `settings` map, exactly as the controller validates.
    const generalForm = useForm<{ settings: Record<string, string> }>({
        settings: {
            app_name: settings.app_name ?? '',
            support_email: settings.support_email ?? '',
            default_locale: settings.default_locale ?? 'ar',
            signup_credits: settings.signup_credits ?? '0',
            maintenance_message: settings.maintenance_message ?? '',
        },
    });

    const setSetting = (key: string, value: string) =>
        generalForm.setData('settings', { ...generalForm.data.settings, [key]: value });

    const [editingId, setEditingId] = useState<string | null>(null);
    const planForm = useForm<Plan>(emptyPlan);
    const providerForm = useForm({ provider: '', api_key: '', default_model: '', is_active: true });

    const selectPlan = (plan: PlanRow) => {
        setEditingId(plan.id);
        planForm.setData({
            id: plan.id,
            code: plan.code,
            name: plan.name,
            description: plan.description ?? '',
            price: String(plan.price),
            currency: plan.currency,
            interval: plan.interval,
            monthly_credits: plan.monthly_credits,
            features: [...(plan.features ?? [])],
            is_active: plan.is_active,
            sort_order: plan.sort_order ?? 0,
            ai_model: plan.ai_model ?? 'openai',
        });
    };

    const [newFeature, setNewFeature] = useState('');
    const addFeature = () => {
        const value = newFeature.trim();
        if (!value || planForm.data.features.includes(value)) return;
        planForm.setData('features', [...planForm.data.features, value]);
        setNewFeature('');
    };
    const moveFeature = (index: number, delta: number) => {
        const next = [...planForm.data.features];
        const target = index + delta;
        if (target < 0 || target >= next.length) return;
        [next[index], next[target]] = [next[target], next[index]];
        planForm.setData('features', next);
    };
    const setFeature = (index: number, value: string) =>
        planForm.setData('features', planForm.data.features.map((item, i) => (i === index ? value : item)));
    const removeFeature = (index: number) =>
        planForm.setData('features', planForm.data.features.filter((_, i) => i !== index));

    const resetPlan = () => {
        setNewFeature('');
        setEditingId(null);
        planForm.setData(emptyPlan);
    };

    return (
        <>
            <Head title={t('settings.heading')} />
            <PageHeader title={t('settings.heading')} subtitle={t('settings.subheading')} />

            <Card title={t('settings.general')}>
                <form
                    className="grid gap-4 sm:grid-cols-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        generalForm.post('/admin/settings', { preserveScroll: true });
                    }}
                >
                    <Field label={t('settings.appName')}>
                        <input
                            className={inputClass}
                            value={generalForm.data.settings.app_name}
                            onChange={(event) => setSetting('app_name', event.target.value)}
                        />
                    </Field>
                    <Field label={t('settings.supportEmail')}>
                        <input
                            type="email"
                            className={inputClass}
                            value={generalForm.data.settings.support_email}
                            onChange={(event) => setSetting('support_email', event.target.value)}
                        />
                    </Field>
                    <Field label={t('settings.defaultLocale')}>
                        <select
                            className={inputClass}
                            value={generalForm.data.settings.default_locale}
                            onChange={(event) => setSetting('default_locale', event.target.value)}
                        >
                            <option value="ar">العربية</option>
                            <option value="en">English</option>
                        </select>
                    </Field>
                    <Field label={t('settings.signupCredits')}>
                        <input
                            type="number"
                            className={inputClass}
                            value={generalForm.data.settings.signup_credits}
                            onChange={(event) => setSetting('signup_credits', event.target.value)}
                        />
                    </Field>
                    <div className="sm:col-span-2">
                        <Field label={t('settings.maintenanceMessage')}>
                            <textarea
                                rows={3}
                                className={`${inputClass} resize-y`}
                                value={generalForm.data.settings.maintenance_message}
                                onChange={(event) => setSetting('maintenance_message', event.target.value)}
                            />
                        </Field>
                    </div>
                    <div className="sm:col-span-2">
                        <Button type="submit" disabled={generalForm.processing}>
                            {generalForm.processing ? t('common.saving') : t('common.save')}
                        </Button>
                    </div>
                </form>
            </Card>

            <div className="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card
                    title={t('settings.plans')}
                    padded={false}
                    action={
                        <Button variant="ghost" className="px-3 py-1.5 text-xs" onClick={resetPlan}>
                            {t('settings.addPlan')}
                        </Button>
                    }
                >
                    {plans.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <Table
                            head={[
                                t('common.plan'),
                                t('settings.planCode'),
                                t('common.price'),
                                t('settings.credits'),
                                t('common.status'),
                            ]}
                        >
                            {plans.map((plan) => (
                                <tr key={plan.id} className="cursor-pointer hover:bg-muted/40" onClick={() => selectPlan(plan)}>
                                    <Td className="font-bold">{plan.name}</Td>
                                    <Td className="font-mono text-xs">{plan.code}</Td>
                                    <Td className="tabular-nums">{formatMoney(Number(plan.price), plan.currency, locale)}</Td>
                                    <Td className="tabular-nums">{plan.monthly_credits}</Td>
                                    <Td>
                                        <Badge tone={plan.is_active ? 'success' : 'neutral'}>
                                            {plan.is_active ? t('common.active') : t('common.inactive')}
                                        </Badge>
                                    </Td>
                                </tr>
                            ))}
                        </Table>
                    )}
                </Card>

                <Card title={editingId ? t('settings.editPlan') : t('settings.addPlan')}>
                    <form
                        className="space-y-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            planForm.transform((data) => ({
                                ...data,
                                features: data.features.map((line) => line.trim()).filter(Boolean),
                            }));
                            planForm.post('/admin/settings/plans', {
                                preserveScroll: true,
                                onSuccess: resetPlan,
                            });
                        }}
                    >
                        <fieldset className="space-y-3">
                            <legend className="text-xs font-black uppercase tracking-wide text-muted-foreground">البيانات الأساسية</legend>
                            <Field label={t('common.name')} error={planForm.errors.name}>
                                <input
                                    required
                                    className={inputClass}
                                    value={planForm.data.name}
                                    onChange={(event) => planForm.setData('name', event.target.value)}
                                />
                            </Field>
                            <Field label={`${t('settings.planCode')} (حروف إنجليزية صغيرة وأرقام)`} error={planForm.errors.code}>
                                <input
                                    required
                                    pattern="[a-z0-9_-]+"
                                    className={`${inputClass} font-mono`}
                                    value={planForm.data.code}
                                    onChange={(event) => planForm.setData('code', event.target.value.toLowerCase())}
                                />
                            </Field>
                            <Field label="وصف قصير يظهر على الكارت" error={planForm.errors.description}>
                                <textarea
                                    rows={2}
                                    className={`${inputClass} resize-y`}
                                    value={planForm.data.description}
                                    onChange={(event) => planForm.setData('description', event.target.value)}
                                />
                            </Field>
                        </fieldset>

                        <fieldset className="space-y-3 border-t border-border pt-4">
                            <legend className="text-xs font-black uppercase tracking-wide text-muted-foreground">السعر والمدة</legend>
                            <div className="grid grid-cols-3 gap-3">
                                <Field label={t('settings.planPrice')} error={planForm.errors.price}>
                                    <input
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        className={inputClass}
                                        value={planForm.data.price}
                                        onChange={(event) => planForm.setData('price', event.target.value)}
                                    />
                                </Field>
                                <Field label={t('common.currency')} error={planForm.errors.currency}>
                                    <input
                                        className={inputClass}
                                        maxLength={3}
                                        value={planForm.data.currency}
                                        onChange={(event) => planForm.setData('currency', event.target.value.toUpperCase())}
                                    />
                                </Field>
                                <Field label={t('settings.planInterval')}>
                                    <select
                                        className={inputClass}
                                        value={planForm.data.interval}
                                        onChange={(event) => planForm.setData('interval', event.target.value as 'month' | 'year')}
                                    >
                                        <option value="month">{t('settings.interval.month')}</option>
                                        <option value="year">{t('settings.interval.year')}</option>
                                    </select>
                                </Field>
                            </div>
                            <p className="text-xs text-muted-foreground">السعر 0 يجعل الخطة مجانية.</p>
                        </fieldset>

                        <fieldset className="space-y-3 border-t border-border pt-4">
                            <legend className="text-xs font-black uppercase tracking-wide text-muted-foreground">موديل الذكاء الاصطناعي (داخلي — لا يظهر للمستخدم)</legend>
                            <Field label="الموديل" error={planForm.errors.ai_model}>
                                <select
                                    className={inputClass}
                                    value={planForm.data.ai_model}
                                    onChange={(event) => planForm.setData('ai_model', event.target.value)}
                                >
                                    {aiModels.map((model) => (
                                        <option key={model} value={model}>{AI_MODEL_LABELS[model] ?? model}</option>
                                    ))}
                                </select>
                            </Field>
                        </fieldset>

                        <fieldset className="space-y-3 border-t border-border pt-4">
                            <legend className="text-xs font-black uppercase tracking-wide text-muted-foreground">الكريدت</legend>
                            <Field label={t('settings.planCredits')} error={planForm.errors.monthly_credits}>
                                <input
                                    type="number"
                                    min={0}
                                    className={inputClass}
                                    value={planForm.data.monthly_credits}
                                    onChange={(event) => planForm.setData('monthly_credits', Number(event.target.value))}
                                />
                            </Field>
                        </fieldset>

                        <fieldset className="space-y-3 border-t border-border pt-4">
                            <legend className="text-xs font-black uppercase tracking-wide text-muted-foreground">المميزات (بنفس الترتيب على الكارت)</legend>
                            {planForm.data.features.length === 0 && (
                                <p className="text-xs text-muted-foreground">لا توجد مميزات بعد.</p>
                            )}
                            <ol className="space-y-2">
                                {planForm.data.features.map((feature, index) => (
                                    <li key={index} className="flex items-center gap-1">
                                        <span className="w-5 text-center text-xs font-bold text-muted-foreground">{index + 1}</span>
                                        <input
                                            className={`${inputClass} flex-1`}
                                            value={feature}
                                            onChange={(event) => setFeature(index, event.target.value)}
                                        />
                                        <Button type="button" variant="ghost" className="px-2 py-1.5" disabled={index === 0} onClick={() => moveFeature(index, -1)} aria-label="تحريك لأعلى">
                                            <ArrowUp className="size-4" />
                                        </Button>
                                        <Button type="button" variant="ghost" className="px-2 py-1.5" disabled={index === planForm.data.features.length - 1} onClick={() => moveFeature(index, 1)} aria-label="تحريك لأسفل">
                                            <ArrowDown className="size-4" />
                                        </Button>
                                        <Button type="button" variant="ghost" className="px-2 py-1.5" onClick={() => removeFeature(index)} aria-label="حذف">
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </li>
                                ))}
                            </ol>
                            <div className="flex gap-2">
                                <input
                                    className={`${inputClass} flex-1`}
                                    placeholder="ميزة جديدة"
                                    value={newFeature}
                                    onChange={(event) => setNewFeature(event.target.value)}
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            addFeature();
                                        }
                                    }}
                                />
                                <Button type="button" variant="ghost" onClick={addFeature}>
                                    <Plus className="size-4" />
                                </Button>
                            </div>
                        </fieldset>

                        <fieldset className="space-y-3 border-t border-border pt-4">
                            <legend className="text-xs font-black uppercase tracking-wide text-muted-foreground">الظهور</legend>
                            <div className="grid grid-cols-2 items-end gap-3">
                                <Field label="ترتيب العرض" error={planForm.errors.sort_order}>
                                    <input
                                        type="number"
                                        min={0}
                                        className={inputClass}
                                        value={planForm.data.sort_order}
                                        onChange={(event) => planForm.setData('sort_order', Number(event.target.value))}
                                    />
                                </Field>
                                <label className="flex items-center gap-2 pb-2 text-sm font-bold">
                                    <input
                                        type="checkbox"
                                        className="size-4 rounded border-input"
                                        checked={planForm.data.is_active}
                                        onChange={(event) => planForm.setData('is_active', event.target.checked)}
                                    />
                                    {t('settings.planActive')}
                                </label>
                            </div>
                        </fieldset>

                        <div className="flex gap-2">
                            <Button type="submit" disabled={planForm.processing} className="flex-1">
                                {planForm.processing ? t('common.saving') : t('common.save')}
                            </Button>
                            {editingId && (
                                <Button variant="ghost" onClick={resetPlan}>
                                    {t('common.cancel')}
                                </Button>
                            )}
                        </div>
                    </form>
                </Card>
            </div>

            <CreditPackagesCard packages={creditPackages} />

            <div className="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card title={t('settings.providers')} padded={false}>
                    {providers.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <Table
                            head={[
                                t('settings.provider'),
                                t('settings.defaultModel'),
                                t('settings.keyHint'),
                                t('common.status'),
                                t('common.actions'),
                            ]}
                        >
                            {providers.map((provider) => (
                                <tr key={provider.id}>
                                    <Td className="font-bold">{provider.provider}</Td>
                                    <Td>{provider.default_model}</Td>
                                    <Td className="font-mono text-xs">{provider.key_hint}</Td>
                                    <Td>
                                        <Badge tone={statusTone(provider.status)}>
                                            {provider.status ?? (provider.is_active ? t('common.active') : t('common.inactive'))}
                                        </Badge>
                                    </Td>
                                    <Td>
                                        <Button
                                            variant="danger"
                                            className="px-3 py-1.5 text-xs"
                                            onClick={() => {
                                                if (!window.confirm(t('settings.confirmDeleteKey'))) return;
                                                router.delete(`/admin/settings/providers/${provider.id}`, { preserveScroll: true });
                                            }}
                                        >
                                            <Trash2 className="size-3.5" />
                                            {t('settings.deleteKey')}
                                        </Button>
                                    </Td>
                                </tr>
                            ))}
                        </Table>
                    )}
                </Card>

                <Card title={t('settings.addProviderKey')}>
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            providerForm.post('/admin/settings/providers', {
                                preserveScroll: true,
                                onSuccess: () => providerForm.reset(),
                            });
                        }}
                    >
                        <Field label={t('settings.provider')} error={providerForm.errors.provider}>
                            <input
                                required
                                className={inputClass}
                                value={providerForm.data.provider}
                                onChange={(event) => providerForm.setData('provider', event.target.value)}
                            />
                        </Field>
                        <Field label={t('settings.apiKey')} error={providerForm.errors.api_key}>
                            <input
                                required
                                type="password"
                                className={inputClass}
                                value={providerForm.data.api_key}
                                onChange={(event) => providerForm.setData('api_key', event.target.value)}
                            />
                        </Field>
                        <Field label={t('settings.defaultModel')} error={providerForm.errors.default_model}>
                            <input
                                className={inputClass}
                                value={providerForm.data.default_model}
                                onChange={(event) => providerForm.setData('default_model', event.target.value)}
                            />
                        </Field>
                        <label className="flex items-center gap-2 text-sm font-bold">
                            <input
                                type="checkbox"
                                className="size-4 rounded border-input"
                                checked={providerForm.data.is_active}
                                onChange={(event) => providerForm.setData('is_active', event.target.checked)}
                            />
                            {t('common.active')}
                        </label>
                        <p className="text-xs text-muted-foreground">{t('settings.keyNeverShown')}</p>
                        <Button type="submit" disabled={providerForm.processing} className="w-full">
                            {providerForm.processing ? t('common.saving') : t('common.save')}
                        </Button>
                    </form>
                </Card>
            </div>
        </>
    );
}
