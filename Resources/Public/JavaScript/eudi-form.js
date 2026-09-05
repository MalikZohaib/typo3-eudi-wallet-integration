(function () {
    'use strict';

    const DEFAULT_START_URL = '/wallet/start';
    const DEFAULT_RESULT_URL = '/wallet/claims';

    const SESSION_PARAMETER = 'eudi_session';

    /*
     * Poll every 2 seconds.
     */
    const POLL_INTERVAL = 2000;

    /*
     * Stop polling after 5 minutes.
     */
    const POLL_TIMEOUT = 5 * 60 * 1000;


    /*
     * ============================================================
     * INITIALIZE
     * ============================================================
     */

    function initialize() {

        document
            .querySelectorAll('[data-eudi-wallet]')
            .forEach(
                initializeWalletElement
            );

        /*
         * IMPORTANT:
         *
         * Check whether this page was loaded after
         * EUDI verification.
         */
        handleWalletReturn();
    }


    /*
     * ============================================================
     * INITIALIZE WALLET BUTTON
     * ============================================================
     */

    function initializeWalletElement(
        walletElement
    ) {

        const button =
            walletElement.querySelector(
                '.eudi-wallet-verify-button'
            );

        if (!button) {
            console.warn(
                '[T3Hub EUDI] Verify button not found.'
            );

            return;
        }

        if (
            button.dataset.eudiInitialized ===
            'true'
        ) {
            return;
        }

        button.dataset.eudiInitialized =
            'true';

        button.addEventListener(
            'click',
            function (event) {

                event.preventDefault();

                startWalletVerification(
                    walletElement,
                    button
                );
            }
        );
    }


    /*
     * ============================================================
     * START EUDI VERIFICATION
     *
     * Form
     *   ↓
     * /wallet/start
     *   ↓
     * QR page
     * ============================================================
     */

    function startWalletVerification(
        walletElement,
        button
    ) {

        if (
            button.dataset.eudiBusy ===
            'true'
        ) {
            return;
        }

        button.dataset.eudiBusy =
            'true';

        button.disabled =
            true;

        const originalText =
            button.textContent;

        button.dataset.originalText =
            originalText;

        button.textContent =
            'Opening EUDI Wallet...';

        showStatus(
            walletElement,
            'Preparing EUDI Wallet verification...'
        );


        try {

            /*
             * ----------------------------------------------------
             * Configuration UID
             * ----------------------------------------------------
             */

            const configuration =
                walletElement.dataset.eudiConfiguration;

            if (!configuration) {

                throw new Error(
                    'EUDI Wallet configuration UID is missing.'
                );
            }


            /*
             * ----------------------------------------------------
             * Current form URL
             * ----------------------------------------------------
             *
             * This is where the middleware will redirect
             * after successful verification.
             */
            const returnUrl =
                window.location.href;


            /*
             * ----------------------------------------------------
             * Start endpoint
             * ----------------------------------------------------
             */

            const startUrl =
                walletElement.dataset.eudiStartUrl ||
                DEFAULT_START_URL;


            const url =
                new URL(
                    startUrl,
                    window.location.origin
                );


            /*
             * Configuration.
             */
            url.searchParams.set(
                'configuration',
                configuration
            );


            /*
             * IMPORTANT:
             *
             * Tell the middleware that we want the
             * verified claims after returning.
             */
            url.searchParams.set(
                'return_mode',
                'claims'
            );


            /*
             * Return URL.
             */
            url.searchParams.set(
                'return_url',
                returnUrl
            );


            console.log(
                '[T3Hub EUDI] Starting verification:',
                url.toString()
            );


            /*
             * ----------------------------------------------------
             * IMPORTANT
             *
             * Do NOT use fetch() here.
             *
             * Your start endpoint returns the actual
             * QR page.
             *
             * Navigate directly to it.
             * ----------------------------------------------------
             */

            window.location.href =
                url.toString();


        } catch (error) {

            console.error(
                '[T3Hub EUDI] Unable to start verification:',
                error
            );

            button.disabled =
                false;

            button.dataset.eudiBusy =
                'false';

            button.textContent =
                originalText;

            showStatus(
                walletElement,
                error.message
            );
        }
    }


    /*
     * ============================================================
     * RETURN FROM WALLET
     *
     * Form URL:
     *
     * /form?eudi_session=ABC123
     *
     * Start polling.
     * ============================================================
     */

    function handleWalletReturn() {

        const currentUrl =
            new URL(
                window.location.href
            );


        const sessionId =
            currentUrl.searchParams.get(
                SESSION_PARAMETER
            );


        /*
         * No EUDI session.
         *
         * Normal form visit.
         */
        if (!sessionId) {
            return;
        }


        console.log(
            '[T3Hub EUDI] Returned from wallet.'
        );

        console.log(
            '[T3Hub EUDI] Session ID:',
            sessionId
        );


        const walletElement =
            document.querySelector(
                '[data-eudi-wallet]'
            );


        if (!walletElement) {

            console.warn(
                '[T3Hub EUDI] EUDI Wallet element not found.'
            );

            return;
        }


        /*
         * Save session ID.
         */
        walletElement.dataset.eudiSessionId =
            sessionId;


        /*
         * Remove the session ID from the
         * browser address bar.
         */
        currentUrl.searchParams.delete(
            SESSION_PARAMETER
        );


        window.history.replaceState(
            {},
            document.title,
            currentUrl.toString()
        );


        /*
         * Start polling.
         */
        showStatus(
            walletElement,
            'Waiting for EUDI verification result...'
        );


        pollForClaims(
            walletElement,
            sessionId
        );
    }


    /*
     * ============================================================
     * POLL FOR CLAIMS
     * ============================================================
     */

    async function pollForClaims(
        walletElement,
        sessionId
    ) {

        const resultUrl =
            walletElement.dataset.eudiResultUrl ||
            DEFAULT_RESULT_URL;


        const startedAt =
            Date.now();


        console.log(
            '[T3Hub EUDI] Starting claim polling.'
        );


        while (
            Date.now() - startedAt <
            POLL_TIMEOUT
        ) {

            try {

                const result =
                    await requestVerificationResult(
                        resultUrl,
                        sessionId
                    );


                console.log(
                    '[T3Hub EUDI] Poll response:',
                    result
                );


                /*
                 * ------------------------------------------------
                 * CLAIMS AVAILABLE
                 * ------------------------------------------------
                 */

                const claims =
                    result.claims ||
                    result.verifiedClaims ||
                    result.verified_claims;


                if (
                    result.success === true &&
                    claims &&
                    typeof claims === 'object'
                ) {

                    console.log(
                        '[T3Hub EUDI] Verified claims received:',
                        claims
                    );


                    verificationSuccessful(
                        walletElement,
                        claims
                    );

                    return;
                }


                /*
                 * ------------------------------------------------
                 * EXPLICIT SUCCESS STATUS
                 * ------------------------------------------------
                 */

                if (
                    (
                        result.status === 'verified' ||
                        result.status === 'complete' ||
                        result.status === 'completed' ||
                        result.status === 'success'
                    ) &&
                    claims &&
                    typeof claims === 'object'
                ) {

                    verificationSuccessful(
                        walletElement,
                        claims
                    );

                    return;
                }


                /*
                 * ------------------------------------------------
                 * REJECTED
                 * ------------------------------------------------
                 */

                if (
                    result.status === 'rejected' ||
                    result.status === 'failed' ||
                    result.status === 'error'
                ) {

                    showStatus(
                        walletElement,
                        result.message ||
                        'EUDI Wallet verification failed.'
                    );

                    return;
                }


                /*
                 * ------------------------------------------------
                 * STILL WAITING
                 * ------------------------------------------------
                 */

                showStatus(
                    walletElement,
                    'Waiting for EUDI Wallet verification...'
                );


            } catch (error) {

                /*
                 * A temporary HTTP/network problem should
                 * not immediately stop polling.
                 */
                console.warn(
                    '[T3Hub EUDI] Poll request failed. Retrying...',
                    error
                );
            }


            await sleep(
                POLL_INTERVAL
            );
        }


        showStatus(
            walletElement,
            'EUDI Wallet verification timed out.'
        );


        console.warn(
            '[T3Hub EUDI] Polling timeout.'
        );
    }


    /*
     * ============================================================
     * REQUEST VERIFICATION RESULT
     * ============================================================
     */

    async function requestVerificationResult(
        resultUrl,
        sessionId
    ) {

        const url =
            new URL(
                resultUrl,
                window.location.origin
            );


        url.searchParams.set(
            'session_id',
            sessionId
        );


        const response =
            await fetch(
                url.toString(),
                {
                    method: 'GET',

                    credentials:
                        'same-origin',

                    cache:
                        'no-store',

                    headers: {
                        'Accept':
                            'application/json'
                    }
                }
            );


        if (!response.ok) {

            throw new Error(
                `HTTP ${response.status}`
            );
        }


        return response.json();
    }


    /*
     * ============================================================
     * VERIFICATION SUCCESSFUL
     * ============================================================
     */

    function verificationSuccessful(
        walletElement,
        claims
    ) {

        console.log(
            '[T3Hub EUDI] Verification successful.'
        );


        console.log(
            '[T3Hub EUDI] Claims:',
            claims
        );


        /*
         * Autofill TYPO3 form.
         */
        autofillClaims(
            walletElement,
            claims
        );


        /*
         * Success message.
         */
        showStatus(
            walletElement,
            'Identity verified successfully.'
        );


        /*
         * Update button.
         */
        const button =
            walletElement.querySelector(
                '.eudi-wallet-verify-button'
            );


        if (button) {

            button.disabled =
                false;

            button.dataset.eudiBusy =
                'false';

            button.textContent =
                'Verified with EUDI Wallet';
        }


        /*
         * Dispatch event.
         *
         * Other TYPO3 extensions can listen to:
         *
         * document.addEventListener(
         *     'eudi:verified',
         *     ...
         * );
         */
        walletElement.dispatchEvent(
            new CustomEvent(
                'eudi:verified',
                {
                    bubbles: true,

                    detail: {
                        claims: claims,

                        sessionId:
                            walletElement.dataset.eudiSessionId
                    }
                }
            )
        );
    }


    /*
     * ============================================================
     * GET CLAIM MAPPINGS
     * ============================================================
     */

    function getClaimMappings(
        walletElement
    ) {

        const raw =
            walletElement.dataset.eudiClaims;


        if (!raw) {

            console.warn(
                '[T3Hub EUDI] No claim mappings found.'
            );

            return {};
        }


        try {

            return JSON.parse(
                raw
            );

        } catch (error) {

            console.error(
                '[T3Hub EUDI] Invalid claim mapping JSON:',
                error
            );

            return {};
        }
    }


    /*
     * ============================================================
     * AUTOFILL
     * ============================================================
     */

    function autofillClaims(
        walletElement,
        claims
    ) {

        const form =
            walletElement.closest(
                'form'
            );


        if (!form) {

            console.warn(
                '[T3Hub EUDI] TYPO3 form not found.'
            );

            return;
        }


        const mappings =
            getClaimMappings(
                walletElement
            );


        console.log(
            '[T3Hub EUDI] Autofill mappings:',
            mappings
        );


        let filled =
            0;


        Object.entries(
            mappings
        ).forEach(
            ([fieldIdentifier, claimPath]) => {

                const value =
                    getClaimValue(
                        claims,
                        claimPath
                    );


                if (
                    value === undefined ||
                    value === null
                ) {

                    console.warn(
                        `[T3Hub EUDI] Claim "${claimPath}" not found.`
                    );

                    return;
                }


                const field =
                    findFormField(
                        form,
                        fieldIdentifier
                    );


                if (!field) {

                    console.warn(
                        `[T3Hub EUDI] Form field "${fieldIdentifier}" not found.`
                    );

                    return;
                }


                setFieldValue(
                    field,
                    value
                );


                filled++;


                console.log(
                    `[T3Hub EUDI] Filled "${fieldIdentifier}" with "${claimPath}".`
                );
            }
        );


        console.log(
            `[T3Hub EUDI] ${filled} field(s) autofilled.`
        );
    }


    /*
     * ============================================================
     * FIND TYPO3 FIELD
     * ============================================================
     */

    function findFormField(
        form,
        identifier
    ) {

        /*
         * Exact name.
         */
        let field =
            form.querySelector(
                `[name="${CSS.escape(identifier)}"]`
            );


        if (field) {
            return field;
        }


        /*
         * TYPO3 generated name.
         */
        field =
            form.querySelector(
                `[name$="[${CSS.escape(identifier)}]"]`
            );


        if (field) {
            return field;
        }


        /*
         * ID.
         */
        field =
            form.querySelector(
                `#${CSS.escape(identifier)}`
            );


        return field || null;
    }


    /*
     * ============================================================
     * CLAIM VALUE
     * ============================================================
     */

    function getClaimValue(
        claims,
        claimPath
    ) {

        if (
            !claims ||
            typeof claims !== 'object' ||
            !claimPath
        ) {

            return undefined;
        }


        /*
         * Direct claim.
         */
        if (
            Object.prototype.hasOwnProperty.call(
                claims,
                claimPath
            )
        ) {

            return claims[
                claimPath
            ];
        }


        /*
         * Nested claim.
         *
         * Example:
         *
         * address.street
         */
        const parts =
            claimPath.split('.');


        let value =
            claims;


        for (
            const part of parts
        ) {

            if (
                value === null ||
                value === undefined ||
                typeof value !== 'object'
            ) {

                return undefined;
            }


            if (
                !Object.prototype.hasOwnProperty.call(
                    value,
                    part
                )
            ) {

                return undefined;
            }


            value =
                value[part];
        }


        return value;
    }


    /*
     * ============================================================
     * SET FIELD VALUE
     * ============================================================
     */

    function setFieldValue(
        field,
        value
    ) {

        /*
         * SELECT
         */
        if (
            field instanceof HTMLSelectElement
        ) {

            const stringValue =
                String(value);


            const option =
                Array.from(
                    field.options
                ).find(
                    (option) =>
                        option.value ===
                        stringValue
                );


            if (!option) {

                console.warn(
                    `[T3Hub EUDI] Select option "${stringValue}" not found.`
                );

                return;
            }


            field.value =
                stringValue;


            dispatchFieldEvents(
                field
            );


            return;
        }


        /*
         * CHECKBOX
         */
        if (
            field instanceof HTMLInputElement &&
            field.type === 'checkbox'
        ) {

            field.checked =
                isTruthy(value);


            dispatchFieldEvents(
                field
            );


            return;
        }


        /*
         * RADIO
         */
        if (
            field instanceof HTMLInputElement &&
            field.type === 'radio'
        ) {

            field.checked =
                field.value ===
                String(value);


            if (field.checked) {

                dispatchFieldEvents(
                    field
                );
            }


            return;
        }


        /*
         * ARRAY
         */
        if (
            Array.isArray(value)
        ) {

            value =
                value.join(', ');
        }


        /*
         * OBJECT
         */
        if (
            typeof value === 'object' &&
            value !== null
        ) {

            value =
                JSON.stringify(value);
        }


        /*
         * TEXT / EMAIL / TEL / DATE / NUMBER /
         * TEXTAREA
         */
        field.value =
            String(value);


        dispatchFieldEvents(
            field
        );
    }


    /*
     * ============================================================
     * DISPATCH EVENTS
     * ============================================================
     */

    function dispatchFieldEvents(
        field
    ) {

        field.dispatchEvent(
            new Event(
                'input',
                {
                    bubbles: true
                }
            )
        );


        field.dispatchEvent(
            new Event(
                'change',
                {
                    bubbles: true
                }
            )
        );
    }


    /*
     * ============================================================
     * BOOLEAN
     * ============================================================
     */

    function isTruthy(
        value
    ) {

        return [
            true,
            1,
            '1',
            'true',
            'yes',
            'on'
        ].includes(value);
    }


    /*
     * ============================================================
     * STATUS
     * ============================================================
     */

    function showStatus(
        walletElement,
        message
    ) {

        const status =
            walletElement.querySelector(
                '[data-eudi-wallet-status]'
            );


        if (status) {

            status.textContent =
                message;
        }
    }


    /*
     * ============================================================
     * SLEEP
     * ============================================================
     */

    function sleep(
        milliseconds
    ) {

        return new Promise(
            (resolve) => {

                window.setTimeout(
                    resolve,
                    milliseconds
                );
            }
        );
    }


    /*
     * ============================================================
     * PUBLIC API
     * ============================================================
     */

    window.T3HubEudiWalletIntegration =
        window.T3HubEudiWalletIntegration || {};

    window.T3HubEudiWalletIntegration.autofillClaims =
        autofillClaims;


    /*
     * ============================================================
     * RUN
     * ============================================================
     */

    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initialize
        );

    } else {

        initialize();
    }

})();