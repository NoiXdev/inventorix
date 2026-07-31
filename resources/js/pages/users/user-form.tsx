import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { PersonPicker } from '@/components/form/person-picker';
import { SwitchField } from '@/components/form/switch-field';

type Option = { value: string; label: string };
interface Initial { id: string; email: string | null; login_enabled: boolean; person_id: string | null }
interface Props { initial?: Initial; personOptions: Option[]; personCreateUrl: string; submitUrl: string; method: 'post' | 'put'; isSelf?: boolean }

export function UserForm({ initial, personOptions, personCreateUrl, submitUrl, method, isSelf }: Props) {
    const form = useForm({
        person_id: initial?.person_id ?? '',
        email: initial?.email ?? '',
        login_enabled: initial?.login_enabled ?? false,
    });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };

    return (
        <form onSubmit={submit} className="max-w-lg space-y-6">
            <PersonPicker id="person_id" label="Person" options={personOptions} createUrl={personCreateUrl}
                value={form.data.person_id} onChange={(v) => form.setData('person_id', v)} error={form.errors.person_id} />
            <TextField id="email" label="Login email" value={form.data.email} onChange={(v) => form.setData('email', v)} error={form.errors.email} />
            <SwitchField id="login_enabled" label="Login enabled" checked={form.data.login_enabled}
                onChange={(v) => form.setData('login_enabled', v)} error={form.errors.login_enabled}
                description={isSelf ? 'You cannot disable your own login.' : undefined} />
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
