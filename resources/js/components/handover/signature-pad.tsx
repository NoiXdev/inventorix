import { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';

interface Props { value: string; onChange: (base64: string) => void; width?: number; height?: number; }

export function SignaturePad({ value, onChange, width = 600, height = 200 }: Props) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const drawing = useRef(false);
    const last = useRef<{ x: number; y: number } | null>(null);

    const ctx = () => canvasRef.current?.getContext('2d') ?? null;

    const fillWhite = () => {
        const c = ctx();
        if (!c || !canvasRef.current) return;
        c.fillStyle = '#ffffff';
        c.fillRect(0, 0, canvasRef.current.width, canvasRef.current.height);
    };

    useEffect(() => {
        const c = ctx();
        if (c) { c.lineWidth = 2; c.lineCap = 'round'; c.strokeStyle = '#111111'; }
        fillWhite();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const pos = (e: React.PointerEvent) => {
        const r = canvasRef.current!.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    };

    const start = (e: React.PointerEvent) => { drawing.current = true; last.current = pos(e); };
    const move = (e: React.PointerEvent) => {
        if (!drawing.current) return;
        const c = ctx(); if (!c) return;
        const p = pos(e);
        c.beginPath(); c.moveTo(last.current!.x, last.current!.y); c.lineTo(p.x, p.y); c.stroke(); c.closePath();
        last.current = p;
    };
    const end = () => {
        if (!drawing.current) return;
        drawing.current = false;
        const url = canvasRef.current?.toDataURL('image/png') ?? '';
        onChange(url.replace(/^data:image\/png;base64,/, ''));
    };

    const clear = () => { fillWhite(); onChange(''); };

    return (
        <div className="space-y-2">
            <canvas
                ref={canvasRef} data-testid="signature-canvas" width={width} height={height}
                className="touch-none rounded-md border bg-white"
                onPointerDown={start} onPointerMove={move} onPointerUp={end} onPointerLeave={end}
            />
            <div>
                <Button type="button" variant="outline" size="sm" onClick={clear}>Clear</Button>
            </div>
        </div>
    );
}
