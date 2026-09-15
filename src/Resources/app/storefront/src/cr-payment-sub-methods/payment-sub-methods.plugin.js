import Plugin from 'src/plugin-system/plugin.class';

/**
 * Loads the channel widget (banks, wallets, BNPL providers) for the selected payment
 * method after the checkout has painted.
 *
 * The list used to be rendered server-side during SSR, which put the gateway on the
 * critical path of every checkout render — four identical calls at ING, one per family,
 * synchronously. This asks the storefront controller instead, and the controller returns
 * the same Twig partial the three count branches always lived in, so there is exactly one
 * renderer for "no channels / one channel / a chooser".
 *
 * The container only exists under the currently selected method, so init() loading it
 * covers both cases: arriving at checkout with a method preselected, and switching to one
 * (Shopware's own FormAutoSubmitPlugin reloads the page on a payment METHOD change, and
 * this runs again on the new render).
 *
 * Changing the CHANNEL does not reload anything — see _select(). Only this container's
 * markup depends on it.
 */
export default class CrPaymentSubMethodsPlugin extends Plugin {
    static options = {
        /** Storefront route rendering the widget. */
        url: null,

        /** Storefront route that records the chosen channel and renders the widget back. */
        selectUrl: null,

        /** The method's own radio, disabled when the gateway offers no channel. */
        paymentMethodInputSelector: null,

        /**
         * Whether the gateway refuses the payment without a channel picked here. Comes
         * from the handler's contract: Tpay and PayU show their own bank list when nothing
         * was chosen, ING does not.
         */
        required: false,

        loaderSelector: '.cr-payment-sub-methods-loader',
        busyClass: 'cr-payment-sub-methods--busy',
        unavailableClass: 'cr-payment-sub-methods--unavailable',
        submitSelector: '#confirmFormSubmit',
        subMethodInputSelector: 'input[name="paymentSubMethod"]',
    };

    init() {
        if (!this.options.url) {
            return;
        }

        this._load();
    }

    async _load() {
        try {
            this._render(await this._request(this.options.url));
        } catch (error) {
            console.error('[CrPaymentSubMethods]', error);
            this._onLoadFailed();
        }
    }

    /**
     * Record the chosen channel and swap in the widget the server rendered back.
     *
     * Choosing a bank used to submit Shopware's changePaymentForm and reload the whole
     * confirm page. Nothing outside this container depends on which channel is selected —
     * not the cart, not the totals, not the other payment methods — so the page reload was
     * paying for a full render to move one radio.
     *
     * The response is the same partial as the initial load, so the collapsed
     * "selected channel" panel comes from the server and there is still exactly one
     * renderer for this markup.
     */
    async _select(providerId) {
        if (!this.options.selectUrl) {
            return;
        }

        const body = new FormData();
        body.append('paymentSubMethod', providerId);

        this.el.classList.add(this.options.busyClass);

        try {
            this._render(await this._request(this.options.selectUrl, { method: 'POST', body }));
        } catch (error) {
            // The channel stays visually selected but was not recorded, so re-reading from
            // the server is the honest recovery: it shows what the shop actually stored.
            console.error('[CrPaymentSubMethods]', error);
            await this._load();
        } finally {
            this.el.classList.remove(this.options.busyClass);
        }
    }

    async _request(url, init = {}) {
        const response = await fetch(url, {
            ...init,
            headers: { 'X-Requested-With': 'XMLHttpRequest', ...(init.headers || {}) },
        });

        if (!response.ok) {
            throw new Error(`Channel widget responded ${response.status} for ${url}`);
        }

        return response.text();
    }

    _render(html) {
        this.el.innerHTML = html;

        // The chooser branch carries its own plugin (the bank selector), and the markup
        // arrived after the plugin manager had already swept the DOM.
        window.PluginManager.initializePlugins();

        this._registerEvents();

        const hasChannels = this.el.querySelector(this.options.subMethodInputSelector) !== null;

        if (!hasChannels) {
            this._markUnavailable();

            return;
        }

        this._syncSubmitState();
    }

    /**
     * A failed request is not the same fact as "the gateway offers nothing", so the method
     * is not declared dead over a network hiccup. But a gateway that will not pick a
     * channel for the customer cannot be ordered from without one either, so that case
     * fails closed.
     */
    _onLoadFailed() {
        const loader = this.el.querySelector(this.options.loaderSelector);

        if (loader) {
            loader.remove();
        }

        if (this.options.required) {
            this._markUnavailable();
        }
    }

    _registerEvents() {
        this.el.querySelectorAll(this.options.subMethodInputSelector).forEach(input => {
            input.addEventListener('change', event => {
                this._syncSubmitState();

                if (event.target.checked) {
                    this._select(event.target.value);
                }
            });
        });
    }

    /**
     * Belt to the server-side braces: the order button stays disabled until a required
     * channel is picked, so a customer never gets walked into a payment the gateway will
     * reject. AbstractPaymentMethodHandler::pay() rejects it as well, with a message —
     * this only saves the round trip.
     */
    _syncSubmitState() {
        if (!this.options.required) {
            return;
        }

        this._setSubmitDisabled(this.el.querySelector(`${this.options.subMethodInputSelector}:checked`) === null
            && this.el.querySelector('input[name="paymentSubMethod"][type="hidden"]') === null);
    }

    _markUnavailable() {
        this.el.classList.add(this.options.unavailableClass);

        const radio = this.options.paymentMethodInputSelector
            ? document.querySelector(this.options.paymentMethodInputSelector)
            : null;

        if (radio) {
            radio.disabled = true;
        }

        // The method is the selected one — that is the only method this widget renders
        // under — so with nothing payable the order button has to go too.
        this._setSubmitDisabled(true);
    }

    _setSubmitDisabled(disabled) {
        document.querySelectorAll(this.options.submitSelector).forEach(button => {
            button.disabled = disabled;
        });
    }
}
