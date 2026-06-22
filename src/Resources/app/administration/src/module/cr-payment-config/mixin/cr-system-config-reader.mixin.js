const { Mixin } = Shopware;

const MAX_PARENT_TRAVERSAL_DEPTH = 25;

/**
 * Reads the CURRENT (possibly unsaved) plugin config values straight from the
 * surrounding sw-system-config instance, so a custom config component can act on
 * what the operator typed before they hit Save.
 *
 * A field declared via <component> in config.xml is rendered through
 * sw-form-field-renderer and only receives its own field props — never the sibling
 * values. So we walk up the $parent chain to the sw-system-config component, which
 * holds actualConfigData[currentSalesChannelId] (full unsaved form data) and the
 * currently selected sales channel. Same approach used by the PayPal/Mollie configs.
 */
Mixin.register('cr-system-config-reader', {
    methods: {
        crFindSystemConfigHost() {
            let parent = this.$parent;
            let depth = 0;

            while (parent && depth < MAX_PARENT_TRAVERSAL_DEPTH) {
                if (parent.actualConfigData !== undefined && parent.currentSalesChannelId !== undefined) {
                    return parent;
                }

                parent = parent.$parent;
                depth += 1;
            }

            return null;
        },

        /**
         * Returns the unsaved config values for one plugin domain (keys stripped of the
         * domain prefix, e.g. "clientId", "sandboxClientId") plus the selected sales
         * channel id. Falls back to empty/null when the host can't be located.
         */
        crReadConfigSlice(configDomain) {
            const host = this.crFindSystemConfigHost();

            if (!host) {
                return { config: {}, salesChannelId: null };
            }

            const salesChannelId = host.currentSalesChannelId ?? null;
            const all = host.actualConfigData?.[salesChannelId] ?? {};
            const prefix = `${configDomain}.`;
            const config = {};

            Object.keys(all).forEach((key) => {
                if (key.startsWith(prefix)) {
                    config[key.slice(prefix.length)] = all[key];
                }
            });

            return { config, salesChannelId };
        },
    },
});
