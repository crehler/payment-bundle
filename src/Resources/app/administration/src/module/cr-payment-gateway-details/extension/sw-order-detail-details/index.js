import template from './sw-order-detail-details.html.twig';

/**
 * Append the gateway payment-details section to the order "Szczegóły" tab's
 * "Metoda płatności" card, right after the native payment-method select. Existing
 * fields are untouched (we only extend the block and call {% parent %}).
 */
Shopware.Component.override('sw-order-detail-details', {
    template,
});
