import template from './cr-payment-test-connection.html.twig';
import './cr-payment-test-connection.scss';
import '../../mixin/cr-system-config-reader.mixin';

const { Component, Mixin } = Shopware;

/**
 * Shared "test connection" button used by every provider plugin's config.xml via
 * <component name="cr-payment-test-connection">. One look, one spinner, one
 * notification path. The field name (e.g. "CrehlerTpay.config.sandboxCheckCredentials")
 * tells the component its plugin domain and which environment (live/sandbox) to probe;
 * it posts the unsaved credentials of that set to the shared bundle endpoint.
 */
Component.register('cr-payment-test-connection', {
    template,

    inheritAttrs: false,

    inject: ['CrPaymentTestConnectionService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('cr-system-config-reader'),
    ],

    props: {
        // Bound by sw-form-field-renderer from the config.xml element name.
        name: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            isLoading: false,
        };
    },

    computed: {
        fieldKey() {
            const idx = this.name.lastIndexOf('.');
            return idx === -1 ? this.name : this.name.slice(idx + 1);
        },

        configDomain() {
            const idx = this.name.lastIndexOf('.');
            return idx === -1 ? this.name : this.name.slice(0, idx);
        },

        // "...sandboxCheckCredentials" → sandbox, otherwise the production set.
        environment() {
            return this.fieldKey.toLowerCase().includes('sandbox') ? 'sandbox' : 'live';
        },

        buttonLabel() {
            return this.environment === 'sandbox'
                ? this.$tc('cr-payment-config.test.buttonSandbox')
                : this.$tc('cr-payment-config.test.buttonLive');
        },
    },

    methods: {
        async onTest() {
            this.isLoading = true;

            try {
                const { config, salesChannelId } = this.crReadConfigSlice(this.configDomain);

                const response = await this.CrPaymentTestConnectionService.testConnection({
                    configDomain: this.configDomain,
                    environment: this.environment,
                    config,
                    salesChannelId,
                });

                this.createNotificationSuccess({
                    message: response?.message || this.$tc('cr-payment-config.test.success'),
                });
            } catch (error) {
                this.createNotificationError({
                    message: error?.response?.data?.message || this.$tc('cr-payment-config.test.error'),
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
