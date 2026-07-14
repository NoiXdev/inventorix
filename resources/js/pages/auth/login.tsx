import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TextField } from '@/components/form/text-field';

export default function Login({ entraEnabled }: { entraEnabled: boolean }) {
    const form = useForm({ email: '', password: '', remember: false });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/app/login');
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-background p-4">
            <Head title="Sign in" />
            <Card className="w-full max-w-sm">
                <CardHeader><CardTitle>Sign in to Inventorix</CardTitle></CardHeader>
                <CardContent className="space-y-6">
                    <form onSubmit={submit} className="space-y-4">
                        <TextField id="email" label="Email" value={form.data.email}
                            onChange={(v) => form.setData('email', v)} error={form.errors.email} autoFocus required />
                        <div className="space-y-2">
                            <label htmlFor="password" className="text-sm font-medium">Password</label>
                            <input id="password" type="password" className="w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                                value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                        </div>
                        <Button type="submit" className="w-full" disabled={form.processing}>Sign in</Button>
                    </form>
                    {entraEnabled && (
                        <a href="/auth/microsoft/redirect"
                            className="flex w-full items-center justify-center rounded-md border py-2 text-sm hover:bg-accent">
                            Sign in with Microsoft
                        </a>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
