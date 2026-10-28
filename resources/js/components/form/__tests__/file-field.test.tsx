import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi, beforeAll } from 'vitest';
import { FileField } from '../file-field';

// happy-dom has no object-URL implementation; the component only needs a
// string back and a revoke it can call.
beforeAll(() => {
    URL.createObjectURL = vi.fn(() => 'blob:preview');
    URL.revokeObjectURL = vi.fn();
});

const png = () => new File(['x'], 'logo.png', { type: 'image/png' });

describe('FileField', () => {
    it('reports the picked file to the caller', () => {
        const onChange = vi.fn();
        render(<FileField id="image" label="Logo" value={null} onChange={onChange} />);

        const file = png();
        fireEvent.change(screen.getByLabelText('Logo'), { target: { files: [file] } });

        expect(onChange).toHaveBeenCalledWith(file);
    });

    it('previews the staged image', () => {
        render(<FileField id="image" label="Logo" value={png()} onChange={() => {}} />);

        expect(screen.getByRole('img')).toHaveAttribute('src', 'blob:preview');
    });

    it('shows the stored file when nothing new is staged', () => {
        render(<FileField id="image" label="Logo" value={null} onChange={() => {}} currentUrl="/app/manufacturers/1/image" />);

        expect(screen.getByRole('img')).toHaveAttribute('src', '/app/manufacturers/1/image');
    });

    it('clears the staged file without touching the stored one', () => {
        const onChange = vi.fn();
        render(<FileField id="image" label="Logo" value={png()} onChange={onChange} currentUrl="/stored.png" />);

        fireEvent.click(screen.getByRole('button', { name: /discard selection/i }));

        expect(onChange).toHaveBeenCalledWith(null);
    });

    it('offers removal only for a stored file and only when allowed', () => {
        const onRemove = vi.fn();
        const { rerender } = render(
            <FileField id="image" label="Logo" value={null} onChange={() => {}} currentUrl="/stored.png" onRemoveCurrent={onRemove} />,
        );
        fireEvent.click(screen.getByRole('button', { name: /^remove$/i }));
        expect(onRemove).toHaveBeenCalled();

        // no stored file -> nothing to remove
        rerender(<FileField id="image" label="Logo" value={null} onChange={() => {}} onRemoveCurrent={onRemove} />);
        expect(screen.queryByRole('button', { name: /^remove$/i })).not.toBeInTheDocument();

        // no handler -> removal is forbidden
        rerender(<FileField id="image" label="Logo" value={null} onChange={() => {}} currentUrl="/stored.png" />);
        expect(screen.queryByRole('button', { name: /^remove$/i })).not.toBeInTheDocument();
    });

    it('hides the preview once the stored file is marked for removal', () => {
        render(
            <FileField id="image" label="Logo" value={null} onChange={() => {}} currentUrl="/stored.png"
                onRemoveCurrent={() => {}} currentRemoved />,
        );

        expect(screen.queryByRole('img')).not.toBeInTheDocument();
    });

    it('renders the hint and the validation error', () => {
        render(<FileField id="image" label="Logo" value={null} onChange={() => {}} hint="PNG, max 2 MB" error="Too large" />);

        expect(screen.getByText('PNG, max 2 MB')).toBeInTheDocument();
        expect(screen.getByText('Too large')).toBeInTheDocument();
        expect(screen.getByLabelText('Logo')).toHaveAttribute('aria-invalid', 'true');
    });
});
