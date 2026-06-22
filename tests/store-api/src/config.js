import { config } from 'dotenv';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
config({ path: resolve(__dirname, '../.env') });

/**
 * Test configuration loaded from environment variables.
 *
 * Prerequisites:
 * - Test customer must exist and have a valid billing/shipping address
 * - Test product must exist and be available in the sales channel
 * - Payment methods must be configured and active
 */
export const testConfig = {
    /** Shopware Store URL (without trailing slash) */
    storeUrl: process.env.STORE_URL,

    /** Sales Channel Access Key */
    accessKey: process.env.SW_ACCESS_KEY,

    /** Test product number to use for orders */
    productNumber: process.env.TEST_PRODUCT_NUMBER,

    /** Test customer email */
    customerEmail: process.env.TEST_CUSTOMER_EMAIL,

    /** Test customer password */
    customerPassword: process.env.TEST_CUSTOMER_PASSWORD,

    /** BLIK test code */
    blikCode: process.env.BLIK_TEST_CODE || '777123',
};

/**
 * Validate that all required configuration is present.
 */
export function validateConfig() {
    const required = [
        'storeUrl',
        'accessKey',
        'productNumber',
        'customerEmail',
        'customerPassword',
    ];

    const missing = required.filter((key) => !testConfig[key]);

    if (missing.length > 0) {
        throw new Error(
            `Missing required configuration: ${missing.join(', ')}\n` +
                'Please copy .env.example to .env and fill in your values.'
        );
    }

    return testConfig;
}
