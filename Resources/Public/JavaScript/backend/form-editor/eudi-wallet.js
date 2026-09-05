
/**
 * EUDI Wallet - TYPO3 Form Editor integration
 *
 * File:
 * Resources/Public/JavaScript/backend/form-editor/eudi-wallet.js
 */

let formEditorApp = null;


/**
 * Bootstrap TYPO3 Form Editor integration.
 *
 * TYPO3 loads this module through:
 *
 * formEditor.dynamicJavaScriptModules
 *
 * or:
 *
 * formEditor.additionalViewModelModules
 *
 * depending on the configuration.
 *
 * @param {Object} app
 */
export function bootstrap(app) {
    formEditorApp = app;

    console.log(
        '[T3Hub EUDI] Form Editor module loaded.'
    );

    const publisherSubscriber =
        formEditorApp.getPublisherSubscriber();

    /*
     * Form Editor is ready.
     */
    publisherSubscriber.subscribe(
        'view/ready',
        () => {
            console.log(
                '[T3Hub EUDI] Form Editor ready.'
            );
        }
    );

    /*
     * TYPO3 inserts an inspector editor.
     *
     * This is the important event for our custom
     * EUDI mapping inspector.
     */
    publisherSubscriber.subscribe(
        'view/inspector/editor/insert/perform',
        (topic, args) => {
            handleInspectorEditor(args);
        }
    );

    /*
     * Currently selected FormElement changed.
     */
    publisherSubscriber.subscribe(
        'core/currentlySelectedFormElementChanged',
        () => {
            console.log(
                '[T3Hub EUDI] Selected FormElement changed.'
            );
        }
    );
}


/**
 * Handle an inserted inspector editor.
 *
 * TYPO3 passes information about the inspector
 * editor in the event arguments.
 *
 * @param {Array} args
 */
function handleInspectorEditor(args) {
    console.log(
        '[T3Hub EUDI] Inspector event arguments:',
        args
    );

    const editorConfiguration =
        args && args[0]
            ? args[0]
            : {};

    const editorHtml =
        args && args[1]
            ? args[1]
            : '';

    console.log(
        '[T3Hub EUDI] Inspector editor configuration:',
        editorConfiguration
    );

    console.log(
        '[T3Hub EUDI] Inspector editor HTML:',
        editorHtml
    );

    /*
     * Only react to our custom inspector.
     */
    if (
        editorConfiguration.templateName !==
        'Inspector-EudiClaimMappingEditor'
    ) {
        return;
    }

    const eudiElement =
        getSelectedEudiElement();

    if (!eudiElement) {
        console.warn(
            '[T3Hub EUDI] No EUDI Wallet element selected.'
        );

        return;
    }

    /*
     * TYPO3 may still be finishing the DOM insertion.
     *
     * Wait until the next rendering frame.
     */
    window.requestAnimationFrame(
        () => {
            initializeMappingEditor(
                eudiElement
            );
        }
    );
}


/**
 * Return currently selected EUDI FormElement.
 *
 * @returns {Object|null}
 */
function getSelectedEudiElement() {
    if (!formEditorApp) {
        return null;
    }

    const element =
        formEditorApp.getCurrentlySelectedFormElement();

    if (!element) {
        return null;
    }

    let type = null;

    try {
        type =
            element.get('type');
    } catch (error) {
        console.error(
            '[T3Hub EUDI] Unable to read FormElement type:',
            error
        );

        return null;
    }

    if (
        type !==
        'EudiWalletIntegrationVerification'
    ) {
        return null;
    }

    return element;
}


/**
 * Initialize the mapping editor.
 *
 * @param {Object} eudiElement
 */
function initializeMappingEditor(
    eudiElement
) {
    const editors =
        document.querySelectorAll(
            '[data-eudi-claim-mapping-editor]'
        );

    console.log(
        '[T3Hub EUDI] Mapping editor elements found:',
        editors.length
    );

    if (!editors.length) {
        console.warn(
            '[T3Hub EUDI] Mapping editor DOM element not found.'
        );

        return;
    }

    /*
     * TYPO3 may contain more than one instance in the DOM.
     *
     * The most recently inserted one is the one we want.
     */
    const editor =
        editors[editors.length - 1];

    if (
        editor.dataset.initialized ===
        'true'
    ) {
        return;
    }

    editor.dataset.initialized =
        'true';

    console.log(
        '[T3Hub EUDI] Initializing mapping editor:',
        editor
    );

    renderMappingEditor(
        editor,
        eudiElement
    );
}


/**
 * Render the complete mapping UI.
 *
 * @param {HTMLElement} editor
 * @param {Object} eudiElement
 */
function renderMappingEditor(
    editor,
    eudiElement
) {
    editor.innerHTML = '';

    const mappings =
        eudiElement.get(
            'properties.claimMappings'
        ) || [];

    const fields =
        getCurrentFormFields(
            eudiElement
        );

    console.log(
        '[T3Hub EUDI] Current mappings:',
        mappings
    );

    console.log(
        '[T3Hub EUDI] Available form fields:',
        fields
    );

    /*
     * Container.
     */
    const container =
        document.createElement(
            'div'
        );

    container.className =
        'eudi-mapping-container';

    /*
     * Header.
     */
    const header =
        document.createElement(
            'div'
        );

    header.className =
        'eudi-mapping-header';

    header.innerHTML = `
        <div class="eudi-mapping-column">
            TYPO3 field
        </div>

        <div class="eudi-mapping-column">
            EUDI claim
        </div>

        <div class="eudi-mapping-actions">
        </div>
    `;

    container.appendChild(
        header
    );

    /*
     * Existing mappings.
     */
    if (
        Array.isArray(mappings)
    ) {
        mappings.forEach(
            (mapping) => {

                addMappingRow(
                    container,
                    eudiElement,
                    fields,
                    mapping.formField || '',
                    mapping.claim || ''
                );
            }
        );
    }

    /*
     * Add mapping button.
     */
    const addButton =
        document.createElement(
            'button'
        );

    addButton.type =
        'button';

    addButton.className =
        'btn btn-default';

    addButton.textContent =
        '+ Add mapping';

    addButton.addEventListener(
        'click',
        () => {

            addMappingRow(
                container,
                eudiElement,
                fields,
                '',
                ''
            );
        }
    );

    container.appendChild(
        addButton
    );

    editor.appendChild(
        container
    );
}


/**
 * Get all usable fields from the current Form.
 *
 * @param {Object} eudiElement
 * @returns {Array}
 */
function getCurrentFormFields(
    eudiElement
) {
    const fields = [];

    /*
     * Start from the EUDI element and walk upwards
     * using TYPO3's __parentRenderable property.
     */
    let root =
        eudiElement;

    while (root) {
        let parent = null;

        try {
            parent =
                root.get(
                    '__parentRenderable'
                );
        } catch (error) {
            console.warn(
                '[T3Hub EUDI] Could not read parent renderable:',
                error
            );

            break;
        }

        if (!parent) {
            break;
        }

        root =
            parent;
    }

    console.log(
        '[T3Hub EUDI] Form root:',
        root
    );

    /*
     * Walk through all renderables.
     */
    collectFormFields(
        root,
        fields,
        eudiElement
    );

    return fields;
}


/**
 * Recursively collect form fields.
 *
 * TYPO3 stores child FormElements in:
 *
 * renderables
 *
 * @param {Object} element
 * @param {Array} fields
 * @param {Object} eudiElement
 */
function collectFormFields(
    element,
    fields,
    eudiElement
) {
    if (!element) {
        return;
    }

    let type = null;
    let identifier = null;
    let label = null;

    try {
        type =
            element.get('type');

        identifier =
            element.get('identifier');

        label =
            element.get('label');
    } catch (error) {
        console.warn(
            '[T3Hub EUDI] Unable to read FormElement:',
            error
        );

        return;
    }

    /*
     * Don't include our own EUDI Wallet element.
     */
    if (
        element !== eudiElement &&
        identifier &&
        isInputElement(type)
    ) {
        fields.push({
            identifier: identifier,
            label: label || identifier,
            type: type
        });
    }

    /*
     * Get child renderables.
     */
    let renderables = null;

    try {
        renderables =
            element.get('renderables');
    } catch (error) {
        console.warn(
            '[T3Hub EUDI] Unable to read renderables:',
            error
        );

        return;
    }

    if (
        !Array.isArray(renderables)
    ) {
        return;
    }

    renderables.forEach(
        (child) => {

            collectFormFields(
                child,
                fields,
                eudiElement
            );
        }
    );
}


/**
 * Determine whether the FormElement is an
 * input field that can be populated.
 *
 * @param {string} type
 * @returns {boolean}
 */
function isInputElement(type) {
    return [
        'Text',
        'Email',
        'Telephone',
        'Number',
        'Date',
        'DateTime',
        'Textarea',
        'Password',
        'Hidden',
        'Checkbox',
        'RadioButton',
        'SingleSelect',
        'MultiSelect'
    ].includes(type);
}


/**
 * Add one mapping row.
 *
 * @param {HTMLElement} container
 * @param {Object} eudiElement
 * @param {Array} fields
 * @param {string} selectedField
 * @param {string} selectedClaim
 */
function addMappingRow(
    container,
    eudiElement,
    fields,
    selectedField,
    selectedClaim
) {
    const row =
        document.createElement(
            'div'
        );

    row.className =
        'eudi-mapping-row';

    /*
     * Form field select.
     */
    const fieldSelect =
        document.createElement(
            'select'
        );

    fieldSelect.className =
        'form-select';

    fieldSelect.setAttribute(
        'aria-label',
        'TYPO3 Form field'
    );

    const fieldPlaceholder =
        document.createElement(
            'option'
        );

    fieldPlaceholder.value =
        '';

    fieldPlaceholder.textContent =
        'Select form field...';

    fieldSelect.appendChild(
        fieldPlaceholder
    );

    fields.forEach(
        (field) => {

            const option =
                document.createElement(
                    'option'
                );

            option.value =
                field.identifier;

            option.textContent =
                `${field.label} (${field.identifier})`;

            if (
                field.identifier ===
                selectedField
            ) {
                option.selected =
                    true;
            }

            fieldSelect.appendChild(
                option
            );
        }
    );

    /*
     * EUDI claim select.
     */
    const claimSelect =
        document.createElement(
            'select'
        );

    claimSelect.className =
        'form-select';

    claimSelect.setAttribute(
        'aria-label',
        'EUDI claim'
    );

    const claimPlaceholder =
        document.createElement(
            'option'
        );

    claimPlaceholder.value =
        '';

    claimPlaceholder.textContent =
        'Select EUDI claim...';

    claimSelect.appendChild(
        claimPlaceholder
    );

    getAvailableClaims()
        .forEach(
            (claim) => {

                const option =
                    document.createElement(
                        'option'
                    );

                option.value =
                    claim;

                option.textContent =
                    claim;

                if (
                    claim ===
                    selectedClaim
                ) {
                    option.selected =
                        true;
                }

                claimSelect.appendChild(
                    option
                );
            }
        );

    /*
     * Delete button.
     */
    const deleteButton =
        document.createElement(
            'button'
        );

    deleteButton.type =
        'button';

    deleteButton.className =
        'btn btn-default';

    deleteButton.title =
        'Remove mapping';

    deleteButton.setAttribute(
        'aria-label',
        'Remove mapping'
    );

    deleteButton.textContent =
        '×';

    row.appendChild(
        fieldSelect
    );

    row.appendChild(
        claimSelect
    );

    row.appendChild(
        deleteButton
    );

    container.appendChild(
        row
    );

    /*
     * Form field changed.
     */
    fieldSelect.addEventListener(
        'change',
        () => {

            saveMappings(
                container,
                eudiElement
            );
        }
    );

    /*
     * EUDI claim changed.
     */
    claimSelect.addEventListener(
        'change',
        () => {

            saveMappings(
                container,
                eudiElement
            );
        }
    );

    /*
     * Delete mapping.
     */
    deleteButton.addEventListener(
        'click',
        () => {

            row.remove();

            saveMappings(
                container,
                eudiElement
            );
        }
    );
}


/**
 * Get available EUDI claims.
 *
 * TEMPORARY.
 *
 * This will later be populated from the selected
 * EUDI configuration / DCQL definition.
 *
 * @returns {Array<string>}
 */
function getAvailableClaims() {
    return [
        'given_name',
        'family_name',
        'email',
        'birth_date',
        'personal_administrative_number'
    ];
}


/**
 * Save mapping rows to the FormElement model.
 *
 * Structure:
 *
 * [
 *     {
 *         formField: 'firstname',
 *         claim: 'given_name'
 *     },
 *     {
 *         formField: 'lastname',
 *         claim: 'family_name'
 *     }
 * ]
 *
 * @param {HTMLElement} container
 * @param {Object} eudiElement
 */
function saveMappings(
    container,
    eudiElement
) {
    const mappings = [];

    const rows =
        container.querySelectorAll(
            '.eudi-mapping-row'
        );

    rows.forEach(
        (row) => {

            const selects =
                row.querySelectorAll(
                    'select'
                );

            if (
                selects.length < 2
            ) {
                return;
            }

            const formField =
                selects[0].value;

            const claim =
                selects[1].value;

            /*
             * Ignore incomplete rows.
             */
            if (
                !formField ||
                !claim
            ) {
                return;
            }

            /*
             * Prevent duplicate Form fields.
             */
            const alreadyMapped =
                mappings.some(
                    (mapping) =>
                        mapping.formField ===
                        formField
                );

            if (alreadyMapped) {
                console.warn(
                    `[T3Hub EUDI] Form field "${formField}" is already mapped.`
                );

                return;
            }

            mappings.push({
                formField: formField,
                claim: claim
            });
        }
    );

    /*
     * IMPORTANT:
     *
     * Store an ARRAY of objects rather than an object
     * with dynamic property names.
     *
     * This avoids TYPO3 Form Editor HMAC errors such as:
     *
     * No hmac found for
     * properties.claimMappings.name
     */
    eudiElement.set(
        'properties.claimMappings',
        mappings
    );

    console.log(
        '[T3Hub EUDI] Claim mappings saved:',
        mappings
    );
}
