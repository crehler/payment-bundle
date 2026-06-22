const PluginManager = window.PluginManager;

PluginManager.register(
    'BlikCodeFormatter',
    () => import('./cr-blik-code-formatter/blik-code-formatter'),
    '[data-blik-code-formatter]'
);

PluginManager.register(
    'BlikPayment',
    () => import('./cr-blik-payment/blik-payment'),
    '[data-blik-payment]'
);

PluginManager.register(
    'CrehlerBankSelectorPlugin',
    () => import('./cr-bank-selector/bank-selector.plugin'),
    '[data-crehler-payment-bank-selector]'
);

PluginManager.register(
    'CrehlerCheckPayment',
    () => import('./cr-check-payment-status/check-payment-status'),
    '[data-crehler-check-payment]'
);

PluginManager.register(
    'CrSavedCardSelector',
    () => import('./cr-saved-card-selector/saved-card-selector.plugin'),
    '[data-saved-card-selector]'
);