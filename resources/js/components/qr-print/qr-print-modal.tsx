import { useEffect, useRef, useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { PrintController } from '@/plugins/qr-print/controller';
import { composeLabel } from '@/plugins/qr-print/layout';
import { isWebUsbAvailable } from '@/plugins/qr-print/webusb-transport';
import { DK_ROLLS, getRollById } from '@/plugins/qr-print/dk-rolls';
import type { LabelItem, LayoutKind } from '@/plugins/qr-print/types';

const STORAGE_ROLL = 'qrPrint.lastRollId';
const STORAGE_LAYOUT = 'qrPrint.lastLayout';

interface Props { open: boolean; items: LabelItem[]; onClose: () => void }

export function QrPrintModal({ open, items, onClose }: Props) {
    const controllerRef = useRef<PrintController | undefined>(undefined);
    if (!controllerRef.current) controllerRef.current = new PrintController();
    const controller = controllerRef.current;

    const webUsbSupported = isWebUsbAvailable();
    const canUseAsset = items.length > 0 && items.every((i) => !!i.metadata);

    const [rollId, setRollId] = useState<string>(() => {
        const stored = localStorage.getItem(STORAGE_ROLL);
        // Fall back to the first enabled roll if nothing stored or the stored roll was disabled.
        return stored && DK_ROLLS.some((r) => r.id === stored) ? stored : DK_ROLLS[0].id;
    });
    const [layout, setLayout] = useState<LayoutKind>(() => {
        const stored = (localStorage.getItem(STORAGE_LAYOUT) as LayoutKind | null) ?? 'qr-uuid';
        return stored === 'qr-asset' && !canUseAsset ? 'qr-uuid' : stored;
    });
    const [paired, setPaired] = useState(false);
    const [pairing, setPairing] = useState(false);
    const [printing, setPrinting] = useState(false);
    const [completed, setCompleted] = useState(0);
    const [error, setError] = useState<string | null>(null);
    const [status, setStatus] = useState<string | null>(null);
    const canvasRef = useRef<HTMLCanvasElement>(null);

    // reconnect on open
    useEffect(() => {
        if (!open || !webUsbSupported) return;
        controller.tryReconnect().then(setPaired).catch(() => {});
    }, [open, webUsbSupported, controller]);

    // live preview
    useEffect(() => {
        if (!open || !items[0]) return;
        let cancelled = false;
        (async () => {
            const image = await composeLabel(items[0], getRollById(rollId), layout);
            if (cancelled || !canvasRef.current) return;
            canvasRef.current.width = image.width;
            canvasRef.current.height = image.height;
            canvasRef.current.getContext('2d')?.putImageData(image, 0, 0);
        })();
        return () => { cancelled = true; };
    }, [open, items, rollId, layout]);

    const pair = async () => {
        setPairing(true); setError(null);
        try { await controller.pair(); setPaired(true); }
        catch (e) { const m = (e as Error).message ?? ''; if (!m.includes('No device selected')) setError(m); }
        finally { setPairing(false); }
    };

    const doPrint = async () => {
        if (!paired) { setError('Drucker nicht verbunden.'); return; }
        setPrinting(true); setError(null);
        localStorage.setItem(STORAGE_ROLL, rollId);
        localStorage.setItem(STORAGE_LAYOUT, layout);
        try {
            await controller.print({ items, roll: getRollById(rollId), layout }, (p) => setCompleted(p.completedLabels));
            setStatus(`${items.length} von ${items.length} Etiketten gedruckt.`);
        } catch (e) { setError((e as Error).message); }
        finally { setPrinting(false); }
    };

    const cancel = async () => { await controller.cancel(); setStatus(`Gestoppt nach ${completed} von ${items.length} Etiketten.`); setPrinting(false); };

    const layouts: { value: LayoutKind; label: string; disabled: boolean }[] = [
        { value: 'qr-only', label: 'Nur QR-Code', disabled: false },
        { value: 'qr-uuid', label: 'QR + UUID', disabled: false },
        { value: 'qr-asset', label: 'QR + Asset-Info', disabled: !canUseAsset },
    ];

    return (
        <Dialog open={open} onOpenChange={(o) => { if (!o) onClose(); }}>
            <DialogContent>
                <DialogHeader><DialogTitle>QR drucken ({items.length})</DialogTitle></DialogHeader>

                {!webUsbSupported ? (
                    <p className="text-sm text-muted-foreground">
                        WebUSB wird von diesem Browser nicht unterstützt. Bitte Chrome oder Edge über HTTPS verwenden.
                    </p>
                ) : (
                    <div className="space-y-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="grid gap-1 text-sm">Etikettenrolle
                                <select className="rounded-md border bg-background px-2 py-1" value={rollId} onChange={(e) => setRollId(e.target.value)}>
                                    {DK_ROLLS.map((r) => <option key={r.id} value={r.id}>{r.label}</option>)}
                                </select>
                            </label>
                            <fieldset className="grid gap-1 text-sm">
                                <legend>Layout</legend>
                                {layouts.map((l) => (
                                    <label key={l.value} className="flex items-center gap-2">
                                        <input type="radio" name="qr-layout" value={l.value} checked={layout === l.value} disabled={l.disabled}
                                            onChange={() => setLayout(l.value)} aria-label={l.label} />
                                        {l.label}
                                    </label>
                                ))}
                            </fieldset>
                        </div>

                        <canvas ref={canvasRef} className="max-h-[320px] max-w-full border object-contain" />

                        {error && <p className="text-sm text-red-600 dark:text-red-400">{error}</p>}
                        {status && <p className="text-sm text-muted-foreground">{status}</p>}
                        {printing && <p className="text-sm text-muted-foreground">{completed} / {items.length}</p>}

                        <div className="flex gap-2">
                            {!paired
                                ? <Button type="button" onClick={pair} disabled={pairing}>{pairing ? 'Verbinde…' : 'Drucker verbinden'}</Button>
                                : <Button type="button" onClick={doPrint} disabled={printing}>Drucken</Button>}
                            {printing && <Button type="button" variant="outline" onClick={cancel}>Abbrechen</Button>}
                            <Button type="button" variant="ghost" onClick={onClose}>Schließen</Button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
