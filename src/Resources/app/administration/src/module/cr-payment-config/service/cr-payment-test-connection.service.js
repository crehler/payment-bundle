const ApiService = Shopware.Classes.ApiService;

/**
 * Talks to the shared bundle endpoint POST /api/_action/crehler-payment/test-connection.
 * Credentials travel in the POST body (never the URL/query) so they don't leak into
 * access logs. One service for every provider plugin.
 */
class CrPaymentTestConnectionService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/crehler-payment') {
        super(httpClient, loginService, apiEndpoint);
    }

    testConnection({ configDomain, environment, config, salesChannelId }) {
        const route = `${this.getApiBasePath()}/test-connection`;

        return this.httpClient
            .post(
                route,
                { configDomain, environment, config, salesChannelId },
                { headers: this.getBasicHeaders() },
            )
            .then((response) => ApiService.handleResponse(response));
    }
}

export default CrPaymentTestConnectionService;
