import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import QrScanner from 'qr-scanner';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { classifyCameraError } from '@/plugins/scanner-camera-error';

interface Props { open: boolean; onClose: () => void }

const MESSAGES: Record<string, string> = {
    permission: 'Kamerazugriff wurde verweigert.',
    not_found: 'Keine Kamera gefunden.',
    generic: 'Kamera konnte nicht gestartet werden.',
};

export function ScanDialog({ open, onClose }: Props) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const scannerRef = useRef<QrScanner | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [manual, setManual] = useState('');

    const go = (code: string) => {
        if (!code) return;
        onClose();
        router.get('/app/scan/resolve', { code });
    };

    useEffect(() => {
        if (!open || !videoRef.current) return;
        // Reset any stale state from a previous open (the dialog stays mounted).
        setError(null);
        setManual('');
        const scanner = new QrScanner(videoRef.current, (result) => go(typeof result === 'string' ? result : result.data), {
            highlightScanRegion: true,
        });
        scannerRef.current = scanner;
        scanner.start().then(() => setError(null)).catch((e) => setError(MESSAGES[classifyCameraError(e)]));
        return () => { scanner.stop(); scanner.destroy(); scannerRef.current = null; };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    return (
        <Dialog open={open} onOpenChange={(o) => { if (!o) onClose(); }}>
            <DialogContent>
                <DialogHeader><DialogTitle>QR scannen</DialogTitle></DialogHeader>
                <video ref={videoRef} muted playsInline className="aspect-square w-full rounded-md bg-black object-cover" />
                {error && <p className="text-sm text-red-600 dark:text-red-400">{error}</p>}
                <form onSubmit={(e) => { e.preventDefault(); go(manual.trim()); }} className="flex gap-2">
                    <Input value={manual} onChange={(e) => setManual(e.target.value)} placeholder="UUID manuell eingeben" />
                    <Button type="submit" variant="secondary">Öffnen</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
