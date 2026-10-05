import { Head, Link } from '@inertiajs/react';
import { CreditCard, LifeBuoy, TrendingUp, Users } from 'lucide-react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { useI18n } from '@admin/i18n';
import { Badge, Card, EmptyState, PageHeader, StatCard, Table, Td, statusTone } from '@admin/components/ui';
import { formatDate, formatDateTime, formatMonth, formatMoney, formatNumber, percentChange } from '@admin/lib/format';
import type { MoneyRow, Series, SupportThreadRow, UserRow } from '@admin/types';

type Metrics = {
    users: { total: number; active: number; new_this_month: number; new_last_month: number };
    subscriptions: { active: number; trialing: number; cancelled: number; expiring_soon: number };
    revenue: { this_month: number; last_month: number; lifetime: number; currency: string };
    payments: { successful_this_month: number; failed_this_month: number; pending: number };
    support: { open_threads: number; unread_messages: number; unassigned: number; replied_today: number };
    content: { posts: number; published: number; this_month: number };
};

type Props = {
    metrics: Metrics;
    revenueSeries: Series;
    userGrowthSeries: Series;
    subscriptionStatus: { status: string; total: number }[];
    recentPayments: MoneyRow[];
    recentSubscriptions: {
        id: string;
        user?: { id: string; name: string; email: string } | null;
        plan?: { id: string; name: string } | null;
        status: string;
        ends_at?: string | null;
    }[];
    recentUsers: UserRow[];
    expiringSubscriptions: {
        id: string;
        user?: { id: string; name: string; email: string } | null;
        plan?: { id: string; name: string } | null;
        ends_at?: string | null;
    }[];
    supportQueue: SupportThreadRow[];
};

const chartColors = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];

export default function Dashboard({
    metrics,
    revenueSeries,
    userGrowthSeries,
    subscriptionStatus,
    recentPayments,
    recentUsers,
    expiringSubscriptions,
    supportQueue,
}: Props) {
    const { t, locale } = useI18n();
    const currency = metrics.revenue.currency;

    const revenueData = revenueSeries.map((point) => ({ ...point, label: formatMonth(point.month, locale) }));
    const growthData = userGrowthSeries.map((point) => ({ ...point, label: formatMonth(point.month, locale) }));

    return (
        <>
            <Head title={t('dashboard.heading')} />
            <PageHeader title={t('dashboard.heading')} subtitle={t('dashboard.subheading')} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label={t('dashboard.users')}
                    value={formatNumber(metrics.users.total, locale)}
                    icon={<Users className="size-4.5" />}
                    trend={percentChange(metrics.users.new_this_month, metrics.users.new_last_month)}
                    hint={`${t('dashboard.newThisMonth')}: ${formatNumber(metrics.users.new_this_month, locale)}`}
                />
                <StatCard
                    label={t('dashboard.activeSubscribers')}
                    value={formatNumber(metrics.subscriptions.active, locale)}
                    icon={<TrendingUp className="size-4.5" />}
                    hint={`${t('subs.expiringSoon')}: ${formatNumber(metrics.subscriptions.expiring_soon, locale)}`}
                />
                <StatCard
                    label={t('dashboard.revenueThisMonth')}
                    value={formatMoney(metrics.revenue.this_month, currency, locale)}
                    icon={<CreditCard className="size-4.5" />}
                    trend={percentChange(metrics.revenue.this_month, metrics.revenue.last_month)}
                    hint={`${t('common.lifetime')}: ${formatMoney(metrics.revenue.lifetime, currency, locale)}`}
                />
                <StatCard
                    label={t('dashboard.openTickets')}
                    value={formatNumber(metrics.support.open_threads, locale)}
                    icon={<LifeBuoy className="size-4.5" />}
                    hint={`${t('common.unread')}: ${formatNumber(metrics.support.unread_messages, locale)}`}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Card title={t('dashboard.revenueTrend')} className="lg:col-span-2">
                    <div className="h-64">
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart data={revenueData}>
                                <defs>
                                    <linearGradient id="revenueFill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="var(--chart-2)" stopOpacity={0.45} />
                                        <stop offset="100%" stopColor="var(--chart-2)" stopOpacity={0.02} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid strokeDasharray="4 4" stroke="var(--border)" vertical={false} />
                                <XAxis dataKey="label" tick={{ fontSize: 12 }} stroke="var(--muted-foreground)" />
                                <YAxis tick={{ fontSize: 12 }} stroke="var(--muted-foreground)" width={70} />
                                <Tooltip
                                    formatter={(value: number) => formatMoney(value, currency, locale)}
                                    contentStyle={{ borderRadius: 12, border: '1px solid var(--border)' }}
                                />
                                <Area
                                    type="monotone"
                                    dataKey="total"
                                    stroke="var(--chart-2)"
                                    strokeWidth={2.5}
                                    fill="url(#revenueFill)"
                                />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                </Card>

                <Card title={t('dashboard.subscriptionStatus')}>
                    {subscriptionStatus.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie data={subscriptionStatus} dataKey="total" nameKey="status" innerRadius={55} outerRadius={85}>
                                        {subscriptionStatus.map((entry, index) => (
                                            <Cell key={entry.status} fill={chartColors[index % chartColors.length]} />
                                        ))}
                                    </Pie>
                                    <Tooltip contentStyle={{ borderRadius: 12, border: '1px solid var(--border)' }} />
                                </PieChart>
                            </ResponsiveContainer>
                        </div>
                    )}
                </Card>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Card title={t('dashboard.userGrowth')} className="lg:col-span-2">
                    <div className="h-56">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={growthData}>
                                <CartesianGrid strokeDasharray="4 4" stroke="var(--border)" vertical={false} />
                                <XAxis dataKey="label" tick={{ fontSize: 12 }} stroke="var(--muted-foreground)" />
                                <YAxis tick={{ fontSize: 12 }} stroke="var(--muted-foreground)" width={40} />
                                <Tooltip contentStyle={{ borderRadius: 12, border: '1px solid var(--border)' }} />
                                <Bar dataKey="total" radius={[8, 8, 0, 0]} fill="var(--chart-1)" />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </Card>

                <div className="grid gap-4">
                    <StatCard
                        label={t('dashboard.failedPayments')}
                        value={formatNumber(metrics.payments.failed_this_month, locale)}
                        hint={`${t('dashboard.pendingPayments')}: ${formatNumber(metrics.payments.pending, locale)}`}
                    />
                    <StatCard
                        label={t('dashboard.publishedContent')}
                        value={formatNumber(metrics.content.published, locale)}
                        hint={`${t('common.thisMonth')}: ${formatNumber(metrics.content.this_month, locale)}`}
                    />
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Card title={t('dashboard.recentPayments')} padded={false}>
                    {recentPayments.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <Table head={[t('common.user'), t('common.amount'), t('common.status'), t('common.date')]}>
                            {recentPayments.map((payment) => (
                                <tr key={payment.id}>
                                    <Td>{payment.user?.name}</Td>
                                    <Td className="tabular-nums">{formatMoney(Number(payment.amount), payment.currency, locale)}</Td>
                                    <Td>
                                        <Badge tone={statusTone(payment.status)}>{payment.status}</Badge>
                                    </Td>
                                    <Td>{formatDate(payment.created_at, locale)}</Td>
                                </tr>
                            ))}
                        </Table>
                    )}
                </Card>

                <Card title={t('dashboard.supportQueue')} padded={false}>
                    {supportQueue.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <Table head={[t('support.subject'), t('common.user'), t('common.status'), t('support.lastMessage')]}>
                            {supportQueue.map((thread) => (
                                <tr key={thread.id} className="hover:bg-muted/40">
                                    <Td>
                                        <Link href={`/admin/support/${thread.id}`} className="font-bold text-primary hover:underline">
                                            {thread.subject}
                                        </Link>
                                    </Td>
                                    <Td>{thread.user?.name}</Td>
                                    <Td>
                                        <Badge tone={statusTone(thread.status)}>{t(`support.status.${thread.status}`)}</Badge>
                                    </Td>
                                    <Td>{formatDateTime(thread.last_message_at, locale)}</Td>
                                </tr>
                            ))}
                        </Table>
                    )}
                </Card>

                <Card title={t('dashboard.recentUsers')} padded={false}>
                    {recentUsers.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <Table head={[t('common.name'), t('common.email'), t('common.status'), t('common.created')]}>
                            {recentUsers.map((user) => (
                                <tr key={user.id}>
                                    <Td>
                                        <Link href={`/admin/users/${user.id}`} className="font-bold text-primary hover:underline">
                                            {user.name}
                                        </Link>
                                    </Td>
                                    <Td>{user.email}</Td>
                                    <Td>
                                        <Badge tone={statusTone(user.status)}>{t(`users.status.${user.status}`, user.status)}</Badge>
                                    </Td>
                                    <Td>{formatDate(user.created_at, locale)}</Td>
                                </tr>
                            ))}
                        </Table>
                    )}
                </Card>

                <Card title={t('dashboard.expiring')} padded={false}>
                    {expiringSubscriptions.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <Table head={[t('common.user'), t('common.plan'), t('subs.endsAt')]}>
                            {expiringSubscriptions.map((sub) => (
                                <tr key={sub.id}>
                                    <Td>{sub.user?.name}</Td>
                                    <Td>{sub.plan?.name}</Td>
                                    <Td>{formatDate(sub.ends_at, locale)}</Td>
                                </tr>
                            ))}
                        </Table>
                    )}
                </Card>
            </div>

        </>
    );
}
