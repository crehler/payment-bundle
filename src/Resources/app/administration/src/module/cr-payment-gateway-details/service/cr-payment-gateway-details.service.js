const ApiService = Shopware.Classes.ApiService;

/**
 * Reads provider-agnostic gateway transaction details for an order from the bundle's
 * admin endpoint. The endpoint returns the list of order transactions handled by a
 * Crehler provider plus the details for one (the requested transaction, or the newest
 * by default).
 */
class CrPaymentGatewayDetailsService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/crehler-payment') {
        super(httpClient, loginService, apiEndpoint);
    }

    getDetails(orderId, orderTransactionId = null) {
        let route = `${this.getApiBasePath()}/order/${orderId}/gateway-details`;
        if (orderTransactionId) {
            route += `?transactionId=${encodeURIComponent(orderTransactionId)}`;
        }

        return this.httpClient
            .get(route, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }
}

export default CrPaymentGatewayDetailsService;
