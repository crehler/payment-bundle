import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';

export default class BlikPayment extends Plugin {
    static options = {
        blikInputSelector: '#crehlerBlikCode',
        checkoutConfirmFormSelector: '#confirmOrderForm',
        orderButtonSelector: '#confirmFormSubmit',
        errorContainerSelector: '.blik-error-container',
        url: '',
        accessKey: '',
        loadingClass: 'is-loading',
        errorMessages: {
            invalidCode: 'Kod BLIK musi mieć 6 cyfr',
            processingError: 'Wystąpił błąd podczas przetwarzania płatności BLIK',
            networkError: 'Błąd połączenia. Spróbuj ponownie.'
        }
    };

    init() {
        this._blikInput = document.querySelector(this.options.blikInputSelector);
        this._checkoutForm = document.querySelector(this.options.checkoutConfirmFormSelector);
        this._orderButton = document.querySelector(this.options.orderButtonSelector);
        this._client = new HttpClient();
        this._isProcessing = false;
        this._originalButtonText = null;

        const blikWrapper = document.querySelector('[data-cr-blik]');
        const blikRadio = blikWrapper ? blikWrapper.querySelector('input[name="paymentMethodId"]') : null;
        this._blikMethodId = blikRadio ? blikRadio.value : null;

        if (!this._blikInput || !this._checkoutForm || !this._orderButton) {
            return;
        }

        this._createErrorContainer();
        this._registerEvents();
    }

    _createErrorContainer() {
        let container = this._blikInput.parentElement.querySelector(this.options.errorContainerSelector);
        if (!container) {
            container = document.createElement('div');
            container.className = 'blik-error-container alert alert-danger mt-2';
            container.style.display = 'none';
            this._blikInput.parentElement.appendChild(container);
        }
        this._errorContainer = container;
    }

    _registerEvents() {
        this._checkoutForm.addEventListener('submit', this._onFormSubmit.bind(this), true);
        this._orderButton.addEventListener('click', this._onButtonClick.bind(this));
        this._blikInput.addEventListener('input', this._onBlikInputChange.bind(this));
    }

    _onBlikInputChange() {
        this._hideError();
        this._blikInput.classList.remove('is-invalid');
    }

    _onButtonClick(event) {
        if (this._isBlikSelected() && !this._isProcessing) {
            event.preventDefault();
            this._processBlikPayment();
        }
    }

    _onFormSubmit(event) {
        if (this._isBlikSelected() && !this._isProcessing) {
            event.preventDefault();
            event.stopPropagation();
            this._processBlikPayment();
            return false;
        }
    }

    _isBlikSelected() {
        if (!this._blikMethodId) {
            return false;
        }

        const selected = document.querySelector('input[name="paymentMethodId"]:checked');
        return selected && selected.value === this._blikMethodId;
    }

    _processBlikPayment() {
        const blikCode = this._blikInput.value;

        this._hideError();

        if (!this._validateBlikCode(blikCode)) {
            this._showError(this.options.errorMessages.invalidCode);
            this._blikInput.classList.add('is-invalid');
            this._blikInput.focus();
            return;
        }

        const selectedPaymentMethod = document.querySelector('input[name="paymentMethodId"]:checked');

        this._setLoadingState(true);

        this._client.post(
            this.options.url,
            JSON.stringify({ blikCode: blikCode, paymentMethodId: selectedPaymentMethod.value }),
            this._handleBlikResponse.bind(this),
        );
    }

    _setLoadingState(isLoading) {
        this._isProcessing = isLoading;

        if (isLoading) {
            this._originalButtonText = this._orderButton.innerHTML;
            this._orderButton.disabled = true;
            this._orderButton.classList.add(this.options.loadingClass);
            this._orderButton.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Przetwarzanie...
            `;
            this._blikInput.disabled = true;
        } else {
            this._orderButton.disabled = false;
            this._orderButton.classList.remove(this.options.loadingClass);
            if (this._originalButtonText) {
                this._orderButton.innerHTML = this._originalButtonText;
            }
            this._blikInput.disabled = false;
        }
    }

    _handleBlikResponse(response) {
        try {
            const result = JSON.parse(response);

            if (result.redirect === true && result.location != null) {
                window.location.replace(result.location);
                return;
            }

            this._setLoadingState(false);
            this._showError(result.message || this.options.errorMessages.processingError);
        } catch (error) {
            this._setLoadingState(false);
            this._showError(this.options.errorMessages.networkError);
        }
    }

    _showError(message) {
        if (this._errorContainer) {
            this._errorContainer.textContent = message;
            this._errorContainer.style.display = 'block';
        }
    }

    _hideError() {
        if (this._errorContainer) {
            this._errorContainer.style.display = 'none';
            this._errorContainer.textContent = '';
        }
    }

    _validateBlikCode(blikCode) {
        const cleanCode = blikCode.replace(/\s/g, '');
        return /^\d{6}$/.test(cleanCode);
    }
}