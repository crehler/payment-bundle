import { config as loadEnv } from 'dotenv';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
loadEnv({ path: resolve(__dirname, '../.env') });

export const config = {
  storeUrl: process.env.STORE_URL,
  productNumber: process.env.TEST_PRODUCT_NUMBER,
  customerEmail: process.env.TEST_CUSTOMER_EMAIL,
  customerPassword: process.env.TEST_CUSTOMER_PASSWORD,
  blikCode: process.env.BLIK_TEST_CODE || '777123'
};

export function validateConfig() {
  const required = ['storeUrl', 'productNumber', 'customerEmail', 'customerPassword'];
  const missing = required.filter((key) => !config[key]);

  if (missing.length > 0) {
    throw new Error(
      `Missing configuration: ${missing.join(', ')}\n` +
      'Copy .env.example to .env and fill in your values.'
    );
  }

  return config;
}

// Payment method selectors using data attributes from CrehlerPaymentBundle
export const paymentSelectors = {
  // CrehlerPaymentBundle payment methods (have data-cr-* attributes)
  crehler: '[data-cr-payment="true"]',
  bank: '[data-cr-bank="true"]',
  blik: '[data-cr-blik="true"]',
  card: '[data-cr-card="true"]',
  ewallet: '[data-cr-ewallet="true"]',
  deferred: '[data-cr-deferred="true"]',
  hasSubmethods: '[data-cr-has-submethods="true"]',

  // Any payment method (for fallback)
  any: '.payment-method',

  // Non-Crehler payment methods (no data-cr-payment attribute)
  nonCrehler: '.payment-method:not([data-cr-payment])'
};
