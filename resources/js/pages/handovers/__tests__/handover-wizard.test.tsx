import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { HandoverWizard } from '../handover-wizard';

const props = {
    typeOptions: [
        { value: 'issue', label: 'Issue', allowedStates: ['new', 'storage'], assignsOwner: true },
        { value: 'return', label: 'Return', allowedStates: ['in-use', 'lend'], assignsOwner: false },
    ],
    recipientKindOptions: [{ value: 'internal', label: 'Internal' }, { value: 'external', label: 'External' }],
    personOptions: [{ value: 'u1', label: 'Ada', email: 'ada@x.test' }],
    assetOptions: [
        { value: 'a1', label: '(Acme) X1 — SN1', state: 'new' },
        { value: 'a2', label: '(Dell) U27 — SN2', state: 'in-use' },
    ],
    defaultTerms: 'Default terms',
};

describe('HandoverWizard', () => {
    it('starts on step 1 and filters assets by the selected type allowed states', () => {
        render(<HandoverWizard {...props} />);
        // issue is the first type option (default); only the 'new' asset is eligible
        expect(screen.getByText('(Acme) X1 — SN1')).toBeInTheDocument();
        expect(screen.queryByText('(Dell) U27 — SN2')).not.toBeInTheDocument();
    });

    it('advances to the recipient step via Next', () => {
        render(<HandoverWizard {...props} />);
        // pick the eligible asset so step 1 is valid
        // (radix-ui's Checkbox renders a hidden bubble <input> alongside the
        // visible checkbox button for native form association, so the query
        // is scoped to the button to avoid ambiguous matches)
        fireEvent.click(screen.getByLabelText('(Acme) X1 — SN1', { selector: 'button' }));
        fireEvent.click(screen.getByRole('button', { name: /next/i }));
        // "Recipient kind" is unique to step 2 (the progress bar's "Recipient"
        // label is always present, so match the field label instead).
        expect(screen.getByText('Recipient kind')).toBeInTheDocument();
    });
});
