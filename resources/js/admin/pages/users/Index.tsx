import { Head, Link } from '@inertiajs/react';
import { useI18n } from '@admin/i18n';
import { Badge, Card, EmptyState, PageHeader, Pagination, Table, Td, statusTone } from '@admin/components/ui';
import { FilterBar, FilterSelect } from '@admin/components/Filters';
import { formatDate, formatNumber } from '@admin/lib/format';
import type { Paginated, UserRow } from '@admin/types';

type Props = {
    users: Paginated<UserRow>;
    filters: Record<string, string>;
    roles: string[];
};

export default function UsersIndex({ users, filters, roles }: Props) {
    const { t, locale } = useI18n();

    return (
        <>
            <Head title={t('users.heading')} />
            <PageHeader title={t('users.heading')} subtitle={t('users.subheading')} />

            <FilterBar action="/admin/users" values={filters} searchPlaceholder={t('users.searchPlaceholder')}>
                <FilterSelect
                    name="status"
                    label={t('common.status')}
                    options={[
                        { value: 'active', label: t('users.status.active') },
                        { value: 'suspended', label: t('users.status.suspended') },
                    ]}
                />
                <FilterSelect
                    name="role"
                    label={t('common.roles')}
                    options={roles.map((role) => ({ value: role, label: role }))}
                />
            </FilterBar>

            <Card padded={false}>
                {users.data.length === 0 ? (
                    <EmptyState />
                ) : (
                    <>
                        <Table
                            head={[
                                t('common.name'),
                                t('common.email'),
                                t('common.roles'),
                                t('common.plan'),
                                t('common.credits'),
                                t('common.status'),
                                t('common.created'),
                            ]}
                        >
                            {users.data.map((user) => (
                                <tr key={user.id} className="hover:bg-muted/40">
                                    <Td>
                                        <Link href={`/admin/users/${user.id}`} className="font-bold text-primary hover:underline">
                                            {user.name}
                                        </Link>
                                    </Td>
                                    <Td>{user.email}</Td>
                                    <Td>
                                        <span className="flex flex-wrap gap-1">
                                            {user.roles.length === 0
                                                ? '—'
                                                : user.roles.map((role) => (
                                                      <Badge key={role} tone="primary">
                                                          {role}
                                                      </Badge>
                                                  ))}
                                        </span>
                                    </Td>
                                    <Td>{user.plan}</Td>
                                    <Td className="tabular-nums">{formatNumber(user.credits, locale)}</Td>
                                    <Td>
                                        <Badge tone={statusTone(user.status)}>{t(`users.status.${user.status}`, user.status)}</Badge>
                                    </Td>
                                    <Td>{formatDate(user.created_at, locale)}</Td>
                                </tr>
                            ))}
                        </Table>
                        <Pagination meta={users} />
                    </>
                )}
            </Card>
        </>
    );
}
