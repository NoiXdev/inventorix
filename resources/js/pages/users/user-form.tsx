import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { SwitchField } from '@/components/form/switch-field';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';

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
        <form onSubmit={submit} className="space-y-6">
            <FormCard>
                <FormCardHeader title="General" />
                <FormCardContent className="md:grid-cols-2">
                    <TextField id="firstname" label="First name" required autoFocus value={form.data.firstname} onChange={(v) => form.setData('firstname', v)} error={form.errors.firstname} />
                    <TextField id="lastname" label="Last name" required value={form.data.lastname} onChange={(v) => form.setData('lastname', v)} error={form.errors.lastname} />
                    <TextField id="email" label="Email" value={form.data.email} onChange={(v) => form.setData('email', v)} error={form.errors.email} />
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Login access" />
                <FormCardContent>
                    <SwitchField id="login_enabled" label="Login enabled" checked={form.data.login_enabled}
                        onChange={(v) => form.setData('login_enabled', v)} error={form.errors.login_enabled}
                        description={isSelf ? 'You cannot disable your own login.' : 'Without login, this user is only an asset owner.'} />
                </FormCardContent>
            </FormCard>

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
