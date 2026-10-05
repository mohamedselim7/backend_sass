import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useI18n } from '@admin/i18n';
import { Badge, Button, Card, Field, PageHeader, inputClass, statusTone } from '@admin/components/ui';
import { getEcho } from '@admin/lib/echo';
import { formatDateTime, initials } from '@admin/lib/format';
import type { SharedProps, SupportMessageRow, SupportThreadRow } from '@admin/types';

type Props = {
    thread: SupportThreadRow;
    messages: SupportMessageRow[];
    agents: { id: string; name: string }[];
};

export default function SupportShow({ thread, messages: initialMessages, agents }: Props) {
    const { t, locale } = useI18n();
    const { auth } = usePage<SharedProps>().props;
    const [messages, setMessages] = useState(initialMessages);
    const scroller = useRef<HTMLDivElement>(null);

    useEffect(() => setMessages(initialMessages), [initialMessages]);

    // Live thread updates over the same Pusher setup the user app uses.
    useEffect(() => {
        const echo = getEcho();
        if (!echo) return;

        const channel = echo.private(`support.${thread.id}`);
        // The event payload is the message itself (see SupportMessageSent::broadcastWith).
        channel.listen('.support.message.sent', (event: SupportMessageRow | { message: SupportMessageRow }) => {
            const incoming = 'message' in event && typeof event.message === 'object' ? event.message : (event as SupportMessageRow);
            setMessages((current) => (current.some((message) => message.id === incoming.id) ? current : [...current, incoming]));
            // The agent is looking at this conversation: clear its unread counter server-side.
            if (incoming.author_type === 'user') {
                router.reload({ only: ['thread'] });
            }
        });
        // After a dropped connection, resync once from the server; ids de-duplicate the list.
        const connector = echo.connector as unknown as { pusher?: { connection: { bind: (e: string, cb: (s: { previous: string; current: string }) => void) => void; unbind: (e: string, cb: unknown) => void } } };
        const onState = (state: { previous: string; current: string }) => {
            if (state.current === 'connected' && state.previous !== 'initialized' && state.previous !== 'connecting') {
                router.reload({ only: ['messages', 'thread'] });
            }
        };
        connector.pusher?.connection.bind('state_change', onState);

        return () => {
            connector.pusher?.connection.unbind('state_change', onState);
            echo.leave(`private-support.${thread.id}`);
        };
    }, [thread.id]);

    useEffect(() => {
        scroller.current?.scrollTo({ top: scroller.current.scrollHeight, behavior: 'smooth' });
    }, [messages.length]);

    const replyForm = useForm<{ body: string; status: string; image: File | null }>({ body: '', status: '', image: null });

    return (
        <>
            <Head title={thread.subject} />

            <PageHeader
                title={thread.subject}
                subtitle={`${thread.user?.name ?? ''} · ${thread.user?.email ?? ''}`}
                action={
                    <Link
                        href="/admin/support"
                        className="inline-flex items-center gap-2 rounded-xl border border-border px-4 py-2 text-sm font-bold transition hover:bg-muted"
                    >
                        <ArrowLeft className="size-4 rtl:rotate-180" />
                        {t('common.back')}
                    </Link>
                }
            />

            <div className="grid gap-4 lg:grid-cols-[1fr_20rem]">
                <Card padded={false} className="flex flex-col">
                    <div ref={scroller} className="max-h-[32rem] flex-1 space-y-4 overflow-y-auto p-5">
                        {messages.map((message) => {
                            const isAgent = message.author_type !== 'user';

                            return (
                                <div key={message.id} className={`flex gap-3 ${isAgent ? 'flex-row-reverse' : ''}`}>
                                    <div className="grid size-9 shrink-0 place-items-center rounded-full bg-muted text-xs font-black">
                                        {initials(message.sender?.name ?? '?')}
                                    </div>
                                    <div
                                        className={`max-w-[75%] rounded-2xl px-4 py-3 text-sm leading-relaxed ${
                                            isAgent
                                                ? 'bg-primary text-primary-foreground'
                                                : 'border border-border bg-muted/50 text-foreground'
                                        }`}
                                    >
                                        <p className="mb-1 text-xs font-bold opacity-70">
                                            {message.sender?.name ?? t('support.system')} ·{' '}
                                            {formatDateTime(message.created_at, locale)}
                                        </p>
                                        {message.body && <p className="whitespace-pre-wrap">{message.body}</p>}
                                        {(message.attachments ?? []).map((file) => (
                                            <a key={file.url} href={file.url} target="_blank" rel="noreferrer" className="mt-2 block">
                                                <img src={file.url} alt="" loading="lazy" className="max-h-64 rounded-xl object-contain" />
                                            </a>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    <form
                        className="space-y-3 border-t border-border p-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            replyForm.post(`/admin/support/${thread.id}/reply`, {
                                preserveScroll: true,
                                forceFormData: true,
                                onSuccess: () => replyForm.reset(),
                            });
                        }}
                    >
                        <Field label={t('support.reply')} error={replyForm.errors.body}>
                            <textarea
                                required={!replyForm.data.image}
                                rows={4}
                                className={`${inputClass} resize-y`}
                                placeholder={t('support.replyPlaceholder')}
                                value={replyForm.data.body}
                                onChange={(event) => replyForm.setData('body', event.target.value)}
                            />
                        </Field>
                        <Field label={t('support.attachImage', 'إرفاق صورة (اختياري)')} error={(replyForm.errors as Record<string, string>).image}>
                            <input
                                type="file"
                                accept="image/png,image/jpeg,image/webp,image/gif"
                                className={inputClass}
                                onChange={(event) => replyForm.setData('image', event.target.files?.[0] ?? null)}
                            />
                        </Field>
                        <div className="flex flex-wrap items-center gap-3">
                            <select
                                className={`${inputClass} w-auto`}
                                value={replyForm.data.status}
                                onChange={(event) => replyForm.setData('status', event.target.value)}
                            >
                                <option value="">{t('support.keepStatus')}</option>
                                {['open', 'pending', 'resolved', 'closed'].map((status) => (
                                    <option key={status} value={status}>
                                        {t(`support.status.${status}`)}
                                    </option>
                                ))}
                            </select>
                            <Button type="submit" disabled={replyForm.processing}>
                                {replyForm.processing ? t('common.sending') : t('support.send')}
                            </Button>
                        </div>
                    </form>
                </Card>

                <div className="space-y-4">
                    <Card title={t('support.details')}>
                        <dl className="space-y-3 text-sm">
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-muted-foreground">{t('common.status')}</dt>
                                <dd>
                                    <Badge tone={statusTone(thread.status)}>{t(`support.status.${thread.status}`)}</Badge>
                                </dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-muted-foreground">{t('support.priority')}</dt>
                                <dd>
                                    <Badge tone={statusTone(thread.priority)}>{t(`support.priorities.${thread.priority}`)}</Badge>
                                </dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-muted-foreground">{t('support.category')}</dt>
                                <dd className="font-bold">{thread.category ?? '—'}</dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-muted-foreground">{t('support.lastMessage')}</dt>
                                <dd className="font-bold">{formatDateTime(thread.last_message_at, locale)}</dd>
                            </div>
                        </dl>

                        {thread.user && (
                            <Link
                                href={`/admin/users/${thread.user.id}`}
                                className="mt-4 block text-sm font-bold text-primary hover:underline"
                            >
                                {t('support.viewUser')}
                            </Link>
                        )}
                    </Card>

                    <Card title={t('support.assignment')}>
                        <select
                            className={inputClass}
                            value={thread.agent?.id ?? ''}
                            onChange={(event) =>
                                router.post(
                                    `/admin/support/${thread.id}/assign`,
                                    { agent_id: event.target.value || null },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <option value="">{t('support.unassigned')}</option>
                            {agents.map((agent) => (
                                <option key={agent.id} value={agent.id}>
                                    {agent.name}
                                    {agent.id === auth.user?.id ? ` (${t('support.you')})` : ''}
                                </option>
                            ))}
                        </select>
                    </Card>

                    <Card title={t('support.changeStatus')}>
                        <div className="grid grid-cols-2 gap-2">
                            {['open', 'pending', 'resolved', 'closed'].map((status) => (
                                <Button
                                    key={status}
                                    variant={thread.status === status ? 'primary' : 'ghost'}
                                    className="px-3 py-2 text-xs"
                                    onClick={() =>
                                        router.post(`/admin/support/${thread.id}/status`, { status }, { preserveScroll: true })
                                    }
                                >
                                    {t(`support.status.${status}`)}
                                </Button>
                            ))}
                        </div>
                    </Card>
                </div>
            </div>
        </>
    );
}
