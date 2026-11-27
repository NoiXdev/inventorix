import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { FormError } from '@/components/form/form-error';
import { SignaturePad } from '@/components/handover/signature-pad';

interface Props {
    acceptance: {
        item_name: string;
        user_name: string | null;
        eula_text: string | null;
        status: 'pending' | 'accepted' | 'declined';
        answered_at: string | null;
    };
    /** Signed URL the answer is posted to. */
    submitUrl: string;
}

/**
 * Reached through the signed link in the check-out mail, without logging in:
 * the user accepts or declines the item, optionally with a signature.
 */
export default function AcceptanceRespond({ acceptance, submitUrl }: Props) {
    const form = useForm({ decision: '', signature_png: '', note: '' });
    const answer = (decision: 'accept' | 'decline') => {
        form.transform((d) => ({ ...d, decision, signature_png: d.signature_png.replace(/^data:image\/png;base64,/, '') }));
        form.post(submitUrl, { preserveScroll: true });
    };

    return (
        <div className="flex min-h-screen items-start justify-center bg-background p-4 sm:items-center">
            <Head title="Confirm receipt" />
            <Card className="w-full max-w-2xl">
                <CardHeader>
                    <CardTitle>{acceptance.item_name}</CardTitle>
                    <CardDescription>
                        {acceptance.user_name ? `For ${acceptance.user_name}. ` : ''}Please confirm that you received this item, or decline it.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    {acceptance.status !== 'pending' ? (
                        <div className="flex items-center gap-3 rounded-md border p-4" role="status">
                            {acceptance.status === 'accepted'
                                ? <CheckCircle2 className="h-6 w-6 text-green-600" />
                                : <XCircle className="h-6 w-6 text-destructive" />}
                            <p className="text-sm">
                                {acceptance.status === 'accepted' ? 'Accepted' : 'Declined'}
                                {acceptance.answered_at ? ` on ${acceptance.answered_at}` : ''}. Thank you, nothing else to do.
                            </p>
                        </div>
                    ) : (
                        <>
                            {acceptance.eula_text && (
                                <div className="space-y-2">
                                    <h2 className="text-sm font-medium">Terms of use</h2>
                                    <div className="max-h-72 overflow-y-auto whitespace-pre-line rounded-md border bg-muted/40 p-3 text-sm">{acceptance.eula_text}</div>
                                </div>
                            )}
                            <div className="space-y-2">
                                <Label>Signature (optional)</Label>
                                <SignaturePad value={form.data.signature_png} onChange={(v) => form.setData('signature_png', v)} />
                                <FormError message={form.errors.signature_png} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="note">Note (optional)</Label>
                                <Textarea id="note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                                <FormError message={form.errors.note} />
                            </div>
                            <FormError message={form.errors.decision} />
                            <div className="flex flex-wrap gap-2">
                                <Button onClick={() => answer('accept')} disabled={form.processing}>
                                    {acceptance.eula_text ? 'I accept the item and the terms' : 'I accept the item'}
                                </Button>
                                <Button variant="outline" onClick={() => answer('decline')} disabled={form.processing}>Decline</Button>
                            </div>
                        </>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
