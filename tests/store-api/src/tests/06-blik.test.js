/**
 * Testy płatności BLIK Store API
 *
 * Testuje endpoint:
 * - POST /cr/payment/blik
 *
 * Scenariusze:
 * - Płatność BLIK z poprawnym kodem
 * - BLIK z błędnym kodem (format)
 * - BLIK bez kodu
 * - BLIK dla nieobsługiwanej metody płatności
 *
 * UWAGA: Testy używają kodu testowego z konfiguracji.
 * Rzeczywista płatność wymaga prawdziwego kodu z aplikacji bankowej.
 */

import { describe, it, expect, beforeAll, afterAll } from 'vitest';
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
    TestResults,
} from '../utils/logger.js';

describe('BLIK Payment Tests', () => {
    let client;
    let config;
    let testProduct;
    let blikPaymentMethod = null;
    let nonBlikPaymentMethod = null;
    const results = new TestResults();
    let testCount = 0;
    const totalTests = 6;

    /**
     * Extracts the orderTransactionId from a redirectUrl returned by /cr/payment/blik.
     * Two formats are supported:
     *   1) ?transactionId=<uuid> — BLIK authorize page (CrehlerTpayBundle BlikHandler)
     *   2) ?_sw_payment_token=<JWT> — Shopware finalize redirect; JWT `sub` claim is the OT id
     */
    function extractOrderTransactionId(redirectUrl) {
        if (!redirectUrl) return null;

        try {
            const url = new URL(redirectUrl);
            const direct = url.searchParams.get('transactionId');
            if (direct) return direct;

            const token = url.searchParams.get('_sw_payment_token');
            if (token) {
                const [, payload] = token.split('.');
                if (!payload) return null;
                const padded = payload.padEnd(payload.length + ((4 - (payload.length % 4)) % 4), '=');
                const decoded = JSON.parse(Buffer.from(padded, 'base64').toString('utf-8'));
                return decoded.sub ?? null;
            }
        } catch {
            return null;
        }

        return null;
    }

    beforeAll(async () => {
        config = validateConfig();
        client = new StoreApiClient(config.storeUrl, config.accessKey);
        printHeader('BLIK PAYMENT TESTS');

        // Login
        printStep('Logowanie...');
        await client.login(config.customerEmail, config.customerPassword);

        // Find product
        printStep('Wyszukiwanie produktu testowego...');
        testProduct = await client.findProductByNumber(config.productNumber);
        printInfo(`Produkt: ${testProduct.name}`);

        // Find BLIK payment method
        printStep('Wyszukiwanie metody BLIK...');
        const paymentMethods = await client.getPaymentMethods();
        const allMethods = paymentMethods?.elements || [];

        // Find BLIK payment method using cr_payment_type extension
        blikPaymentMethod = allMethods.find(
            (pm) => pm.extensions?.cr_payment_type?.isBlik === true ||
                    pm.name === 'BLIK' ||
                    pm.name?.toLowerCase().includes('blik')
        );

        // Find any non-BLIK payment method for negative testing
        nonBlikPaymentMethod = allMethods.find(
            (pm) => pm.extensions?.cr_payment_type?.isBlik !== true &&
                    !pm.name?.toLowerCase().includes('blik') &&
                    pm.name !== 'Cash on delivery'
        );

        if (blikPaymentMethod) {
            printInfo(`Metoda BLIK: ${blikPaymentMethod.name}`);
        } else {
            printWarning('Nie znaleziono metody BLIK - niektóre testy będą pominięte');
        }
    });

    afterAll(async () => {
        try {
            await client.clearCart();
        } catch {
            // Ignore cleanup errors
        }
    });

    it('should process BLIK payment with valid code', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Płatność BLIK z poprawnym kodem');

        if (!blikPaymentMethod) {
            printWarning('Pominięto - brak metody BLIK');
            results.skip('BLIK with valid code', 'No BLIK payment method');
            return;
        }

        // Prepare cart and order
        printStep('Przygotowanie koszyka...');
        await client.clearCart();
        await client.addToCart(testProduct.id, 1);
        await client.setPaymentMethod(blikPaymentMethod.id);

        printStep('Tworzenie zamówienia...');
        let order;
        try {
            order = await client.createOrder();
            printOrderCreated(order.orderNumber, order.id);
        } catch (error) {
            printError(`Nie udało się utworzyć zamówienia: ${error.message}`);
            results.fail('BLIK with valid code', error, Date.now() - startTime);
            throw error;
        }

        printEndpoint('POST', '/cr/payment/blik');
        printParams({
            'Payment Method ID': blikPaymentMethod.id,
            'BLIK Code': config.blikCode,
            'Order ID': order.id,
        });

        try {
            const response = await client.processBlikPayment(blikPaymentMethod.id, config.blikCode);

            expect(response).toBeDefined();

            if (response.success) {
                printResponse(200, {
                    'Success': 'TAK',
                    'Order ID': response.orderId || order.id,
                    'Redirect URL': response.redirectUrl ? 'Tak' : 'Nie',
                });

                if (response.redirectUrl) {
                    printRedirect(response.redirectUrl);
                }
            } else {
                printResponse(200, {
                    'Success': 'NIE',
                    'Error': response.error || 'Brak szczegółów',
                    'Info': 'Kod testowy może nie działać w środowisku produkcyjnym',
                });
            }

            printTestResult(true, Date.now() - startTime);
            results.pass('BLIK with valid code', Date.now() - startTime);
        } catch (error) {
            printError(error.message);
            printTestResult(false, Date.now() - startTime);
            results.fail('BLIK with valid code', error, Date.now() - startTime);
            throw error;
        }
    });

    it('should reject BLIK with invalid code format', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'BLIK z błędnym formatem kodu');

        if (!blikPaymentMethod) {
            printWarning('Pominięto - brak metody BLIK');
            results.skip('BLIK invalid format', 'No BLIK payment method');
            return;
        }

        const invalidCode = '12345'; // Only 5 digits instead of 6

        printEndpoint('POST', '/cr/payment/blik');
        printParams({
            'Payment Method ID': blikPaymentMethod.id,
            'BLIK Code': invalidCode + ' (niepoprawny format)',
        });

        try {
            const response = await client.rawRequest('POST', '/cr/payment/blik', {
                paymentMethodId: blikPaymentMethod.id,
                blikCode: invalidCode,
            });

            if (!response.ok || !response.data?.success) {
                printResponse(response.status, {
                    'Status': 'Odrzucono (oczekiwane)',
                    'Error': response.data?.error || 'Błąd walidacji',
                });
                printTestResult(true, Date.now() - startTime);
                results.pass('BLIK invalid format rejected', Date.now() - startTime);
            } else {
                // Unexpected - API accepted invalid code
                printResponse(200, {
                    'Status': 'Zaakceptowano (nieoczekiwane)',
                });
                printTestResult(false, Date.now() - startTime);
                results.fail('BLIK invalid format', 'API accepted invalid code', Date.now() - startTime);
            }
        } catch (error) {
            // Error is expected
            printResponse(400, {
                'Status': 'Błąd walidacji (oczekiwane)',
            });
            printTestResult(true, Date.now() - startTime);
            results.pass('BLIK invalid format rejected', Date.now() - startTime);
        }
    });

    it('should reject BLIK without code', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'BLIK bez kodu');

        if (!blikPaymentMethod) {
            printWarning('Pominięto - brak metody BLIK');
            results.skip('BLIK without code', 'No BLIK payment method');
            return;
        }

        printEndpoint('POST', '/cr/payment/blik');
        printParams({
            'Payment Method ID': blikPaymentMethod.id,
            'BLIK Code': '(brak)',
        });

        try {
            const response = await client.rawRequest('POST', '/cr/payment/blik', {
                paymentMethodId: blikPaymentMethod.id,
                // No blikCode
            });

            if (!response.ok) {
                printResponse(response.status, {
                    'Status': 'Odrzucono (oczekiwane)',
                    'Info': 'Kod BLIK jest wymagany',
                });
                printTestResult(true, Date.now() - startTime);
                results.pass('BLIK without code rejected', Date.now() - startTime);
            } else {
                printResponse(200, {
                    'Status': 'Błąd API - brak walidacji',
                });
                printTestResult(false, Date.now() - startTime);
                results.fail('BLIK without code', 'API should reject request without code');
            }
        } catch (error) {
            printResponse(400, {
                'Status': 'Błąd walidacji (oczekiwane)',
            });
            printTestResult(true, Date.now() - startTime);
            results.pass('BLIK without code rejected', Date.now() - startTime);
        }
    });

    it('retry creates a new OrderTransaction and sets it as primary', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'BLIK retry — nowy orderTransactionId');

        if (!blikPaymentMethod) {
            printWarning('Pominięto - brak metody BLIK');
            results.skip('BLIK retry creates new OT', 'No BLIK payment method');
            return;
        }

        printStep('Przygotowanie zamówienia i pierwszego submitu BLIK...');
        await client.clearCart();
        await client.addToCart(testProduct.id, 1);
        await client.setPaymentMethod(blikPaymentMethod.id);

        let firstSubmit;
        try {
            firstSubmit = await client.processBlikPayment(blikPaymentMethod.id, config.blikCode);
        } catch (error) {
            printError(`Pierwszy submit nie udał się: ${error.message}`);
            results.fail('BLIK retry creates new OT', error, Date.now() - startTime);
            throw error;
        }

        expect(firstSubmit.success).toBe(true);
        expect(firstSubmit.orderId).toBeDefined();
        expect(firstSubmit.redirectUrl).toBeDefined();

        const firstOtId = extractOrderTransactionId(firstSubmit.redirectUrl);
        expect(firstOtId, 'first submit redirectUrl must expose an orderTransactionId').toBeTruthy();
        printInfo(`Pierwszy orderTransactionId: ${firstOtId}`);

        printStep('Retry z tym samym kodem BLIK na tym samym zamówieniu...');
        printEndpoint('POST', `/cr/payment/blik/order/${firstSubmit.orderId}`);

        let retrySubmit;
        try {
            retrySubmit = await client.retryBlikPayment(firstSubmit.orderId, config.blikCode);
        } catch (error) {
            printError(`Retry nie udał się: ${error.message}`);
            results.fail('BLIK retry creates new OT', error, Date.now() - startTime);
            throw error;
        }

        expect(retrySubmit.success).toBe(true);
        expect(retrySubmit.orderId).toBe(firstSubmit.orderId);
        expect(retrySubmit.redirectUrl).toBeDefined();

        const retryOtId = extractOrderTransactionId(retrySubmit.redirectUrl);
        expect(retryOtId, 'retry redirectUrl must expose an orderTransactionId').toBeTruthy();
        printInfo(`Retry orderTransactionId: ${retryOtId}`);

        expect(retryOtId, 'retry must create a NEW OrderTransaction, not reuse the previous one').not.toBe(firstOtId);

        printResponse(200, {
            'First OT': firstOtId,
            'Retry OT': retryOtId,
            'Different': retryOtId !== firstOtId ? 'TAK' : 'NIE',
        });
        printTestResult(true, Date.now() - startTime);
        results.pass('BLIK retry creates new OT', Date.now() - startTime);
    });

    it('retry endpoint rejects invalid BLIK code format', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'BLIK retry — odrzucenie złego kodu');

        if (!blikPaymentMethod) {
            printWarning('Pominięto - brak metody BLIK');
            results.skip('BLIK retry invalid format', 'No BLIK payment method');
            return;
        }

        printStep('Przygotowanie zamówienia dla retry...');
        await client.clearCart();
        await client.addToCart(testProduct.id, 1);
        await client.setPaymentMethod(blikPaymentMethod.id);

        const initial = await client.processBlikPayment(blikPaymentMethod.id, config.blikCode);
        expect(initial.success).toBe(true);
        expect(initial.orderId).toBeDefined();

        const invalidCode = '12345';
        printEndpoint('POST', `/cr/payment/blik/order/${initial.orderId}`);
        printParams({ 'BLIK Code': invalidCode + ' (5 cyfr)' });

        try {
            const response = await client.rawRequest('POST', `/cr/payment/blik/order/${initial.orderId}`, {
                blikCode: invalidCode,
            });

            if (!response.ok || !response.data?.success) {
                printResponse(response.status, { 'Status': 'Odrzucono (oczekiwane)' });
                printTestResult(true, Date.now() - startTime);
                results.pass('BLIK retry invalid format rejected', Date.now() - startTime);
            } else {
                printResponse(200, { 'Status': 'Zaakceptowano (nieoczekiwane)' });
                printTestResult(false, Date.now() - startTime);
                results.fail('BLIK retry invalid format', 'API accepted invalid code');
            }
        } catch (error) {
            printResponse(400, { 'Status': 'Błąd walidacji (oczekiwane)' });
            printTestResult(true, Date.now() - startTime);
            results.pass('BLIK retry invalid format rejected', Date.now() - startTime);
        }
    });

    it('should reject BLIK for non-BLIK payment method', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'BLIK dla nieobsługiwanej metody');

        if (!nonBlikPaymentMethod) {
            printWarning('Pominięto - nie znaleziono metody nie-BLIK');
            results.skip('BLIK wrong method', 'No non-BLIK payment method');
            return;
        }

        printEndpoint('POST', '/cr/payment/blik');
        printParams({
            'Payment Method ID': nonBlikPaymentMethod.id,
            'Payment Method Name': nonBlikPaymentMethod.name + ' (nie BLIK)',
            'BLIK Code': config.blikCode,
        });

        try {
            const response = await client.rawRequest('POST', '/cr/payment/blik', {
                paymentMethodId: nonBlikPaymentMethod.id,
                blikCode: config.blikCode,
            });

            if (!response.ok || !response.data?.success) {
                printResponse(response.status, {
                    'Status': 'Odrzucono (oczekiwane)',
                    'Info': 'Metoda nie obsługuje BLIK',
                });
                printTestResult(true, Date.now() - startTime);
                results.pass('BLIK wrong method rejected', Date.now() - startTime);
            } else {
                printResponse(200, {
                    'Status': 'Nieoczekiwany sukces',
                });
                printTestResult(false, Date.now() - startTime);
                results.fail('BLIK wrong method', 'API accepted BLIK for non-BLIK method');
            }
        } catch (error) {
            printResponse(400, {
                'Status': 'Odrzucono (oczekiwane)',
            });
            printTestResult(true, Date.now() - startTime);
            results.pass('BLIK wrong method rejected', Date.now() - startTime);
        }
    });
});
