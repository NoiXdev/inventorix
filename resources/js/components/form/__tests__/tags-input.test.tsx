import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { TagsInput } from '../tags-input';

describe('TagsInput', () => {
    it('renders existing tags as chips', () => {
        render(<TagsInput id="tags" label="Tags" value={['alpha', 'beta']} onChange={() => {}} />);
        expect(screen.getByText('alpha')).toBeInTheDocument();
        expect(screen.getByText('beta')).toBeInTheDocument();
    });
    it('adds a trimmed tag on Enter', () => {
        const onChange = vi.fn();
        render(<TagsInput id="tags" label="Tags" value={['alpha']} onChange={onChange} />);
        const input = screen.getByLabelText('Tags');
        fireEvent.change(input, { target: { value: '  gamma  ' } });
        fireEvent.keyDown(input, { key: 'Enter' });
        expect(onChange).toHaveBeenCalledWith(['alpha', 'gamma']);
    });
    it('does not add duplicate or empty tags', () => {
        const onChange = vi.fn();
        render(<TagsInput id="tags" label="Tags" value={['alpha']} onChange={onChange} />);
        const input = screen.getByLabelText('Tags');
        fireEvent.change(input, { target: { value: 'alpha' } });
        fireEvent.keyDown(input, { key: 'Enter' });
        fireEvent.change(input, { target: { value: '   ' } });
        fireEvent.keyDown(input, { key: 'Enter' });
        expect(onChange).not.toHaveBeenCalled();
    });
    it('removes a tag when its × is clicked', () => {
        const onChange = vi.fn();
        render(<TagsInput id="tags" label="Tags" value={['alpha', 'beta']} onChange={onChange} />);
        fireEvent.click(screen.getByRole('button', { name: 'Remove alpha' }));
        expect(onChange).toHaveBeenCalledWith(['beta']);
    });
});
