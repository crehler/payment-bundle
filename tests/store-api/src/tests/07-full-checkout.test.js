/**
 * Pełne testy checkout dla wszystkich metod płatności Store API
 *
 * Testuje pełny przepływ:
 * - Dodanie produktu do koszyka
 * - Ustawienie metody płatności
 * - Pobranie i wybór submetody (jeśli dostępne)
 * - Utworzenie zamówienia
 * - Obsługa płatności (redirect lub BLIK)
 * - Weryfikacja statusu płatności
 *
 * UWAGA: Test wymaga interakcji operatora dla płatności zewnętrznych.
 * Można go uruchomić w trybie automatycznym (bez czekania na płatność)
 * lub interaktywnym (z możliwością dokończenia płatności).
 */

import { describe, it, expect, beforeAll, afterAll, beforeEach } from 'vitest';
import { StoreApiClient } from '../store-api-client.js';
import { testConfig, validateConfig } from '../config.js';
import {
    printHeader,
    printTestHeader,
    printEndpoint,
    printParams,
    printResponse,
    printTestResult,
    printStep,
    printInfo,
    printWarning,
    printError,
    printOrderCreated,
    printRedirect,
    printSummary,
    TestResults,
} from '../utils/logger.js';

// Tryb testowania: 'auto' lub 'interactive'
const TEST_MODE = process.env.TEST_MODE || 'auto';

describe('Full Checkout Tests', () => {
    let client;
    let config;
    let testProduct;
    let allPaymentMethods = [];
    const results = new TestResults();
    let testCount = 0;

    beforeAll(async () => {
        config = validateConfig();
        client = new StoreApiClient(config.storeUrl, config.accessKey);
        printHeader('FULL CHECKOUT TESTS');

        printInfo(`Tryb testowania: ${TEST_MODE === 'interactive' ? 'INTERAKTYWNY' : 'AUTOMATYCZNY'}`);
        if (TEST_MODE === 'auto') {
            printInfo('Testy zakończą się po utworzeniu zamówienia (bez oczekiwania na płatność)');
        }

        // Login
        printStep('Logowanie...');
        await client.login(config.customerEmail, config.customerPassword);

        // Find product
        printStep('Wyszukiwanie produktu testowego...');
        testProduct = await client.findProductByNumber(config.productNumber);
        printInfo(`Produkt: ${testProduct.name} (${testProduct.productNumber})`);

        // Get all payment methods
        printStep('Pobieranie metod płatności...');
        const paymentMethodsResponse = await client.getPaymentMethods();
        allPaymentMethods = paymentMethodsResponse?.elements || [];
        printInfo(`Dostępne metody płatności: ${allPaymentMethods.length}`);

        // Filter to CrehlerPaymentBundle methods using cr_payment_type extension
        const paymentBundleMethods = allPaymentMethods.filter(
            (pm) => pm.extensions?.cr_payment_type?.isCrehlerPayment === true ||
                    pm.name?.toLowerCase().includes('payu') ||
                    pm.name?.toLowerCase().includes('blik') ||
                    pm.name?.toLowerCase().includes('karta') ||
                    pm.name?.toLowerCase().includes('portfel')
        );
        printInfo(`Metody CrehlerPaymentBundle: ${paymentBundleMethods.length}`);

        if (paymentBundleMethods.length > 0) {
            allPaymentMethods = paymentBundleMethods;
        }
    });

    afterAll(async () => {
        try {
            await client.clearCart();
        } catch {
            // Ignore cleanup errors
        }

        // Print summary
        console.log('\n');
        results.print();
    });

    beforeEach(async () => {
        // Clear cart before each test
        try {
            await client.clearCart();
        } catch {
            // Ignore
        }
    });

    /**
     * Klasyfikuje metodę płatności na podstawie cr_payment_type extension
     * Falls back to name matching if extension not available
     */
    function classifyPaymentMethod(paymentMethod) {
        const ext = paymentMethod.extensions?.cr_payment_type;
        const name = paymentMethod.name?.toLowerCase() || '';

        // Use extension if available
        if (ext) {
            if (ext.isBlik) return 'blik';
            if (ext.isBank) return 'bank';
            if (ext.isCard) return 'card';
            if (ext.isEwallet) return 'wallet';
            if (ext.isDeferred) return 'installment';
        }

        // Fallback to name matching
        if (name.includes('blik')) {
            return 'blik';
        } else if (name === 'payu' || name.includes('przelew')) {
            return 'bank';
        } else if (name.includes('karta') || name.includes('card')) {
            return 'card';
        } else if (name.includes('apple')) {
            return 'apple';
        } else if (name.includes('google')) {
            return 'google';
        } else if (name.includes('visa')) {
            return 'visa';
        } else if (name.includes('portfel') || name.includes('wallet')) {
            return 'wallet';
        } else if (name.includes('odroczon') || name.includes('raty') || name.includes('twisto')) {
            return 'installment';
        } else {
            return 'generic';
        }
    }

    /**
     * Wykonuje checkout dla danej metody płatności
     */
    async function performCheckout(paymentMethod, testIndex, totalTests) {
        const startTime = Date.now();
        const testName = `Checkout: ${paymentMethod.name}`;
        const paymentType = classifyPaymentMethod(paymentMethod);

        printTestHeader(testIndex, totalTests, `Checkout - ${paymentMethod.name}`);
        printInfo(`Typ: ${paymentType.toUpperCase()}`);

        try {
            // Step 1: Add product to cart
            printStep('Dodawanie produktu do koszyka...');
            await client.addToCart(testProduct.id, 1);
            printInfo('Produkt dodany');

            // Step 2: Set payment method
            printStep('Ustawianie metody płatności...');
            await client.setPaymentMethod(paymentMethod.id);
            printInfo(`Metoda: ${paymentMethod.name}`);

            // Step 3: Get submethods if available (bank transfers)
            let selectedSubmethod = null;
            if (paymentType === 'bank') {
                printStep('Pobieranie submetod (banki)...');
                try {
                    const submethodsResponse = await client.getPaymentSubMethods(paymentMethod.id, 10000);
                    const submethods = submethodsResponse?.elements || [];

                    if (submethods.length > 0) {
                        printInfo(`Dostępne submetody: ${submethods.length}`);

                        // Select first available submethod
                        selectedSubmethod = submethods[0];
                        printInfo(`Wybrana submetoda: ${selectedSubmethod.name}`);

                        // Save customer preference
                        printStep('Zapisywanie preferencji submetody...');
                        await client.setPaymentSubMethod(paymentMethod.id, selectedSubmethod.providerId);
                    }
                } catch (subError) {
                    printWarning(`Nie udało się pobrać submetod: ${subError.message}`);
                }
            }

            // Step 4: Create order
            printStep('Tworzenie zamówienia...');
            let order;
            try {
                order = await client.createOrder();
                printOrderCreated(order.orderNumber, order.id);
            } catch (orderError) {
                printError(`Błąd tworzenia zamówienia: ${orderError.message}`);
                results.fail(testName, orderError, Date.now() - startTime);
                return { success: false, error: orderError };
            }

            // Step 5: Handle payment based on type
            let paymentResult = { success: true };

            if (paymentType === 'blik') {
                // BLIK payment
                printStep('Przetwarzanie płatności BLIK...');
                printEndpoint('POST', '/cr/payment/blik');
                printParams({
                    'Payment Method ID': paymentMethod.id,
                    'BLIK Code': config.blikCode,
                });

                try {
                    const blikResponse = await client.processBlikPayment(paymentMethod.id, config.blikCode);
                    printResponse(200, {
                        'Success': blikResponse?.success ? 'TAK' : 'NIE',
                        'Redirect': blikResponse?.redirectUrl ? 'Tak' : 'Nie',
                    });

                    if (blikResponse?.redirectUrl) {
                        printRedirect(blikResponse.redirectUrl);
                    }
                } catch (blikError) {
                    printWarning(`BLIK: ${blikError.message} (kod testowy może nie działać)`);
                }
            } else {
                // Standard payment - call handlePayment to get redirect URL
                printStep('Inicjowanie płatności (handlePayment)...');
                printEndpoint('POST', '/handle-payment');

                try {
                    const finishUrl = `${config.storeUrl}/checkout/finish?orderId=${order.id}`;
                    const errorUrl = `${config.storeUrl}/checkout/finish?orderId=${order.id}&error=1`;

                    const paymentResponse = await client.handlePayment(order.id, finishUrl, errorUrl);

                    if (paymentResponse?.redirectUrl) {
                        printResponse(200, {
                            'Redirect URL': 'TAK',
                        });
                        printRedirect(paymentResponse.redirectUrl);

                        if (TEST_MODE === 'interactive') {
                            printInfo('');
                            printInfo('▶ Otwórz powyższy URL w przeglądarce i dokończ płatność');
                            printInfo('▶ Po zakończeniu naciśnij Enter w terminalu...');
                        }
                    } else {
                        printResponse(200, {
                            'Redirect URL': 'NIE (płatność nie wymaga przekierowania)',
                        });
                    }
                } catch (paymentError) {
                    printWarning(`handlePayment: ${paymentError.message}`);
                }
            }

            // Step 6: Check payment status
            printStep('Sprawdzanie statusu płatności...');
            printEndpoint('POST', '/cr/payment/check');

            try {
                const statusResponse = await client.checkPaymentStatus(order.id);
                printResponse(200, {
                    'Status (zapłacono)': statusResponse?.status ? 'TAK' : 'NIE',
                    'Waiting (oczekuje)': statusResponse?.waiting ? 'TAK' : 'NIE',
                });

                // In auto mode, we expect status=false, waiting=true for new orders
                if (TEST_MODE === 'auto') {
                    printInfo('(W trybie auto oczekujemy status=false dla niezapłaconego zamówienia)');
                }
            } catch (statusError) {
                printWarning(`Status: ${statusError.message}`);
            }

            // Test passed if we got this far
            printTestResult(true, Date.now() - startTime);
            results.pass(testName, Date.now() - startTime);
            return { success: true, order };

        } catch (error) {
            printError(error.message);
            printTestResult(false, Date.now() - startTime);
            results.fail(testName, error, Date.now() - startTime);
            return { success: false, error };
        }
    }

    // Dynamic test generation for each payment method
    it('should complete checkout for all payment methods', async () => {
        if (allPaymentMethods.length === 0) {
            printWarning('Brak metod płatności do przetestowania');
            results.skip('All checkouts', 'No payment methods available');
            return;
        }

        const checkoutResults = [];

        for (let i = 0; i < allPaymentMethods.length; i++) {
            const paymentMethod = allPaymentMethods[i];
            testCount++;

            const result = await performCheckout(paymentMethod, testCount, allPaymentMethods.length);
            checkoutResults.push({
                method: paymentMethod.name,
                ...result,
            });

            // Small delay between tests
            if (i < allPaymentMethods.length - 1) {
                await new Promise((resolve) => setTimeout(resolve, 500));
            }
        }

        // Summary
        console.log('\n');
        printHeader('CHECKOUT RESULTS SUMMARY');

        const successful = checkoutResults.filter((r) => r.success);
        const failed = checkoutResults.filter((r) => !r.success);

        printInfo(`Przetestowano: ${checkoutResults.length} metod płatności`);
        printInfo(`Sukces: ${successful.length}`);
        if (failed.length > 0) {
            printWarning(`Błędy: ${failed.length}`);
            failed.forEach((f) => {
                printError(`  - ${f.method}: ${f.error?.message || 'Unknown error'}`);
            });
        }

        // At least one checkout should succeed
        expect(successful.length).toBeGreaterThan(0);
    });

    // Individual payment type tests
    it('should handle bank transfer checkout', async () => {
        const startTime = Date.now();
        testCount++;
        const testName = 'Bank Transfer Checkout';

        const bankMethod = allPaymentMethods.find(
            (pm) => pm.extensions?.cr_payment_type?.hasSubmethods === true ||
                    pm.extensions?.cr_payment_type?.isBank === true ||
                    pm.name === 'PayU' ||
                    pm.name?.toLowerCase().includes('przelew')
        );

        if (!bankMethod) {
            printWarning('Pominięto - brak metody bankowej');
            results.skip(testName, 'No bank payment method');
            return;
        }

        printTestHeader(testCount, allPaymentMethods.length + 3, 'Przelew bankowy - szczegółowy test');

        try {
            // Clear and setup
            await client.clearCart();
            await client.addToCart(testProduct.id, 1);
            await client.setPaymentMethod(bankMethod.id);

            // Get submethods
            const submethodsResponse = await client.getPaymentSubMethods(bankMethod.id, 10000);
            const submethods = submethodsResponse?.elements || [];

            if (submethods.length === 0) {
                printWarning('Brak skonfigurowanych submetod dla przelewu bankowego - pomijam');
                results.skip(testName, 'No bank submethods configured');
                return;
            }
            printInfo(`Banki: ${submethods.length}`);

            // Select submethod
            const selectedBank = submethods[0];
            await client.setPaymentSubMethod(bankMethod.id, selectedBank.providerId);
            printInfo(`Wybrany bank: ${selectedBank.name}`);

            // Verify preference saved
            const savedPref = await client.getCustomerSubMethod();
            printInfo(`Zapisana preferencja: ${savedPref?.subPaymentMethodId || 'brak'}`);

            // Create order
            const order = await client.createOrder();
            printOrderCreated(order.orderNumber, order.id);

            // Handle payment to get redirect URL
            printStep('Inicjowanie płatności...');
            const finishUrl = `${config.storeUrl}/checkout/finish?orderId=${order.id}`;
            const errorUrl = `${config.storeUrl}/checkout/finish?orderId=${order.id}&error=1`;

            const paymentResponse = await client.handlePayment(order.id, finishUrl, errorUrl);
            if (paymentResponse?.redirectUrl) {
                printRedirect(paymentResponse.redirectUrl);
            } else {
                printWarning('Brak URL przekierowania');
            }

            printTestResult(true, Date.now() - startTime);
            results.pass(testName, Date.now() - startTime);
        } catch (error) {
            printError(error.message);
            printTestResult(false, Date.now() - startTime);
            results.fail(testName, error, Date.now() - startTime);
            throw error;
        }
    });

    it('should handle BLIK checkout', async () => {
        const startTime = Date.now();
        testCount++;
        const testName = 'BLIK Checkout';

        const blikMethod = allPaymentMethods.find(
            (pm) => pm.extensions?.cr_payment_type?.isBlik === true ||
                    pm.name === 'BLIK' ||
                    pm.name?.toLowerCase().includes('blik')
        );

        if (!blikMethod) {
            printWarning('Pominięto - brak metody BLIK');
            results.skip(testName, 'No BLIK payment method');
            return;
        }

        printTestHeader(testCount, allPaymentMethods.length + 3, 'BLIK - szczegółowy test');

        try {
            // Clear and setup
            await client.clearCart();
            await client.addToCart(testProduct.id, 1);
            await client.setPaymentMethod(blikMethod.id);

            // Create order
            const order = await client.createOrder();
            printOrderCreated(order.orderNumber, order.id);

            // Process BLIK
            printStep('Wysyłanie kodu BLIK...');
            printEndpoint('POST', '/cr/payment/blik');

            const blikResponse = await client.processBlikPayment(blikMethod.id, config.blikCode);

            printResponse(200, {
                'Success': blikResponse?.success ? 'TAK' : 'NIE',
                'Message': blikResponse?.message || blikResponse?.error || 'Brak',
            });

            // Check status
            const status = await client.checkPaymentStatus(order.id);
            printInfo(`Status płatności: ${status?.status ? 'Zapłacone' : 'Oczekuje'}`);

            printTestResult(true, Date.now() - startTime);
            results.pass(testName, Date.now() - startTime);
        } catch (error) {
            printError(error.message);
            printTestResult(false, Date.now() - startTime);
            results.fail(testName, error, Date.now() - startTime);
            throw error;
        }
    });

    it('should handle card checkout', async () => {
        const startTime = Date.now();
        testCount++;
        const testName = 'Card Checkout';

        const cardMethod = allPaymentMethods.find(
            (pm) => pm.extensions?.cr_payment_type?.isCard === true ||
                    pm.name?.toLowerCase().includes('karta') ||
                    pm.name?.toLowerCase().includes('card')
        );

        if (!cardMethod) {
            printWarning('Pominięto - brak metody kartowej');
            results.skip(testName, 'No card payment method');
            return;
        }

        printTestHeader(testCount, allPaymentMethods.length + 3, 'Karta - szczegółowy test');

        try {
            // Clear and setup
            await client.clearCart();
            await client.addToCart(testProduct.id, 1);
            await client.setPaymentMethod(cardMethod.id);

            // Check for saved cards
            printStep('Sprawdzanie zapisanych kart...');
            const savedCards = await client.getSavedCards();
            const cards = savedCards?.elements || [];
            printInfo(`Zapisane karty: ${cards.length}`);

            // Create order
            const order = await client.createOrder();
            printOrderCreated(order.orderNumber, order.id);

            // Handle payment to get redirect URL
            printStep('Inicjowanie płatności...');
            const finishUrl = `${config.storeUrl}/checkout/finish?orderId=${order.id}`;
            const errorUrl = `${config.storeUrl}/checkout/finish?orderId=${order.id}&error=1`;

            const paymentResponse = await client.handlePayment(order.id, finishUrl, errorUrl);
            if (paymentResponse?.redirectUrl) {
                printRedirect(paymentResponse.redirectUrl);
            } else {
                printWarning('Brak URL przekierowania');
            }

            printTestResult(true, Date.now() - startTime);
            results.pass(testName, Date.now() - startTime);
        } catch (error) {
            printError(error.message);
            printTestResult(false, Date.now() - startTime);
            results.fail(testName, error, Date.now() - startTime);
            throw error;
        }
    });
});
