import { Head, router } from '@inertiajs/react';
import { useI18n } from '@admin/i18n';
import { Badge, Button, Card, EmptyState, PageHeader, Pagination, Table, Td, statusTone } from '@admin/components/ui';
import { FilterBar, FilterSelect } from '@admin/components/Filters';
import { formatDate } from '@admin/lib/format';
import type { Paginated } from '@admin/types';

type ContentRow = {
    id: string;
    title: string;
    status: 'draft' | 'approved' | 'published' | 'archived';
    platform?: string | null;
    user?: { id: string; name: string } | null;
    created_at?: string | null;
};

type Props = {
    posts: Paginated<ContentRow>;
    filters: Record<string, string>;
    platforms: string[];
    statuses: Record<string, number>;
};

const nextStates: Record<string, string[]> = {
    draft: ['approved', 'archived'],
    approved: ['published', 'archived'],
    published: ['archived'],
    archived: ['draft'],
};

export default function ContentIndex({ posts, filters, platforms, statuses }: Props) {
    const { t, locale } = useI18n();

    const transition = (id: string, status: string) => {
        router.post(`/admin/content/${id}/transition`, { status }, { preserveScroll: true, preserveState: true });
    };

    return (
        <>
            <Head title={t('content.heading')} />
            <PageHeader title={t('content.heading')} subtitle={t('content.subheading')} />

            <div className="grid gap-3 sm:grid-cols-4">
                {(['draft', 'approved', 'published', 'archived'] as const).map((status) => (
                    <div key={status} className="surface p-4">
                        <p className="text-xs font-bold text-muted-foreground">{t(`content.status.${status}`)}</p>
                        <p className="mt-1 text-2xl font-black tabular-nums">{statuses[status] ?? 0}</p>
                    </div>
                ))}
            </div>

            <FilterBar action="/admin/content" values={filters} searchPlaceholder={t('content.searchPlaceholder')}>
                <FilterSelect
                    name="status"
                    label={t('common.status')}
                    options={(['draft', 'approved', 'published', 'archived'] as const).map((status) => ({
                        value: status,
                        label: t(`content.status.${status}`),
                    }))}
                />
                <FilterSelect
                    name="platform"
                    label={t('content.platform')}
                    options={platforms.map((platform) => ({ value: platform, label: platform }))}
                />
            </FilterBar>

            <Card padded={false}>
                {posts.data.length === 0 ? (
                    <EmptyState />
                ) : (
                    <>
                        <Table
                            head={[
                                t('content.title'),
                                t('common.user'),
                                t('content.platform'),
                                t('common.status'),
                                t('common.created'),
                                t('common.actions'),
                            ]}
                        >
                            {posts.data.map((post) => (
                                <tr key={post.id} className="hover:bg-muted/40">
                                    <Td className="max-w-80 truncate font-bold">{post.title}</Td>
                                    <Td>{post.user?.name ?? '—'}</Td>
                                    <Td>{post.platform ?? '—'}</Td>
                                    <Td>
                                        <Badge tone={statusTone(post.status)}>{t(`content.status.${post.status}`)}</Badge>
                                    </Td>
                                    <Td>{formatDate(post.created_at, locale)}</Td>
                                    <Td>
                                        <span className="flex flex-wrap gap-2">
                                            {(nextStates[post.status] ?? []).map((status) => (
                                                <Button
                                                    key={status}
                                                    variant="ghost"
                                                    className="px-3 py-1.5 text-xs"
                                                    onClick={() => transition(post.id, status)}
                                                >
                                                    {t(`content.action.${status}`)}
                                                </Button>
                                            ))}
                                        </span>
                                    </Td>
                                </tr>
                            ))}
                        </Table>
                        <Pagination meta={posts} />
                    </>
                )}
            </Card>
        </>
    );
}
