import { render, screen, fireEvent, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { PermissionMatrix, type CatalogueSection } from '../permission-matrix';

const catalogue: CatalogueSection[] = [
    { section: 'global', label: 'Global', permissions: [{ value: 'superuser', label: 'Superuser' }, { value: 'admin', label: 'Admin' }] },
    { section: 'assets', label: 'Assets', permissions: [{ value: 'assets.view', label: 'View' }, { value: 'assets.edit', label: 'Edit' }] },
];

describe('PermissionMatrix', () => {
    it('grant mode: "all" in the assets row grants every assets.* permission', () => {
        const onChange = vi.fn();
        render(<PermissionMatrix catalogue={catalogue} mode="grant" value={{}} onChange={onChange} />);
        fireEvent.click(screen.getByRole('checkbox', { name: 'All Assets' }));
        expect(onChange).toHaveBeenCalledWith({ 'assets.view': 1, 'assets.edit': 1 });
    });

    it('grant mode: toggling a checkbox adds and removes the key', () => {
        const onChange = vi.fn();
        const { rerender } = render(<PermissionMatrix catalogue={catalogue} mode="grant" value={{}} onChange={onChange} />);
        fireEvent.click(screen.getByRole('checkbox', { name: 'Assets View' }));
        expect(onChange).toHaveBeenLastCalledWith({ 'assets.view': 1 });
        rerender(<PermissionMatrix catalogue={catalogue} mode="grant" value={{ 'assets.view': 1 }} onChange={onChange} />);
        fireEvent.click(screen.getByRole('checkbox', { name: 'Assets View' }));
        expect(onChange).toHaveBeenLastCalledWith({});
    });

    it('hidePrivileged hides the superuser and admin controls', () => {
        render(<PermissionMatrix catalogue={catalogue} mode="grant" value={{}} onChange={() => {}} hidePrivileged />);
        expect(screen.queryByRole('checkbox', { name: 'Global Superuser' })).toBeNull();
        expect(screen.getByRole('checkbox', { name: 'Assets View' })).toBeInTheDocument();
    });

    it('tristate mode cycles inherit -> grant -> deny, omitting inherit keys', () => {
        const onChange = vi.fn();
        const { rerender } = render(<PermissionMatrix catalogue={catalogue} mode="tristate" value={{}} onChange={onChange} />);
        const group = () => screen.getByRole('radiogroup', { name: 'Assets View' });

        fireEvent.click(within(group()).getByRole('radio', { name: 'Grant' }));
        expect(onChange).toHaveBeenLastCalledWith({ 'assets.view': 1 });

        rerender(<PermissionMatrix catalogue={catalogue} mode="tristate" value={{ 'assets.view': 1 }} onChange={onChange} />);
        fireEvent.click(within(group()).getByRole('radio', { name: 'Deny' }));
        expect(onChange).toHaveBeenLastCalledWith({ 'assets.view': -1 });

        rerender(<PermissionMatrix catalogue={catalogue} mode="tristate" value={{ 'assets.view': -1 }} onChange={onChange} />);
        fireEvent.click(within(group()).getByRole('radio', { name: 'Inherit' }));
        expect(onChange).toHaveBeenLastCalledWith({});
    });

    it('tristate mode shows the effective source', () => {
        render(<PermissionMatrix catalogue={catalogue} mode="tristate" value={{}} onChange={() => {}}
            effective={{ 'assets.view': { granted: true, via: 'IT-Admin' } }} />);
        expect(screen.getByText('Granted by IT-Admin')).toBeInTheDocument();
    });
});
