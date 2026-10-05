import { Head, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { useI18n } from '@admin/i18n';
import { Button, Field, inputClass } from '@admin/components/ui';

export default function Login() {
    const { t, dir, locale } = useI18n();
    const form = useForm({ email: '', password: '', remember: false });

    useEffect(() => {
        document.documentElement.dir = dir;
        document.documentElement.lang = locale;
    }, [dir, locale]);

    return (
        <div dir={dir} className="auth-canvas grid min-h-screen place-items-center p-6">
            <Head title={t('login.heading')} />

            <div className="w-full max-w-md">
                <div className="mb-6 text-center text-primary-foreground">
                    <img src="/images/iden-logo.png" alt="iden" className="mx-auto size-16 rounded-2xl bg-white/10 p-2" />
                    <h1 className="mt-4 text-2xl font-extrabold">{t('login.heading')}</h1>
                    <p className="mt-1 text-sm opacity-80">{t('login.subheading')}</p>
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/admin/login', { onFinish: () => form.reset('password') });
                    }}
                    className="surface space-y-4 p-6"
                >
                    <Field label={t('common.email')} error={form.errors.email}>
                        <input
                            type="email"
                            autoComplete="email"
                            required
                            className={inputClass}
                            value={form.data.email}
                            onChange={(event) => form.setData('email', event.target.value)}
                        />
                    </Field>

                    <Field label={t('login.password')} error={form.errors.password}>
                        <input
                            type="password"
                            autoComplete="current-password"
                            required
                            className={inputClass}
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                        />
                    </Field>

                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            className="size-4 rounded border-input"
                            checked={form.data.remember}
                            onChange={(event) => form.setData('remember', event.target.checked)}
                        />
                        {t('login.remember')}
                    </label>

                    <Button type="submit" className="w-full" disabled={form.processing}>
                        {form.processing ? t('login.submitting') : t('login.submit')}
                    </Button>
                </form>
            </div>
        </div>
    );
}
