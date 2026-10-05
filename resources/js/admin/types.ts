import type { Locale } from '@admin/i18n';

export type AdminUser = {
    id: string;
    name: string;
    email: string;
    avatar_url?: string | null;
    roles: string[];
};

export type SharedProps = {
    auth: {
        user: AdminUser | null;
        can: {
            manageUsers: boolean;
            managePlans: boolean;
            manageSettings: boolean;
            handleSupport: boolean;
        };
    };
    locale: Locale;
    flash: { success?: string | null; error?: string | null };
    badges: { support: number };
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

export type UserRow = {
    id: string;
    name: string;
    email: string;
    status: string;
    avatar_url?: string | null;
    roles: string[];
    credits: number;
    plan?: string | null;
    created_at?: string | null;
};

export type SupportThreadRow = {
    id: string;
    subject: string;
    status: 'open' | 'pending' | 'resolved' | 'closed';
    priority: 'low' | 'normal' | 'high' | 'urgent';
    unread: number;
    user?: { id: string; name: string; email: string; avatar_url?: string | null } | null;
    agent?: { id: string; name: string } | null;
    last_message_at?: string | null;
    category?: string | null;
    created_at?: string | null;
};

export type SupportMessageRow = {
    id: string;
    author_type: 'user' | 'agent' | 'system';
    body: string;
    attachments?: { url: string; mime?: string; size?: number }[] | null;
    sender?: { id: string; name: string; avatar_url?: string | null } | null;
    created_at?: string | null;
};

export type MoneyRow = {
    id: string;
    reference?: string | null;
    user?: { id: string; name: string; email: string } | null;
    plan?: { id: string; name: string } | null;
    amount: number;
    currency: string;
    status: string;
    gateway?: string | null;
    paid_at?: string | null;
    created_at?: string | null;
};

export type Series = { month: string; total: number }[];

export type AdminBoot = {
    pusherKey: string | null;
    pusherCluster: string | null;
};

declare global {
    interface Window {
        __ADMIN_BOOT__?: AdminBoot;
    }
}
