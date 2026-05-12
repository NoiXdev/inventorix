window.addEventListener('qr-print:open', (event) => {
    console.log('[qr-print] open event received', (event as CustomEvent).detail);
});
