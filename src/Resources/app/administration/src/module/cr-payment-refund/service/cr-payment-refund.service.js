const ApiService = Shopware.Classes.ApiService;

/**
 * Thin wrapper over the native Shopware refund action endpoint
 * (POST /api/_action/order_transaction_capture_refund/{refundId}).
 *
 * Refund initiation follows "variant A": the UI creates the refund entity (state OPEN,
 * incl. positions) through the DAL repository, then calls this endpoint to execute it.
 * All gateway logic runs in the bundle's AbstractPaymentMethodHandler::refund().
 */
class CrPaymentRefundService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/order_transaction_capture_refund') {
        super(httpClient, loginService, apiEndpoint);
    }

    process(refundId) {
        const apiRoute = `${this.getApiBasePath()}/${refundId}`;

        return this.httpClient
            .post(apiRoute, {}, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    /**
     * Whether the order's payment provider can process refunds (it implements a
     * RefundProviderPort). Gateways without one return false so the UI can hide the
     * refunds tab instead of letting a refund fail with "unknown refund handler".
     */
    isRefundSupported(orderId) {
        const route = `_action/crehler-payment/order/${orderId}/refund-supported`;

        return this.httpClient
            .get(route, { headers: this.getBasicHeaders() })
            .then((response) => Boolean(ApiService.handleResponse(response)?.supported));
    }

    /**
     * Predefined refund reasons the order's provider exposes (e.g. PayNow's enum). Returns
     * { required, reasons: [{ code, label }] }; an empty list means the modal shows only
     * the free-text note field.
     */
    getRefundReasons(orderId) {
        const route = `_action/crehler-payment/order/${orderId}/refund-reasons`;

        return this.httpClient
            .get(route, { headers: this.getBasicHeaders() })
            .then((response) => {
                const data = ApiService.handleResponse(response) ?? {};

                return {
                    required: Boolean(data.required),
                    reasons: Array.isArray(data.reasons) ? data.reasons : [],
                };
            });
    }
}

export default CrPaymentRefundService;
