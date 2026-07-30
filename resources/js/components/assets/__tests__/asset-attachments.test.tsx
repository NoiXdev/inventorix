import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { AssetAttachments } from '../asset-attachments';

const base = { assetId: 'a1', categoryOptions: [{ value: 'foto', label: 'Foto' }] };

describe('AssetAttachments', () => {
    it('renders image attachments as thumbnails', () => {
        render(<AssetAttachments {...base} attachments={[
            { id: 'i1', type: 'image', category_label: 'Foto', title: 'Front', note: null, original_name: 'front.jpg', size: 1000, size_label: '1000 B', uploaded_by_name: 'Ada', created_at: '2025-01-01 10:00:00', url: 'http://x/open/i1' },
        ]} />);
        const img = screen.getByRole('img');
        expect(img).toHaveAttribute('src', 'http://x/open/i1');
    });

    it('renders non-image attachments as rows with an Open link', () => {
        render(<AssetAttachments {...base} attachments={[
            { id: 'd1', type: 'document', category_label: 'Dokument', title: 'Manual', note: 'Handle with care', original_name: 'manual.pdf', size: 2048, size_label: '2 KB', uploaded_by_name: 'Ada', created_at: '2025-01-01 10:00:00', url: 'http://x/open/d1' },
        ]} />);
        expect(screen.getByText('manual.pdf')).toBeInTheDocument();
        expect(screen.getByText('Handle with care')).toBeInTheDocument();
        const open = screen.getByRole('link', { name: /open/i });
        expect(open).toHaveAttribute('href', 'http://x/open/d1');
    });

    it('shows an empty state when there are no attachments', () => {
        render(<AssetAttachments {...base} attachments={[]} />);
        expect(screen.getByText(/no attachments/i)).toBeInTheDocument();
    });

    it('renders the passed category option label in the SelectField', () => {
        render(<AssetAttachments {...base} attachments={[]} />);
        expect(screen.getByText('Foto')).toBeInTheDocument();
    });

    it('renders a multi-file file input', () => {
        render(<AssetAttachments {...base} attachments={[]} />);
        const input = screen.getByLabelText('Files');
        expect(input).toHaveAttribute('type', 'file');
        expect(input).toHaveAttribute('multiple');
    });
});
