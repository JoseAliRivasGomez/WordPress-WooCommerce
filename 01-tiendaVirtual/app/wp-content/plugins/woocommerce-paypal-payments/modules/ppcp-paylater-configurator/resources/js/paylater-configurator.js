document.addEventListener( 'DOMContentLoaded', () => {
    const form = document.querySelector('#mainform');
    const table = form.querySelector('.form-table');
    const headingRow = table.querySelector('#field-pay_later_messaging_heading');
    const saveChangesButton = form.querySelector('.woocommerce-save-button');
    const publishButtonClassName = PcpPayLaterConfigurator.publishButtonClassName;

    const tempContainer = document.createElement('div');
    tempContainer.innerHTML = `<div id='messaging-configurator'></div>`;

    // Get the new row element from the container
    const newRow = tempContainer.firstChild;

    // Insert the new row after the headingRow
    headingRow.parentNode.insertBefore(newRow, headingRow.nextSibling);


    let isSaving = false; // Flag variable to track whether saving is in progress

    saveChangesButton.addEventListener('click', () => {
        // Check if saving is not already in progress
        if (!isSaving) {
            isSaving = true; // Set flag to indicate saving is in progress

            // Trigger the click event on the publish button
            form.querySelector('.' + publishButtonClassName).click();

            // Trigger click event on saveChangesButton after a short delay
            setTimeout(() => {
                saveChangesButton.click(); // Trigger click event on saveChangesButton
                isSaving = false; // Reset flag when saving is complete
            }, 500); // Adjust the delay as needed
        }
    });


    merchantConfigurators.Messaging({
        config: PcpPayLaterConfigurator.config,
        merchantClientId: PcpPayLaterConfigurator.merchantClientId,
        partnerClientId: PcpPayLaterConfigurator.partnerClientId,
        partnerName: 'WooCommerce',
        bnCode: 'Woo_PPCP',
        placements: ['cart', 'checkout', 'product', 'shop', 'home'],
        custom_placement:[{
            message_reference: 'woocommerceBlock',
        }],
        styleOverrides: {
            button: publishButtonClassName,
            header: PcpPayLaterConfigurator.headerClassName,
            subheader: PcpPayLaterConfigurator.subheaderClassName
        },
    onSave: data => {
            fetch(PcpPayLaterConfigurator.ajax.save_config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    nonce: PcpPayLaterConfigurator.ajax.save_config.nonce,
                    config: data,
                }),
            });
        }
    })
} );
