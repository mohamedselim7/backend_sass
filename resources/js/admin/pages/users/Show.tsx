import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useI18n } from '@admin/i18n';
import { Badge, Button, Card, EmptyState, Field, PageHeader, Table, Td, inputClass, statusTone } from '@admin/components/ui';
import { formatDate, formatDateTime, formatMoney, formatNumber } from '@admin/lib/format';
import type { MoneyRow, UserRow } from '@admin/types';

type Props = {
    user: UserRow & {
        phone?: string | null;
        locale?: string | null;
        email_verified_at?: string | null;
        last_login_at?: string | null;
    };
    roles: string[];
    subscriptions: {
        id: string;
        plan?: { id: string; name: string; price: string | number; currency: string } | null;
        status: string;
        starts_at?: string | null;
        ends_at?: string | null;
    }[];
    payments: MoneyRow[];
    creditLogs: {
        id: string;
        amount: number;
        reason: string;
        balance_after: number;
        feature?: string | null;
        note?: string | null;
        by_name?: string | null;
        created_at?: string | null;
    }[];
    canAdjustCredits: boolean;
    canChangePlan?: boolean;
    plans?: { id: string; name: string; code: string }[];
    currentPlanId?: string | null;
    wallet: { balance: number; lifetime_granted: number; lifetime_spent: number };
    currentSubscription: {
        plan?: string | null;
        plan_code?: string | null;
        monthly_credits: number;
        status: string;
        starts_at?: string | null;
        ends_at?: string | null;
        days_left?: number | null;
    } | null;
    stats: { total_paid: number; payments_count: number; support_threads: number; credits_used_30d: number };
};

const REASON_LABELS: Record<string, string> = {
    signup: 'رصيد ترحيبي',
    subscription_grant: 'رصيد الاشتراك',
    admin_adjustment: 'تعديل من الإدارة',
    refund: 'استرجاع',
    charge: 'استخدام',
};

export default function UserShow({ user, roles, subscriptions, payments, creditLogs, canAdjustCredits, canChangePlan = false, plans = [], currentPlanId = null, wallet, currentSubscription, stats }: Props) {
    const { t, locale } = useI18n();

    const statusForm = useForm({ status: user.status === 'active' ? 'suspended' : 'active' });
    const rolesForm = useForm<{ roles: string[] }>({ roles: user.roles });
    const planForm = useForm<{ plan_id: string }>({ plan_id: currentPlanId ?? '' });
    const creditsForm = useForm<{ direction: 'add' | 'deduct'; amount: string; note: string }>({ direction: 'add', amount: '', note: '' });

    const toggleRole = (role: string) => {
        rolesForm.setData(
            'roles',
            rolesForm.data.roles.includes(role)
                ? rolesForm.data.roles.filter((item) => item !== role)
                : [...rolesForm.data.roles, role],
        );
    };

    return (
        <>
            <Head title={user.name} />

            <PageHeader
                title={user.name}
                subtitle={user.email}
                action={
                    <Link
                        href="/admin/users"
                        className="inline-flex items-center gap-2 rounded-xl border border-border px-4 py-2 text-sm font-bold transition hover:bg-muted"
                    >
                        <ArrowLeft className="size-4 rtl:rotate-180" />
                        {t('common.back')}
                    </Link>
                }
            />

            <div className="grid gap-4 lg:grid-cols-3">
                <Card title={t('users.profile')}>
                    <dl className="space-y-3 text-sm">
                        {[
                            [t('common.status'), <Badge tone={statusTone(user.status)}>{t(`users.status.${user.status}`, user.status)}</Badge>],
                            [t('common.credits'), formatNumber(user.credits, locale)],
                            [t('common.plan'), user.plan ?? '—'],
                            ['—', user.email_verified_at ? t('users.verified') : t('users.notVerified')],
                            [t('common.created'), formatDate(user.created_at, locale)],
                            [t('common.lastLogin'), formatDateTime(user.last_login_at, locale)],
                        ].map(([label, value], index) => (
                            <div key={index} className="flex items-center justify-between gap-3">
                                <dt className="text-muted-foreground">{label}</dt>
                                <dd className="font-bold">{value}</dd>
                            </div>
                        ))}
                    </dl>

                    <form
                        className="mt-5 border-t border-border pt-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            statusForm.post(`/admin/users/${user.id}/status`, { preserveScroll: true });
                        }}
                    >
                        <Button type="submit" variant={user.status === 'active' ? 'danger' : 'primary'} className="w-full">
                            {user.status === 'active' ? t('users.suspend') : t('users.activate')}
                        </Button>
                    </form>
                </Card>

                <Card title={t('common.roles')}>
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            rolesForm.post(`/admin/users/${user.id}/roles`, { preserveScroll: true });
                        }}
                    >
                        {roles.map((role) => (
                            <label key={role} className="flex items-center gap-2 text-sm font-bold">
                                <input
                                    type="checkbox"
                                    className="size-4 rounded border-input"
                                    checked={rolesForm.data.roles.includes(role)}
                                    onChange={() => toggleRole(role)}
                                />
                                {role}
                            </label>
                        ))}
                        <Button type="submit" disabled={rolesForm.processing} className="w-full">
                            {rolesForm.processing ? t('common.saving') : t('users.saveRoles')}
                        </Button>
                    </form>
                </Card>

                <Card title={t('users.adjustCredits')}>
                    <div className="mb-4 grid grid-cols-3 gap-2 text-center text-xs">
                        <div className="rounded-xl bg-muted/50 p-2">
                            <p className="text-muted-foreground">الرصيد الحالي</p>
                            <p className="text-lg font-black tabular-nums">{formatNumber(wallet.balance, locale)}</p>
                        </div>
                        <div className="rounded-xl bg-muted/50 p-2">
                            <p className="text-muted-foreground">إجمالي المضاف</p>
                            <p className="font-bold tabular-nums">{formatNumber(wallet.lifetime_granted, locale)}</p>
                        </div>
                        <div className="rounded-xl bg-muted/50 p-2">
                            <p className="text-muted-foreground">إجمالي المستخدم</p>
                            <p className="font-bold tabular-nums">{formatNumber(wallet.lifetime_spent, locale)}</p>
                        </div>
                    </div>
                    {!canAdjustCredits ? (
                        <p className="text-sm text-muted-foreground">تعديل الرصيد متاح للمديرين فقط.</p>
                    ) : (
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const amount = Number(creditsForm.data.amount);
                            const verb = creditsForm.data.direction === 'add' ? 'إضافة' : 'خصم';
                            if (!window.confirm(`تأكيد ${verb} ${amount} كريدت لحساب ${user.name}؟`)) return;
                            creditsForm.transform((data) => ({ ...data, amount }));
                            creditsForm.post(`/admin/users/${user.id}/credits`, {
                                preserveScroll: true,
                                onSuccess: () => creditsForm.reset(),
                            });
                        }}
                    >
                        <div className="grid grid-cols-2 gap-2">
                            {(['add', 'deduct'] as const).map((direction) => (
                                <button
                                    key={direction}
                                    type="button"
                                    onClick={() => creditsForm.setData('direction', direction)}
                                    className={`rounded-xl border px-3 py-2 text-sm font-bold transition ${
                                        creditsForm.data.direction === direction
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border hover:bg-muted'
                                    }`}
                                >
                                    {direction === 'add' ? 'إضافة رصيد' : 'خصم رصيد'}
                                </button>
                            ))}
                        </div>
                        <Field label={t('users.creditsAmount')} error={creditsForm.errors.amount}>
                            <input
                                type="number"
                                required
                                min={1}
                                step={1}
                                className={inputClass}
                                value={creditsForm.data.amount}
                                onChange={(event) => creditsForm.setData('amount', event.target.value)}
                            />
                        </Field>
                        <Field label="السبب (مطلوب)" error={creditsForm.errors.note}>
                            <input
                                required
                                minLength={3}
                                className={inputClass}
                                value={creditsForm.data.note}
                                onChange={(event) => creditsForm.setData('note', event.target.value)}
                            />
                        </Field>
                        {creditsForm.data.direction === 'deduct' && Number(creditsForm.data.amount) > wallet.balance && (
                            <p className="text-xs font-bold text-destructive">المبلغ أكبر من الرصيد الحالي.</p>
                        )}
                        <Button
                            type="submit"
                            variant={creditsForm.data.direction === 'deduct' ? 'danger' : 'primary'}
                            disabled={creditsForm.processing || (creditsForm.data.direction === 'deduct' && Number(creditsForm.data.amount) > wallet.balance)}
                            className="w-full"
                        >
                            {creditsForm.processing ? t('common.saving') : creditsForm.data.direction === 'add' ? 'إضافة' : 'خصم'}
                        </Button>
                    </form>
                    )}
                </Card>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card title="الاشتراك الحالي">
                    {currentSubscription ? (
                        <div className="space-y-1 text-sm">
                            <p className="text-lg font-black">{currentSubscription.plan ?? '—'}</p>
                            <p className="text-muted-foreground">
                                {formatDate(currentSubscription.starts_at, locale)} ← {formatDate(currentSubscription.ends_at, locale)}
                            </p>
                            {currentSubscription.days_left != null && (
                                <p className="font-bold">متبقي {currentSubscription.days_left} يوم</p>
                            )}
                            <p className="text-muted-foreground">{formatNumber(currentSubscription.monthly_credits, locale)} كريدت شهريًا</p>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">لا يوجد اشتراك نشط (الخطة المجانية).</p>
                    )}
                    {canChangePlan && plans.length > 0 && (
                        <form
                            className="mt-3 space-y-2 border-t border-border pt-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                planForm.post(`/admin/users/${user.id}/plan`, { preserveScroll: true });
                            }}
                        >
                            <Field label="تغيير الخطة" error={planForm.errors.plan_id}>
                                <select
                                    className={inputClass}
                                    value={planForm.data.plan_id}
                                    onChange={(event) => planForm.setData('plan_id', event.target.value)}
                                >
                                    <option value="" disabled>اختر خطة</option>
                                    {plans.map((plan) => (
                                        <option key={plan.id} value={plan.id}>{plan.name}</option>
                                    ))}
                                </select>
                            </Field>
                            <Button
                                type="submit"
                                className="w-full px-3 py-1.5 text-xs"
                                disabled={planForm.processing || !planForm.data.plan_id || planForm.data.plan_id === currentPlanId}
                            >
                                حفظ الخطة
                            </Button>
                        </form>
                    )}
                </Card>
                <Card title="إجمالي المدفوع">
                    <p className="text-lg font-black tabular-nums">{formatMoney(stats.total_paid, payments[0]?.currency ?? 'EGP', locale)}</p>
                    <p className="text-sm text-muted-foreground">{formatNumber(stats.payments_count, locale)} عملية دفع</p>
                </Card>
                <Card title="استخدام آخر 30 يوم">
                    <p className="text-lg font-black tabular-nums">{formatNumber(stats.credits_used_30d, locale)}</p>
                    <p className="text-sm text-muted-foreground">كريدت مستخدم</p>
                </Card>
                <Card title="تذاكر الدعم">
                    <p className="text-lg font-black tabular-nums">{formatNumber(stats.support_threads, locale)}</p>
                    <Link href={`/admin/support?search=${encodeURIComponent(user.email)}`} className="text-sm font-bold text-primary hover:underline">
                        عرض المحادثات
                    </Link>
                </Card>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Card title={t('users.subscriptions')} padded={false}>
                    {subscriptions.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <Table head={[t('common.plan'), t('common.status'), t('subs.startsAt'), t('subs.endsAt')]}>
                            {subscriptions.map((sub) => (
                                <tr key={sub.id}>
                                    <Td>{sub.plan?.name}</Td>
                                    <Td>
                                        <Badge tone={statusTone(sub.status)}>{sub.status}</Badge>
                                    </Td>
                                    <Td>{formatDate(sub.starts_at, locale)}</Td>
                                    <Td>{formatDate(sub.ends_at, locale)}</Td>
                                </tr>
                            ))}
                        </Table>
                    )}
                </Card>

                <Card title={t('users.payments')} padded={false}>
                    {payments.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <Table head={[t('common.amount'), t('common.status'), t('payments.gateway'), t('common.date')]}>
                            {payments.map((payment) => (
                                <tr key={payment.id}>
                                    <Td className="tabular-nums">{formatMoney(Number(payment.amount), payment.currency, locale)}</Td>
                                    <Td>
                                        <Badge tone={statusTone(payment.status)}>{payment.status}</Badge>
                                    </Td>
                                    <Td>{payment.gateway}</Td>
                                    <Td>{formatDate(payment.created_at, locale)}</Td>
                                </tr>
                            ))}
                        </Table>
                    )}
                </Card>
            </div>

            <Card title={t('users.creditLog')} padded={false}>
                {creditLogs.length === 0 ? (
                    <EmptyState />
                ) : (
                    <Table head={[t('common.amount'), t('common.details'), t('common.total'), t('common.date')]}>
                        {creditLogs.map((log) => (
                            <tr key={log.id}>
                                <Td className="tabular-nums font-bold">
                                    {log.amount > 0 ? '+' : ''}
                                    {formatNumber(log.amount, locale)}
                                </Td>
                                <Td className="text-xs">
                                    <span className="font-bold">{REASON_LABELS[log.reason] ?? log.reason}</span>
                                    {log.feature && log.feature !== 'admin_adjustment' && <span className="text-muted-foreground"> · {log.feature}</span>}
                                    {log.note && <span className="block text-muted-foreground">{log.note}{log.by_name ? ` — ${log.by_name}` : ''}</span>}
                                </Td>
                                <Td className="tabular-nums">{formatNumber(log.balance_after, locale)}</Td>
                                <Td>{formatDateTime(log.created_at, locale)}</Td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>
        </>
    );
}
