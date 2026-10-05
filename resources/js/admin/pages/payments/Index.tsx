import { Head, Link } from '@inertiajs/react';
import { useI18n } from '@admin/i18n';
import { Badge, Card, EmptyState, PageHeader, Pagination, StatCard, Table, Td, statusTone } from '@admin/components/ui';
import { FilterBar, FilterDate, FilterSelect } from '@admin/components/Filters';
import { formatDateTime, formatMoney } from '@admin/lib/format';
import type { MoneyRow, Paginated } from '@admin/types';

type Props = {
    payments: Paginated<MoneyRow>;
    filters: Record<string, string>;
    gateways: string[];
    totals: Record<string, { count: number; amount: number }>;
};

export default function PaymentsIndex({ payments, filters, gateways, totals }: Props) {
    const { t, locale } = useI18n();
    const currency = payments.data[0]?.currency ?? 'SAR';

    return (
        <>
            <Head title={t('payments.heading')} />
            <PageHeader title={t('payments.heading')} subtitle={t('payments.subheading')} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {Object.entries(totals).map(([status, total]) => (
                    <StatCard
                        key={status}
                        label={t(`payments.status.${status}`, status)}
                        value={formatMoney(Number(total.amount), currency, locale)}
                        hint={`${t('common.count')}: ${total.count}`}
                    />
                ))}
            </div>

            <FilterBar action="/admin/payments" values={filters} searchPlaceholder={t('payments.searchPlaceholder')}>
                <FilterSelect
                    name="status"
                    label={t('common.status')}
                    options={['paid', 'pending', 'failed', 'refunded'].map((status) => ({
                        value: status,
                        label: t(`payments.status.${status}`, status),
                    }))}
                />
                <FilterSelect
                    name="gateway"
                    label={t('payments.gateway')}
                    options={gateways.map((gateway) => ({ value: gateway, label: gateway }))}
                />
                <FilterDate name="from" label={t('common.from')} />
                <FilterDate name="to" label={t('common.to')} />
            </FilterBar>

            <Card padded={false}>
                {payments.data.length === 0 ? (
                    <EmptyState />
                ) : (
                    <>
                        <Table
                            head={[
                                t('payments.reference'),
                                t('common.user'),
                                t('common.amount'),
                                t('payments.gateway'),
                                t('common.status'),
                                t('payments.paidAt'),
                            ]}
                        >
                            {payments.data.map((payment) => (
                                <tr key={payment.id} className="hover:bg-muted/40">
                                    <Td className="font-mono text-xs">{payment.reference}</Td>
                                    <Td>
                                        {payment.user ? (
                                            <Link
                                                href={`/admin/users/${payment.user.id}`}
                                                className="font-bold text-primary hover:underline"
                                            >
                                                {payment.user.name}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </Td>
                                    <Td className="tabular-nums font-bold">
                                        {formatMoney(Number(payment.amount), payment.currency, locale)}
                                    </Td>
                                    <Td>{payment.gateway ?? '—'}</Td>
                                    <Td>
                                        <Badge tone={statusTone(payment.status)}>
                                            {t(`payments.status.${payment.status}`, payment.status)}
                                        </Badge>
                                    </Td>
                                    <Td>{formatDateTime(payment.paid_at ?? payment.created_at, locale)}</Td>
                                </tr>
                            ))}
                        </Table>
                        <Pagination meta={payments} />
                    </>
                )}
            </Card>
        </>
    );
}
