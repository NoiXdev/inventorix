import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { TextareaField } from '@/components/form/textarea-field';
import { FileField } from '@/components/form/file-field';

export interface ManufacturerInitial {
    id: string;
    name: string;
    url: string | null;
    support_url: string | null;
    warranty_lookup_url: string | null;
    support_phone: string | null;
    support_email: string | null;
    notes: string | null;
    image_url: string | null;
}

interface Props {
    initial?: ManufacturerInitial;
    submitUrl: string;
    method: 'post' | 'put';
}

export function ManufacturerForm({ initial, submitUrl, method }: Props) {
    const form = useForm({
        name: initial?.name ?? '',
        url: initial?.url ?? '',
        support_url: initial?.support_url ?? '',
        warranty_lookup_url: initial?.warranty_lookup_url ?? '',
        support_phone: initial?.support_phone ?? '',
        support_email: initial?.support_email ?? '',
        notes: initial?.notes ?? '',
        image: null as File | null,
        remove_image: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        // Always POST with forceFormData: Inertia cannot send a file over PUT,
        // so an update is method-spoofed. remove_image goes out as '1'/'0'
        // rather than a boolean so the server's `boolean` rule matches whatever
        // Inertia's FormData serialisation does with it.
        form.transform((data) => ({
            ...data,
            remove_image: data.remove_image ? '1' : '0',
            ...(method === 'put' ? { _method: 'put' } : {}),
        }));

        form.post(submitUrl, { forceFormData: true });
    };

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-8">
            <TextField id="name" label="Name" required autoFocus
                value={form.data.name} onChange={(v) => form.setData('name', v)}
                error={form.errors.name} />

            <FileField id="image" label="Logo"
                value={form.data.image}
                onChange={(file) => form.setData((d) => ({ ...d, image: file, remove_image: false }))}
                accept="image/png,image/jpeg,image/webp"
                hint="PNG, JPEG or WebP, up to 2 MB."
                currentUrl={initial?.image_url ?? null}
                currentRemoved={form.data.remove_image}
                onRemoveCurrent={() => form.setData('remove_image', true)}
                error={form.errors.image} />

            <fieldset className="space-y-4">
                <legend className="mb-2 text-sm font-medium">Links</legend>
                <TextField id="url" label="Website"
                    value={form.data.url} onChange={(v) => form.setData('url', v)}
                    error={form.errors.url} />
                <TextField id="support_url" label="Support page"
                    value={form.data.support_url} onChange={(v) => form.setData('support_url', v)}
                    error={form.errors.support_url} />
                <TextField id="warranty_lookup_url" label="Warranty lookup"
                    value={form.data.warranty_lookup_url} onChange={(v) => form.setData('warranty_lookup_url', v)}
                    error={form.errors.warranty_lookup_url} />
            </fieldset>

            <fieldset className="space-y-4">
                <legend className="mb-2 text-sm font-medium">Support contact</legend>
                <TextField id="support_phone" label="Phone"
                    value={form.data.support_phone} onChange={(v) => form.setData('support_phone', v)}
                    error={form.errors.support_phone} />
                <TextField id="support_email" label="Email"
                    value={form.data.support_email} onChange={(v) => form.setData('support_email', v)}
                    error={form.errors.support_email} />
            </fieldset>

            <TextareaField id="notes" label="Notes" rows={5}
                value={form.data.notes} onChange={(v) => form.setData('notes', v)}
                error={form.errors.notes} />

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
