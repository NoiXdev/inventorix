import { router, useForm } from '@inertiajs/react';
import { ExternalLink, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent } from '@/components/ui/card';
import { TextField } from '@/components/form/text-field';
import { SelectField } from '@/components/form/select-field';
import { FormError } from '@/components/form/form-error';

export interface AttachmentItem {
    id: string; type: string; category_label: string | null; title: string | null;
    note: string | null;
    original_name: string; size: number; size_label: string;
    uploaded_by_name: string | null; created_at: string | null; url: string;
}
interface Props { assetId: string; attachments: AttachmentItem[]; categoryOptions: { value: string; label: string }[]; }

export function AssetAttachments({ assetId, attachments, categoryOptions }: Props) {
    const form = useForm<{ files: File[]; title: string; category: string; note: string }>({
        files: [], title: '', category: '', note: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/app/assets/${assetId}/attachments`, {
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
    };

    const remove = (id: string) => {
        if (confirm('Delete this attachment?')) router.delete(`/app/assets/${assetId}/attachments/${id}`);
    };

    const images = attachments.filter((a) => a.type === 'image');
    const others = attachments.filter((a) => a.type !== 'image');

    return (
        <div className="space-y-6">
            <Card>
                <CardContent className="py-6">
                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="files">Files</Label>
                            <input id="files" type="file" multiple
                                onChange={(e) => form.setData('files', Array.from(e.target.files ?? []))}
                                className="block w-full text-sm file:mr-3 file:rounded-md file:border file:bg-secondary file:px-3 file:py-1.5" />
                            {Object.entries(form.errors)
                                .filter(([k]) => k === 'files' || k.startsWith('files.'))
                                .map(([k, message]) => (
                                    <FormError key={k} message={message} />
                                ))}
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField id="att_title" label="Title" value={form.data.title}
                                onChange={(v) => form.setData('title', v)} error={form.errors.title} />
                            <SelectField id="att_category" label="Category" nullable options={categoryOptions}
                                value={form.data.category} onChange={(v) => form.setData('category', v)} error={form.errors.category} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="att_note">Note</Label>
                            <Textarea id="att_note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                            <FormError message={form.errors.note} />
                        </div>
                        <Button type="submit" disabled={form.processing || form.data.files.length === 0}>Upload</Button>
                    </form>
                </CardContent>
            </Card>

            {attachments.length === 0 ? (
                <p className="text-sm text-muted-foreground">No attachments yet.</p>
            ) : (
                <div className="space-y-6">
                    {images.length > 0 && (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                            {images.map((a) => (
                                <div key={a.id} className="group relative">
                                    <a href={a.url} target="_blank" rel="noreferrer">
                                        <img src={a.url} alt={a.title ?? a.original_name} className="h-28 w-full rounded object-cover" />
                                    </a>
                                    <div className="mt-1 truncate text-xs text-muted-foreground">{a.title ?? a.original_name}</div>
                                    {a.note && <div className="truncate text-xs text-muted-foreground italic">{a.note}</div>}
                                    <Button variant="ghost" size="icon" className="absolute right-1 top-1 bg-background/80"
                                        onClick={() => remove(a.id)} aria-label={`Delete ${a.original_name}`}>
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                    {others.length > 0 && (
                        <div className="rounded-md border divide-y">
                            {others.map((a) => (
                                <div key={a.id} className="flex items-center gap-3 px-3 py-2 text-sm">
                                    <Badge variant="secondary">{a.type}</Badge>
                                    {a.category_label && <Badge variant="outline">{a.category_label}</Badge>}
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate font-medium">{a.title ?? a.original_name}</div>
                                        <div className="truncate text-xs text-muted-foreground">
                                            <span>{a.original_name}</span> · {a.size_label} · {a.uploaded_by_name ?? '—'} · {a.created_at ?? ''}
                                        </div>
                                        {a.note && <div className="truncate text-xs text-muted-foreground">{a.note}</div>}
                                    </div>
                                    <a href={a.url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-sm hover:underline">
                                        <ExternalLink className="h-4 w-4" /> Open
                                    </a>
                                    <Button variant="ghost" size="icon" onClick={() => remove(a.id)} aria-label={`Delete ${a.original_name}`}>
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
