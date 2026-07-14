import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { FormError } from '../form-error';

describe('FormError', () => {
    it('renders the message when present', () => {
        render(<FormError message="Name is required" />);
        expect(screen.getByText('Name is required')).toBeInTheDocument();
    });

    it('renders nothing when no message', () => {
        const { container } = render(<FormError />);
        expect(container).toBeEmptyDOMElement();
    });
});
