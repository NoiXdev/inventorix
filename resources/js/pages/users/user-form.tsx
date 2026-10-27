import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { SwitchField } from '@/components/form/switch-field';

interface Initial { id: string; firstname: string; lastname: string; email: string | null; login_enabled: boolean }
interface Props { initial?: Initial; submitUrl: string; method: 'post' | 'put'; isSelf?: boolean }

export function UserForm({ initial, submitUrl, method, isSelf }: Props) {
    const form = useForm({
        firstname: initial?.firstname ?? '',
        lastname: initial?.lastname ?? '',
        email: initial?.email ?? '',
        login_enabled: initial?.login_enabled ?? false,
    });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };

    return (
        <form onSubmit={submit} className="max-w-lg space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField id="firstname" label="First name" required autoFocus value={form.data.firstname} onChange={(v) => form.setData('firstname', v)} error={form.errors.firstname} />
                <TextField id="lastname" label="Last name" required value={form.data.lastname} onChange={(v) => form.setData('lastname', v)} error={form.errors.lastname} />
            </div>
            <TextField id="email" label="Email" value={form.data.email} onChange={(v) => form.setData('email', v)} error={form.errors.email} />

            <div className="space-y-4 rounded-lg border p-4">
                <h2 className="text-sm font-medium">Login access</h2>
                <SwitchField id="login_enabled" label="Login enabled" checked={form.data.login_enabled}
                    onChange={(v) => form.setData('login_enabled', v)} error={form.errors.login_enabled}
                    description={isSelf ? 'You cannot disable your own login.' : 'Without login, this user is only an asset owner.'} />
            </div>

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
