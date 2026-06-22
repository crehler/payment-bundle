import CrPaymentRefundService from './module/cr-payment-refund/service/cr-payment-refund.service';
import CrPaymentGatewayDetailsService from './module/cr-payment-gateway-details/service/cr-payment-gateway-details.service';
import CrPaymentTestConnectionService from './module/cr-payment-config/service/cr-payment-test-connection.service';
import './module/cr-payment-refund';
import './module/cr-payment-gateway-details';
import './module/cr-payment-config';
import './component/cr-payment-integration-trailer';

import enGBSnippets from './translations/cr-payment-refund/en-GB.json';
import plPLSnippets from './translations/cr-payment-refund/pl-PL.json';
import gatewayEnGBSnippets from './translations/cr-payment-gateway-details/en-GB.json';
import gatewayPlPLSnippets from './translations/cr-payment-gateway-details/pl-PL.json';
import configEnGBSnippets from './translations/cr-payment-config/en-GB.json';
import configPlPLSnippets from './translations/cr-payment-config/pl-PL.json';

Shopware.Locale.extend('en-GB', enGBSnippets);
Shopware.Locale.extend('pl-PL', plPLSnippets);
Shopware.Locale.extend('en-GB', gatewayEnGBSnippets);
Shopware.Locale.extend('pl-PL', gatewayPlPLSnippets);
Shopware.Locale.extend('en-GB', configEnGBSnippets);
Shopware.Locale.extend('pl-PL', configPlPLSnippets);

const { Application } = Shopware;

Application.addServiceProvider('CrPaymentRefundService', (container) => {
    const initContainer = Application.getContainer('init');
    return new CrPaymentRefundService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('CrPaymentGatewayDetailsService', (container) => {
    const initContainer = Application.getContainer('init');
    return new CrPaymentGatewayDetailsService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('CrPaymentTestConnectionService', (container) => {
    const initContainer = Application.getContainer('init');
    return new CrPaymentTestConnectionService(initContainer.httpClient, container.loginService);
});
