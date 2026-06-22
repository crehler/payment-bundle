/**
 * Shopware Store API Client for Payment Testing
 */
export class StoreApiClient {
    #baseUrl;
    #accessKey;
    #contextToken;

    /**
     * @param {string} baseUrl - Store URL without trailing slash
     * @param {string} accessKey - Sales channel access key
     */
    constructor(baseUrl, accessKey) {
        this.#baseUrl = baseUrl;
        this.#accessKey = accessKey;
        this.#contextToken = '';
    }

    get contextToken() {
        return this.#contextToken;
    }

    get baseUrl() {
        return this.#baseUrl;
    }

    /**
     * Reset context token (useful for testing guest scenarios).
     */
    resetContext() {
        this.#contextToken = '';
    }

    /**
     * Make a request to the Store API.
     */
    async #request(method, endpoint, body = null) {
        const url = `${this.#baseUrl}/store-api${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'sw-access-key': this.#accessKey,
        };

        if (this.#contextToken) {
            headers['sw-context-token'] = this.#contextToken;
        }

        const options = {
            method,
            headers,
        };

        if (body && (method === 'POST' || method === 'PATCH' || method === 'PUT' || method === 'DELETE')) {
            options.body = JSON.stringify(body);
        }

        const response = await fetch(url, options);

        // Update context token from response if present
        const newContextToken = response.headers.get('sw-context-token');
        if (newContextToken) {
            this.#contextToken = newContextToken;
        }

        if (!response.ok) {
            const errorBody = await response.text();
            throw new Error(
                `Store API Error ${response.status}: ${response.statusText}\n${errorBody}`
            );
        }

        const text = await response.text();
        return text ? JSON.parse(text) : null;
    }

    /**
     * Make a raw request that returns status code and body (doesn't throw on error).
     * Useful for testing error scenarios.
     */
    async rawRequest(method, endpoint, body = null) {
        const url = `${this.#baseUrl}/store-api${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'sw-access-key': this.#accessKey,
        };

        if (this.#contextToken) {
            headers['sw-context-token'] = this.#contextToken;
        }

        const options = { method, headers };

        if (body && (method === 'POST' || method === 'PATCH' || method === 'PUT' || method === 'DELETE')) {
            options.body = JSON.stringify(body);
        }

        const response = await fetch(url, options);
        const newContextToken = response.headers.get('sw-context-token');
        if (newContextToken) {
            this.#contextToken = newContextToken;
        }

        const text = await response.text();
        let data = null;
        try {
            data = text ? JSON.parse(text) : null;
        } catch {
            data = text;
        }

        return {
            status: response.status,
            ok: response.ok,
            data,
        };
    }

    // ==================== Authentication ====================

    /**
     * Log in a customer.
     * @see https://shopware.stoplight.io/docs/store-api/61246d1020594-log-in-a-customer
     */
    async login(email, password) {
        const response = await this.#request('POST', '/account/login', {
            email,
            password,
        });

        if (response?.contextToken) {
            this.#contextToken = response.contextToken;
        }

        return response;
    }

    /**
     * Log out the current customer.
     */
    async logout() {
        return this.#request('POST', '/account/logout');
    }

    /**
     * Get current customer context.
     */
    async getContext() {
        return this.#request('GET', '/context');
    }

    // ==================== Products ====================

    /**
     * Search for products.
     * @see https://shopware.stoplight.io/docs/store-api/7391176b295ee-search-for-products
     */
    async searchProducts(criteria = {}) {
        return this.#request('POST', '/search', criteria);
    }

    /**
     * Find a product by its product number.
     */
    async findProductByNumber(productNumber) {
        const response = await this.searchProducts({
            filter: [
                {
                    type: 'equals',
                    field: 'productNumber',
                    value: productNumber,
                },
            ],
            limit: 1,
        });

        if (!response?.elements?.length) {
            throw new Error(`Product with number "${productNumber}" not found`);
        }

        return response.elements[0];
    }

    // ==================== Cart ====================

    /**
     * Get current cart.
     */
    async getCart() {
        return this.#request('GET', '/checkout/cart');
    }

    /**
     * Add a product to the cart.
     */
    async addToCart(productId, quantity = 1) {
        return this.#request('POST', '/checkout/cart/line-item', {
            items: [
                {
                    type: 'product',
                    referencedId: productId,
                    quantity,
                },
            ],
        });
    }

    /**
     * Clear the cart (remove all items).
     */
    async clearCart() {
        const cart = await this.getCart();

        if (cart?.lineItems?.length > 0) {
            const ids = cart.lineItems.map((item) => item.id);
            await this.#request('DELETE', '/checkout/cart/line-item', { ids });
        }

        return this.getCart();
    }

    // ==================== Payment Methods ====================

    /**
     * Get available payment methods.
     * Uses POST to include media association.
     */
    async getPaymentMethods(onlyAvailable = true) {
        return this.#request('POST', '/payment-method', {
            onlyAvailable: onlyAvailable ? 1 : 0,
            associations: {
                media: {},
            },
        });
    }

    /**
     * Helper: Classify payment method by technicalName.
     * Returns type info based on technical name pattern.
     */
    static classifyPaymentMethod(paymentMethod) {
        const technicalName = paymentMethod.technicalName?.toLowerCase() || '';

        return {
            isBlik: technicalName.includes('blik'),
            isBank: technicalName.includes('bank') || technicalName === 'payu_bank',
            isCard: technicalName.includes('card'),
            isEwallet: technicalName.includes('ewallet'),
            isDeferred: technicalName.includes('deferred') || technicalName.includes('odroczon'),
            hasSubmethods: technicalName.includes('bank') || technicalName.includes('ewallet') || technicalName.includes('deferred'),
            isCrehlerPayment: technicalName.startsWith('payu_') || technicalName.startsWith('paynow_'),
        };
    }

    /**
     * Update context (e.g., change payment method).
     */
    async updateContext(data) {
        return this.#request('PATCH', '/context', data);
    }

    /**
     * Set the payment method for the current context.
     */
    async setPaymentMethod(paymentMethodId) {
        return this.updateContext({ paymentMethodId });
    }

    // ==================== Checkout ====================

    /**
     * Create an order from the current cart.
     */
    async createOrder() {
        return this.#request('POST', '/checkout/order');
    }

    /**
     * Handle payment for an order.
     * This may return a redirect URL to the payment gateway.
     */
    async handlePayment(orderId, finishUrl, errorUrl, paymentDetails = {}) {
        return this.#request('POST', '/handle-payment', {
            orderId,
            finishUrl,
            errorUrl,
            ...paymentDetails,
        });
    }

    /**
     * Set payment sub-method (bank) for the current selection.
     * Done via the standard context switch — same mechanism as the native
     * paymentMethodId. For a logged-in customer this also persists to the account.
     * @see SalesChannelContextSwitchSubscriber
     */
    async setPaymentSubMethod(paymentMethodId, subMethodId) {
        return this.updateContext({ paymentMethodId, paymentSubMethod: subMethodId });
    }

    /**
     * Get payment sub-methods for a payment method.
     * This is specific to CrehlerPaymentBundle.
     * @see PaymentSubMethodRoute
     * @param {string} paymentMethodId - Payment method ID
     * @param {number} paymentValue - Cart value in smallest currency unit (e.g., grosze)
     */
    async getPaymentSubMethods(paymentMethodId, paymentValue = 10000) {
        return this.#request('GET', `/cr/payment-sub-methods/${paymentMethodId}?paymentValue=${paymentValue}`);
    }

    /**
     * Get payment sub-methods for current payment method.
     * @param {number} paymentValue - Cart value in smallest currency unit
     */
    async getCurrentPaymentSubMethods(paymentValue = 10000) {
        return this.#request('GET', `/cr/payment-sub-methods?paymentValue=${paymentValue}`);
    }

    /**
     * Get customer's selected payment sub-method.
     * @see CustomerPaymentSubMethodRoute GET
     */
    async getCustomerSubMethod() {
        return this.#request('GET', '/customer/cr/payment-sub-method');
    }

    // ==================== Payment Status ====================

    /**
     * Check payment status for an order.
     * @see CheckPaymentStatusRoute
     * @param {string} orderId - Order ID
     * @returns {Promise<{status: boolean, waiting: boolean}>}
     */
    async checkPaymentStatus(orderId) {
        return this.#request('POST', '/cr/payment/check', { orderId });
    }

    // ==================== BLIK Payment ====================

    /**
     * Process BLIK payment.
     * @see BlikPaymentRoute
     * @param {string} paymentMethodId - BLIK payment method ID
     * @param {string} blikCode - 6-digit BLIK code
     * @param {object} [options]
     * @param {string} [options.finishUrl] - Optional custom URL for successful payment redirect
     * @param {string} [options.errorUrl] - Optional custom URL for failed payment redirect
     * @returns {Promise<{success: boolean, redirectUrl?: string, error?: string, orderId: string}>}
     */
    async processBlikPayment(paymentMethodId, blikCode, options = {}) {
        const body = {
            paymentMethodId,
            blikCode,
        };

        if (options.finishUrl) {
            body.finishUrl = options.finishUrl;
        }
        if (options.errorUrl) {
            body.errorUrl = options.errorUrl;
        }

        return this.#request('POST', '/cr/payment/blik', body);
    }

    /**
     * Retry BLIK payment for an existing order.
     * @see BlikRetryPaymentRoute
     * @param {string} orderId - Order ID whose payment is being retried
     * @param {string} blikCode - 6-digit BLIK code
     * @param {object} [options]
     * @param {string} [options.finishUrl] - Optional custom URL for successful payment redirect
     * @param {string} [options.errorUrl] - Optional custom URL for failed payment redirect
     * @returns {Promise<{success: boolean, redirectUrl?: string, error?: string, orderId: string}>}
     */
    async retryBlikPayment(orderId, blikCode, options = {}) {
        const body = { blikCode };

        if (options.finishUrl) {
            body.finishUrl = options.finishUrl;
        }
        if (options.errorUrl) {
            body.errorUrl = options.errorUrl;
        }

        return this.#request('POST', `/cr/payment/blik/order/${orderId}`, body);
    }

    // ==================== Saved Cards ====================

    /**
     * Get customer's saved card tokens.
     * @see RouteSavedCardTokenRoute
     * @returns {Promise<{elements: Array}>}
     */
    async getSavedCards() {
        return this.#request('GET', '/cr/payment/card-tokens');
    }

    // ==================== Customer ====================

    /**
     * Get current logged-in customer.
     */
    async getCustomer() {
        return this.#request('POST', '/account/customer', {
            associations: {
                defaultBillingAddress: {},
                defaultShippingAddress: {},
            },
        });
    }
}
