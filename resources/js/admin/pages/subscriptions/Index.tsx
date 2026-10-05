import { Head, Link, router } from '@inertiajs/react';
import { useI18n } from '@admin/i18n';
import { Badge, Button, Card, EmptyState, PageHeader, Pagination, StatCard, Table, Td, statusTone } from '@admin/components/ui';
import { FilterBar, FilterSelect } from '@admin/components/Filters';
import { formatDate, formatNumber } from '@admin/lib/format';
import type { Paginated } from '@admin/types';

type SubscriptionRow = {
    id: string;
    user?: { id: string; name: string; email: string } | null;
    plan?: { id: string; name: string } | null;
    status: string;
    starts_at?: string | null;
    ends_at?: string | null;
    cancelled_at?: string | null;
};

type Props = {
    subscriptions: Paginated<SubscriptionRow>;
    filters: Record<string, string>;
    plans: { id: string; name: string }[];
    summary: {
        active_subscribers: number;
        expiring_soon: number;
        status_breakdown: { status: string; total: number }[];
    };
};

export default function SubscriptionsIndex({ subscriptions, filters, plans, summary }: Props) {
    const { t, locale } = useI18n();

    const byStatus = (status: string) =>
        summary.status_breakdown.find((row) => row.status === status)?.total ?? 0;

    const cancel = (id: string, immediately: boolean) => {
        if (!window.confirm(immediately ? t('subs.confirmCancelNow') : t('subs.confirmCancel'))) return;
        router.post(`/admin/subscriptions/${id}/cancel`, { immediately }, { preserveScroll: true, preserveState: true });
    };

    return (
        <>
            <Head title={t('subs.heading')} />
            <PageHeader title={t('subs.heading')} subtitle={t('subs.subheading')} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label={t('subs.status.active')} value={formatNumber(summary.active_subscribers, locale)} />
                <StatCard label={t('subs.status.trialing')} value={formatNumber(byStatus('trialing'), locale)} />
                <StatCard label={t('subs.expiringSoon')} value={formatNumber(summary.expiring_soon, locale)} />
                <StatCard label={t('subs.status.cancelled')} value={formatNumber(byStatus('cancelled'), locale)} />
            </div>

            <FilterBar action="/admin/subscriptions" values={filters} searchPlaceholder={t('subs.searchPlaceholder')}>
                <FilterSelect
                    name="status"
                    label={t('common.status')}
                    options={['active', 'trialing', 'cancelled', 'expired'].map((status) => ({
                        value: status,
                        label: t(`subs.status.${status}`, status),
                    }))}
                />
                <FilterSelect
                    name="plan"
                    label={t('common.plan')}
                    options={plans.map((plan) => ({ value: plan.id, label: plan.name }))}
                />
            </FilterBar>

            <Card padded={false}>
                {subscriptions.data.length === 0 ? (
                    <EmptyState />
                ) : (
                    <>
                        <Table
                            head={[
                                t('common.user'),
                                t('common.plan'),
                                t('common.status'),
                                t('subs.startsAt'),
                                t('subs.endsAt'),
                                t('common.actions'),
                            ]}
                        >
                            {subscriptions.data.map((sub) => (
                                <tr key={sub.id} className="hover:bg-muted/40">
                                    <Td>
                                        {sub.user ? (
                                            <Link href={`/admin/users/${sub.user.id}`} className="font-bold text-primary hover:underline">
                                                {sub.user.name}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </Td>
                                    <Td>{sub.plan?.name ?? '—'}</Td>
                                    <Td>
                                        <Badge tone={statusTone(sub.status)}>{t(`subs.status.${sub.status}`, sub.status)}</Badge>
                                    </Td>
                                    <Td>{formatDate(sub.starts_at, locale)}</Td>
                                    <Td>{formatDate(sub.ends_at, locale)}</Td>
                                    <Td>
                                        {sub.cancelled_at ? (
                                            <span className="text-xs text-muted-foreground">
                                                {t('subs.cancelledOn')} {formatDate(sub.cancelled_at, locale)}
                                            </span>
                                        ) : (
                                            <span className="flex flex-wrap gap-2">
                                                <Button variant="ghost" className="px-3 py-1.5 text-xs" onClick={() => cancel(sub.id, false)}>
                                                    {t('subs.cancelAtPeriodEnd')}
                                                </Button>
                                                <Button variant="danger" className="px-3 py-1.5 text-xs" onClick={() => cancel(sub.id, true)}>
                                                    {t('subs.cancelNow')}
                                                </Button>
                                            </span>
                                        )}
                                    </Td>
                                </tr>
                            ))}
                        </Table>
                        <Pagination meta={subscriptions} />
                    </>
                )}
            </Card>
        </>
    );
}
