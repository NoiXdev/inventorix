import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { AssetImportDialog } from '../asset-import-dialog';

describe('AssetImportDialog', () => {
    it('opens the dialog with a file input', () => {
        render(<AssetImportDialog />);
        fireEvent.click(screen.getByRole('button', { name: /import/i }));
        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(screen.getByLabelText(/file/i)).toHaveAttribute('type', 'file');
    });
});
