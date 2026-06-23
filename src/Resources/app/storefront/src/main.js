const PluginManager = window.PluginManager;

// webpackChunkName nadaje split-chunkom deterministyczne, czyste nazwy — bez tego
// standalone build (shopware-cli) nazywa je absolutną ścieżką dysku, co psuje
// reprodukowalność i wystawia ścieżkę dewelopera w publicznym artefakcie.
PluginManager.register(
    'BlikCodeFormatter',
    () => import(/* webpackChunkName: "cr-blik-code-formatter" */ './cr-blik-code-formatter/blik-code-formatter'),
    '[data-blik-code-formatter]'
);

PluginManager.register(
    'BlikPayment',
    () => import(/* webpackChunkName: "cr-blik-payment" */ './cr-blik-payment/blik-payment'),
    '[data-blik-payment]'
);

PluginManager.register(
    'CrehlerBankSelectorPlugin',
    () => import(/* webpackChunkName: "cr-bank-selector" */ './cr-bank-selector/bank-selector.plugin'),
    '[data-crehler-payment-bank-selector]'
);

PluginManager.register(
    'CrehlerCheckPayment',
    () => import(/* webpackChunkName: "cr-check-payment-status" */ './cr-check-payment-status/check-payment-status'),
    '[data-crehler-check-payment]'
);

PluginManager.register(
    'CrSavedCardSelector',
    () => import(/* webpackChunkName: "cr-saved-card-selector" */ './cr-saved-card-selector/saved-card-selector.plugin'),
    '[data-saved-card-selector]'
);
