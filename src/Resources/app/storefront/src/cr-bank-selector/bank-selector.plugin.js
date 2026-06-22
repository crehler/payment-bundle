import Plugin from 'src/plugin-system/plugin.class';

export default class CrehlerBankSelectorPlugin extends Plugin {
    static options = {
        activeClass: 'payment-sub-methods--has-value',
        optionsSelector: '.payment-sub-methods-options',
        selectedSelector: '.payment-sub-methods-selected',
        changeButtonSelector: '.payment-sub-methods-selected-change',
        changeButtonSelectorActiveClass: 'payment-sub-methods-selected-change--active',
        selectedClass: 'is-selected',
        submethodSelector: '.payment-sub-method',
        inputSelector: '.payment-sub-method-input'
    };

    init() {
        this.optionElement = this.el.querySelector(this.options.optionsSelector);
        this.selectedElement = this.el.querySelector(this.options.selectedSelector);
        this.changeButton = this.el.querySelector(this.options.changeButtonSelector);
        this.submethods = this.el.querySelectorAll(this.options.submethodSelector);
        this.inputs = this.el.querySelectorAll(this.options.inputSelector);

        this._registerEvents();
    }

    _registerEvents() {
        if (this.changeButton) {
            this.changeButton.addEventListener('click', this._onChangeButtonClick.bind(this));
        }

        this.inputs.forEach(input => {
            input.addEventListener('change', this._onInputChange.bind(this));
        });

        // Add click handler to submethod cards for better UX
        this.submethods.forEach(submethod => {
            submethod.addEventListener('click', this._onSubmethodClick.bind(this));
        });
    }

    _onChangeButtonClick(event) {
        event.preventDefault();

        if (this.hasSelectedBank()) {
            this.showBankList();
        } else {
            this.hideBankList();
        }
    }

    _onSubmethodClick(event) {
        const submethod = event.currentTarget;
        const input = submethod.querySelector(this.options.inputSelector);

        if (input && !input.checked) {
            input.checked = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    _onInputChange(event) {
        const selectedInput = event.target;
        const selectedSubmethod = selectedInput.closest(this.options.submethodSelector);

        // Remove selected class from all submethods
        this.submethods.forEach(submethod => {
            submethod.classList.remove(this.options.selectedClass);
        });

        // Add selected class to the clicked submethod
        if (selectedSubmethod) {
            selectedSubmethod.classList.add(this.options.selectedClass);

            // Trigger pulse animation by removing and re-adding class
            selectedSubmethod.style.animation = 'none';
            selectedSubmethod.offsetHeight; // Force reflow
            selectedSubmethod.style.animation = null;
        }
    }

    hasSelectedBank() {
        return this.el.classList.contains(this.options.activeClass);
    }

    showBankList() {
        this.el.classList.remove(this.options.activeClass);

        // Scroll to options if needed
        if (this.optionElement) {
            setTimeout(() => {
                this.optionElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });
            }, 100);
        }
    }

    hideBankList() {
        this.el.classList.add(this.options.activeClass);
    }
}
