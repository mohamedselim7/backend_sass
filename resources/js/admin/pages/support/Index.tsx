import { Head, Link, router } from '@inertiajs/react';
import { useEffect } from 'react';
import { getEcho } from '@admin/lib/echo';
import { useI18n } from '@admin/i18n';
import { Badge, Card, EmptyState, PageHeader, Pagination, StatCard, Table, Td, statusTone } from '@admin/components/ui';
import { FilterBar, FilterSelect } from '@admin/components/Filters';
import { formatDateTime, formatNumber } from '@admin/lib/format';
import type { Paginated, SupportThreadRow } from '@admin/types';

type Props = {
    threads: Paginated<SupportThreadRow>;
    filters: Record<string, string>;
    counts: { open: number; unread: number; unassigned: number; mine: number };
};

export default function SupportIndex({ threads, filters, counts }: Props) {
    const { t, locale } = useI18n();

    // Live inbox: new messages / unread / status changes refresh the list in place (no polling).
    useEffect(() => {
        const echo = getEcho();
        if (!echo) return;
        const channel = echo.private('admin');
        const refresh = () => router.reload({ only: ['threads', 'counts'] });
        channel.listen('.support.thread.updated', refresh);
        return () => {
            channel.stopListening('.support.thread.updated', refresh);
        };
    }, []);

    return (
        <>
            <Head title={t('support.heading')} />
            <PageHeader title={t('support.heading')} subtitle={t('support.subheading')} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label={t('support.openThreads')} value={formatNumber(counts.open, locale)} />
                <StatCard label={t('common.unread')} value={formatNumber(counts.unread, locale)} />
                <StatCard label={t('support.unassigned')} value={formatNumber(counts.unassigned, locale)} />
                <StatCard label={t('support.assignedToMe')} value={formatNumber(counts.mine, locale)} />
            </div>

            <FilterBar action="/admin/support" values={filters} searchPlaceholder={t('support.searchPlaceholder')}>
                <FilterSelect
                    name="status"
                    label={t('common.status')}
                    options={['open', 'pending', 'resolved', 'closed'].map((status) => ({
                        value: status,
                        label: t(`support.status.${status}`),
                    }))}
                />
                <FilterSelect
                    name="priority"
                    label={t('support.priority')}
                    options={['low', 'normal', 'high', 'urgent'].map((priority) => ({
                        value: priority,
                        label: t(`support.priorities.${priority}`),
                    }))}
                />
                <FilterSelect
                    name="assigned"
                    label={t('support.assignment')}
                    options={[
                        { value: 'me', label: t('support.assignedToMe') },
                        { value: 'unassigned', label: t('support.unassigned') },
                    ]}
                />
            </FilterBar>

            <Card padded={false}>
                {threads.data.length === 0 ? (
                    <EmptyState />
                ) : (
                    <>
                        <Table
                            head={[
                                t('support.subject'),
                                t('common.user'),
                                t('support.priority'),
                                t('support.assignee'),
                                t('common.status'),
                                t('support.lastMessage'),
                            ]}
                        >
                            {threads.data.map((thread) => (
                                <tr key={thread.id} className="hover:bg-muted/40">
                                    <Td>
                                        <Link href={`/admin/support/${thread.id}`} className="font-bold text-primary hover:underline">
                                            {thread.subject}
                                        </Link>
                                        {thread.unread > 0 && (
                                            <span className="ms-2"><Badge tone="danger">
                                                {formatNumber(thread.unread, locale)}
                                            </Badge></span>
                                        )}
                                    </Td>
                                    <Td>{thread.user?.name ?? '—'}</Td>
                                    <Td>
                                        <Badge tone={statusTone(thread.priority)}>{t(`support.priorities.${thread.priority}`)}</Badge>
                                    </Td>
                                    <Td>{thread.agent?.name ?? t('support.unassigned')}</Td>
                                    <Td>
                                        <Badge tone={statusTone(thread.status)}>{t(`support.status.${thread.status}`)}</Badge>
                                    </Td>
                                    <Td>{formatDateTime(thread.last_message_at, locale)}</Td>
                                </tr>
                            ))}
                        </Table>
                        <Pagination meta={threads} />
                    </>
                )}
            </Card>
        </>
    );
}
