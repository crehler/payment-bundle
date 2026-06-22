import { test, expect } from '@playwright/test';
import { validateConfig, paymentSelectors } from '../config.js';
import {
  login,
  addProductToCart,
  goToCheckout,
  selectPaymentMethod,
  getSubmethods,
  selectSubmethod,
  getSelectedSubmethodName
} from '../utils/storefront.js';

const config = validateConfig();

test.describe('Payment Submethod Selection', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, config);
    await addProductToCart(page, config.productNumber);
    await goToCheckout(page);
  });

  test('displays submethods for bank transfer payment', async ({ page }) => {
    const bankMethod = await selectPaymentMethod(page, paymentSelectors.bank);
    test.skip(!bankMethod, 'No bank payment method configured');

    const submethods = await getSubmethods(page);
    expect(submethods.length).toBeGreaterThan(0);

    // Each submethod should have a name
    for (const sub of submethods) {
      expect(sub.name).toBeTruthy();
    }
  });

  test('allows selecting a submethod', async ({ page }) => {
    const bankMethod = await selectPaymentMethod(page, paymentSelectors.bank);
    test.skip(!bankMethod, 'No bank payment method configured');

    const submethods = await getSubmethods(page);
    test.skip(submethods.length === 0, 'No submethods available');

    await selectSubmethod(page, 0);

    const selected = await getSelectedSubmethodName(page);
    expect(selected).toBeTruthy();
  });

  test('persists submethod selection after page reload', async ({ page }) => {
    const bankMethod = await selectPaymentMethod(page, paymentSelectors.bank);
    test.skip(!bankMethod, 'No bank payment method configured');

    const submethods = await getSubmethods(page);
    test.skip(submethods.length === 0, 'No submethods available');

    const firstSubmethod = await selectSubmethod(page, 0);
    const selectedName = firstSubmethod?.name;

    // Reload checkout page
    await goToCheckout(page);
    await selectPaymentMethod(page, paymentSelectors.bank);

    const persistedName = await getSelectedSubmethodName(page);
    expect(persistedName).toContain(selectedName);
  });

  test('hides submethods for non-Crehler payment methods', async ({ page }) => {
    // First select bank method to see submethods
    const bankMethod = await selectPaymentMethod(page, paymentSelectors.bank);
    if (bankMethod) {
      const bankSubmethods = await getSubmethods(page);

      // Now select a non-Crehler payment method
      const nonCrehlerMethod = await selectPaymentMethod(page, paymentSelectors.nonCrehler);
      if (nonCrehlerMethod) {
        const nonCrehlerSubmethods = await getSubmethods(page);
        expect(nonCrehlerSubmethods.length).toBe(0);
      }
    }
  });
});
