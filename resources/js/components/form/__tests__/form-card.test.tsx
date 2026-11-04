import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { FormCard, FormCardContent, FormCardHeader } from '../form-card';

describe('FormCard', () => {
    it('renders a header with title and description above the content', () => {
        render(
            <FormCard>
                <FormCardHeader title="General" description="Basic data" />
                <FormCardContent><input aria-label="Name" /></FormCardContent>
            </FormCard>,
        );
        expect(screen.getByText('General')).toBeInTheDocument();
        expect(screen.getByText('Basic data')).toBeInTheDocument();
        const header = screen.getByText('General').closest('[data-slot="form-card-header"]')!;
        const content = screen.getByLabelText('Name').closest('[data-slot="form-card-content"]')!;
        expect(header.compareDocumentPosition(content) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    it('omits title and description when not given', () => {
        const { container } = render(<FormCard><FormCardHeader /></FormCard>);
        expect(container.querySelector('[data-slot="card-title"]')).toBeNull();
        expect(container.querySelector('[data-slot="card-description"]')).toBeNull();
    });
});
