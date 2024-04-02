import {show} from "../Helper/Hiding";
import {cardFieldStyles} from "../Helper/CardFieldsHelper";

class CardFieldsRenderer {

    constructor(defaultConfig, errorHandler, spinner, onCardFieldsBeforeSubmit) {
        this.defaultConfig = defaultConfig;
        this.errorHandler = errorHandler;
        this.spinner = spinner;
        this.cardValid = false;
        this.formValid = false;
        this.emptyFields = new Set(['number', 'cvv', 'expirationDate']);
        this.currentHostedFieldsInstance = null;
        this.onCardFieldsBeforeSubmit = onCardFieldsBeforeSubmit;
    }

    render(wrapper, contextConfig) {
        if (
            (
                this.defaultConfig.context !== 'checkout'
                && this.defaultConfig.context !== 'pay-now'
            )
            || wrapper === null
            || document.querySelector(wrapper) === null
        ) {
            return;
        }

        const buttonSelector = wrapper + ' button';

        const gateWayBox = document.querySelector('.payment_box.payment_method_ppcp-credit-card-gateway');
        if (!gateWayBox) {
            return
        }

        const oldDisplayStyle = gateWayBox.style.display;
        gateWayBox.style.display = 'block';

        const hideDccGateway = document.querySelector('#ppcp-hide-dcc');
        if (hideDccGateway) {
            hideDccGateway.parentNode.removeChild(hideDccGateway);
        }

        const cardField = paypal.CardFields({
            createOrder: contextConfig.createOrder,
            onApprove: function (data) {
                return contextConfig.onApprove(data);
            },
            onError: function (error) {
                console.error(error)
                this.spinner.unblock();
            }
        });

        if (cardField.isEligible()) {
            const nameField = document.getElementById('ppcp-credit-card-gateway-card-name');
            if (nameField) {
                let styles = cardFieldStyles(nameField);
                let fieldOptions = {
                    style: { 'input': styles }
                }
                if (nameField.getAttribute('placeholder')) {
                    fieldOptions.placeholder = nameField.getAttribute('placeholder');
                }
                cardField.NameField(fieldOptions).render(nameField.parentNode);
                nameField.remove();
            }

            const numberField = document.getElementById('ppcp-credit-card-gateway-card-number');
            if (numberField) {
                let styles = cardFieldStyles(numberField);
                let fieldOptions = {
                    style: { 'input': styles }
                }
                if (numberField.getAttribute('placeholder')) {
                    fieldOptions.placeholder = numberField.getAttribute('placeholder');
                }
                cardField.NumberField(fieldOptions).render(numberField.parentNode);
                numberField.remove();
            }

            const expiryField = document.getElementById('ppcp-credit-card-gateway-card-expiry');
            if (expiryField) {
                let styles = cardFieldStyles(expiryField);
                let fieldOptions = {
                    style: { 'input': styles }
                }
                if (expiryField.getAttribute('placeholder')) {
                    fieldOptions.placeholder = expiryField.getAttribute('placeholder');
                }
                cardField.ExpiryField(fieldOptions).render(expiryField.parentNode);
                expiryField.remove();
            }

            const cvvField = document.getElementById('ppcp-credit-card-gateway-card-cvc');
            if (cvvField) {
                let styles = cardFieldStyles(cvvField);
                let fieldOptions = {
                    style: { 'input': styles }
                }
                if (cvvField.getAttribute('placeholder')) {
                    fieldOptions.placeholder = cvvField.getAttribute('placeholder');
                }
                cardField.CVVField(fieldOptions).render(cvvField.parentNode);
                cvvField.remove();
            }

            document.dispatchEvent(new CustomEvent("hosted_fields_loaded"));
        }

        gateWayBox.style.display = oldDisplayStyle;

        show(buttonSelector);

        if(this.defaultConfig.cart_contains_subscription) {
            const saveToAccount = document.querySelector('#wc-ppcp-credit-card-gateway-new-payment-method');
            if(saveToAccount) {
                saveToAccount.checked = true;
                saveToAccount.disabled = true;
            }
        }

        document.querySelector(buttonSelector).addEventListener("click", (event) => {
            event.preventDefault();
            this.spinner.block();
            this.errorHandler.clear();

            const paymentToken = document.querySelector('input[name="wc-ppcp-credit-card-gateway-payment-token"]:checked')?.value
            if(paymentToken && paymentToken !== 'new') {
                document.querySelector('#place_order').click();
                return;
            }

            if (typeof this.onCardFieldsBeforeSubmit === 'function' && !this.onCardFieldsBeforeSubmit()) {
                this.spinner.unblock();
                return;
            }

            cardField.submit()
                .catch((error) => {
                    this.spinner.unblock();
                    console.error(error)
                    this.errorHandler.message(this.defaultConfig.hosted_fields.labels.fields_not_valid);
                });
        });
    }

    disableFields() {}
    enableFields() {}
}

export default CardFieldsRenderer;
