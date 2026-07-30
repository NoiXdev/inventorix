import '@testing-library/jest-dom/vitest';
import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';

// `globals: false` in vitest.config.ts means @testing-library/react's
// auto-cleanup (which relies on a global `afterEach`) never registers,
// so unmounted renders leak across tests within the same file. Register
// it explicitly to keep each test's DOM isolated.
afterEach(() => {
    cleanup();
});
