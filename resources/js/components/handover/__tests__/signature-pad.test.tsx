import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi, beforeAll } from 'vitest';
import { SignaturePad } from '../signature-pad';

beforeAll(() => {
    // happy-dom canvas: stub 2d context + toDataURL
    HTMLCanvasElement.prototype.getContext = vi.fn(() => ({
        fillStyle: '', lineWidth: 0, lineCap: '', strokeStyle: '',
        fillRect: vi.fn(), beginPath: vi.fn(), moveTo: vi.fn(), lineTo: vi.fn(), stroke: vi.fn(), closePath: vi.fn(),
    })) as unknown as typeof HTMLCanvasElement.prototype.getContext;
    HTMLCanvasElement.prototype.toDataURL = vi.fn(() => 'data:image/png;base64,SIGDATA');
});

describe('SignaturePad', () => {
    it('emits stripped base64 on a stroke and empties on clear', () => {
        const onChange = vi.fn();
        render(<SignaturePad value="" onChange={onChange} />);
        const canvas = screen.getByTestId('signature-canvas');

        fireEvent.pointerDown(canvas, { clientX: 5, clientY: 5 });
        fireEvent.pointerMove(canvas, { clientX: 20, clientY: 20 });
        fireEvent.pointerUp(canvas);
        expect(onChange).toHaveBeenLastCalledWith('SIGDATA'); // prefix stripped

        fireEvent.click(screen.getByRole('button', { name: /clear/i }));
        expect(onChange).toHaveBeenLastCalledWith('');
    });
});
