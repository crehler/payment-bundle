import template from './cr-payment-integration-trailer.html.twig';
import './cr-payment-integration-trailer.scss';

const { Component } = Shopware;

/**
 * Standalone CREHLER "payment integration" trailer, injected at the top of every
 * Crehler payment plugin's configuration form via ConfigurationServiceDecorator
 * (Shopware "advanced custom input field" — a config element with a componentName).
 *
 * Display-only: it carries no config value, so it ignores the props the
 * sw-form-field-renderer passes to regular fields.
 */
Component.register('cr-payment-integration-trailer', {
    template,

    inheritAttrs: false,
});
