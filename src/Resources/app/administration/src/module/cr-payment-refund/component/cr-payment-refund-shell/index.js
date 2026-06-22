import template from './cr-payment-refund-shell.html.twig';
import './cr-payment-refund-shell.scss';

const { Component } = Shopware;

/**
 * Reusable refund card chrome: rounded tile with a header (refund icon + title),
 * a default slot for the body, and the CREHLER partner trailer at the bottom.
 * Only the body content differs between use cases (refund summary, no-capture
 * empty state, …). An optional `#actions` slot renders header-right controls.
 */
Component.register('cr-payment-refund-shell', {
    template,

    props: {
        title: {
            type: String,
            required: true,
        },
    },
});
