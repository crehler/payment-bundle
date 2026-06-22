import Plugin from 'src/plugin-system/plugin.class';

/**
 * Shared saved-card dropdown selector.
 *
 * Handles the gateway-agnostic UX every provider repeated: pick a saved card
 * from a dropdown, write its token into a hidden input, render the masked
 * number + brand logo, hide the "save card" toggle and close the dropdown.
 *
 * Gateway-specific tokenization (RSA encryption, fingerprinting, SDK iframes)
 * stays in the provider plugin; it only needs to render the dropdown markup
 * with the data attributes below.
 */
export default class CrSavedCardSelectorPlugin extends Plugin {
    static options = {
        // Clickable saved-card entries inside the dropdown.
        itemSelector: '[data-saved-card-item]',
        // Hidden input that carries the selected card token to the handler.
        tokenInputSelector: '[data-saved-card-token]',
        // Element showing the currently selected card (masked number + brand).
        displaySelector: '[data-saved-card-display]',
        // Placeholder shown when no saved card is selected.
        placeholderSelector: '[data-saved-card-placeholder]',
        // Dropdown toggle, used to close the Bootstrap dropdown after selection.
        toggleSelector: '[data-saved-card-toggle]',
        // Optional wrapper of the "save this card" control, hidden when a saved card is chosen.
        saveCardWrapperSelector: '[data-save-card-wrapper]',
    };

    init() {
        this._tokenInput = this.el.querySelector(this.options.tokenInputSelector);
        this._display = this.el.querySelector(this.options.displaySelector);
        this._placeholder = this.el.querySelector(this.options.placeholderSelector);
        this._toggle = this.el.querySelector(this.options.toggleSelector);
        this._saveCardWrapper = this.el.querySelector(this.options.saveCardWrapperSelector);
        this._items = this.el.querySelectorAll(this.options.itemSelector);

        this._registerEvents();
    }

    _registerEvents() {
        this._items.forEach((item) => {
            item.addEventListener('click', (event) => {
                event.preventDefault();
                this._selectCard(event.currentTarget);
            });
        });
    }

    _selectCard(item) {
        const token = item.dataset.token || '';
        const maskedNumber = item.dataset.maskedCardNumber || '';
        const brandImgUrl = item.dataset.brandImgUrl || '';

        if (this._tokenInput) {
            this._tokenInput.value = token;
        }

        this._renderSelection(token, maskedNumber, brandImgUrl);
        this._toggleSaveCardWrapper(token);
        this._closeDropdown();
    }

    _renderSelection(token, maskedNumber, brandImgUrl) {
        if (!this._display || !this._placeholder) {
            return;
        }

        if (token && maskedNumber) {
            this._display.innerHTML = '';
            if (brandImgUrl) {
                const img = document.createElement('img');
                img.src = brandImgUrl;
                img.alt = '';
                img.className = 'saved-card-brand-icon';
                this._display.appendChild(img);
            }
            const span = document.createElement('span');
            span.textContent = maskedNumber;
            this._display.appendChild(span);

            this._display.style.display = 'flex';
            this._placeholder.style.display = 'none';
        } else {
            this._display.style.display = 'none';
            this._placeholder.style.display = 'inline';
        }
    }

    _toggleSaveCardWrapper(token) {
        if (this._saveCardWrapper) {
            this._saveCardWrapper.style.display = token ? 'none' : '';
        }
    }

    _closeDropdown() {
        const bootstrap = window.bootstrap;
        if (this._toggle && bootstrap && bootstrap.Dropdown) {
            const dropdown = bootstrap.Dropdown.getInstance(this._toggle);
            if (dropdown) {
                dropdown.hide();
            }
        }
    }
}
