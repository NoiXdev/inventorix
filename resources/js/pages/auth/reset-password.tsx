import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PasswordField } from '@/components/form/password-field';
import { FormError } from '@/components/form/form-error';

interface Props { token: string; email: string; }

export default function ResetPassword({ token, email }: Props) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.post('/app/reset-password'); };
    return (
        <div className="flex min-h-screen items-center justify-center bg-background p-4">
            <Head title="Set your password" />
            <Card className="w-full max-w-sm">
                <CardHeader><CardTitle>Set your password</CardTitle></CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-4">
                        <p className="text-sm text-muted-foreground">Setting the password for <strong>{email}</strong>.</p>
                        <FormError message={form.errors.email} />
                        <PasswordField id="password" label="New password" required autoFocus autoComplete="new-password"
                            value={form.data.password} onChange={(v) => form.setData('password', v)} error={form.errors.password} />
                        <PasswordField id="password_confirmation" label="Confirm password" autoComplete="new-password"
                            value={form.data.password_confirmation} onChange={(v) => form.setData('password_confirmation', v)}
                            error={form.errors.password_confirmation} />
                        <Button type="submit" className="w-full" disabled={form.processing}>Set password</Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
