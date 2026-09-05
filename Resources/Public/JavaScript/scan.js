const root = document.querySelector('[data-eudi-wallet-scan]');
if (root) {
    const statusElement = root.querySelector('[data-eudi-wallet-status]');
    const statusUrl = root.dataset.statusUrl;
    const finishUrl = root.dataset.finishUrl;
    let stopped = false;

    const poll = async () => {
        if (stopped || !statusUrl || !finishUrl) {
            return;
        }
        try {
            const response = await fetch(statusUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {'Accept': 'application/json'},
                cache: 'no-store',
            });
            const data = await response.json();
            if (data.status === 'verified') {
                stopped = true;
                if (statusElement) statusElement.textContent = 'Wallet verified. Completing verification…';
                window.location.assign(finishUrl);
                return;
            }
            if (data.status === 'rejected' || data.status === 'invalid') {
                stopped = true;
                if (statusElement) statusElement.textContent = 'Wallet presentation was rejected or the request expired.';
                return;
            }
            if (statusElement) statusElement.textContent = 'Waiting for wallet response…';
        } catch (_) {
            if (statusElement) statusElement.textContent = 'Still waiting for wallet response…';
        }
        window.setTimeout(poll, 1200);
    };

    window.setTimeout(poll, 400);
}
