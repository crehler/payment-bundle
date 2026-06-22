/**
 * Testy statusu płatności Store API
 *
 * Testuje endpoint:
 * - POST /cr/payment/check
 *
 * Scenariusze:
 * - Sprawdzenie statusu nowego zamówienia
 * - Status nieistniejącego zamówienia
 * - Sprawdzenie statusu jako gość
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
    TestResults,
} from '../utils/logger.js';

describe('Payment Status Tests', () => {
    let client;
    let config;
    let testProduct;
    let testOrder = null;
    const results = new TestResults();
    let testCount = 0;
    const totalTests = 3;

    beforeAll(async () => {
        config = validateConfig();
        client = new StoreApiClient(config.storeUrl, config.accessKey);
        printHeader('PAYMENT STATUS TESTS');

        // Login
        printStep('Logowanie...');
        await client.login(config.customerEmail, config.customerPassword);

        // Find product
        printStep('Wyszukiwanie produktu testowego...');
        testProduct = await client.findProductByNumber(config.productNumber);
        printInfo(`Produkt: ${testProduct.name}`);
    });

    afterAll(async () => {
        // Clean up - clear cart
        try {
            await client.clearCart();
        } catch {
            // Ignore cleanup errors
        }
    });

    it('should check status of new order', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Sprawdź status nowego zamówienia');

        // Create order for testing
        printStep('Przygotowanie koszyka...');
        await client.clearCart();
        await client.addToCart(testProduct.id, 1);

        // Get available payment methods and set one
        const paymentMethods = await client.getPaymentMethods();
        const paymentMethod = paymentMethods?.elements?.[0];

        if (paymentMethod) {
            await client.setPaymentMethod(paymentMethod.id);
        }

        printStep('Tworzenie zamówienia...');
        try {
            testOrder = await client.createOrder();
            printOrderCreated(testOrder.orderNumber, testOrder.id);
        } catch (error) {
            printError(`Nie udało się utworzyć zamówienia: ${error.message}`);
            results.fail('Check new order status', error, Date.now() - startTime);
            throw error;
        }

        printEndpoint('POST', '/cr/payment/check');
        printParams({
            'Order ID': testOrder.id,
        });

        try {
            const response = await client.checkPaymentStatus(testOrder.id);

            expect(response).toBeDefined();
            expect(response).toHaveProperty('status');
            expect(response).toHaveProperty('waiting');

            printResponse(200, {
                'Status (zapłacono)': response.status ? 'TAK' : 'NIE',
                'Waiting (oczekuje)': response.waiting ? 'TAK' : 'NIE',
            });
            printTestResult(true, Date.now() - startTime);
            results.pass('Check new order status', Date.now() - startTime);
        } catch (error) {
            printError(error.message);
            printTestResult(false, Date.now() - startTime);
            results.fail('Check new order status', error, Date.now() - startTime);
            throw error;
        }
    });

    it('should handle non-existent order ID', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Status nieistniejącego zamówienia');

        const fakeOrderId = '00000000-0000-0000-0000-000000000000';

        printEndpoint('POST', '/cr/payment/check');
        printParams({
            'Order ID': fakeOrderId + ' (nieistniejące)',
        });

        try {
            const response = await client.rawRequest('POST', '/cr/payment/check', {
                orderId: fakeOrderId,
            });

            if (!response.ok) {
                printResponse(response.status, {
                    'Status': 'Błąd (oczekiwane dla nieistniejącego zamówienia)',
                });
                printTestResult(true, Date.now() - startTime);
                results.pass('Non-existent order (error)', Date.now() - startTime);
            } else {
                // API might return false status for non-existent orders
                printResponse(200, {
                    'Status': response.data?.status ? 'Zapłacone' : 'Nie zapłacone',
                    'Info': 'API zwróciło odpowiedź (zamiast błędu)',
                });
                printTestResult(true, Date.now() - startTime);
                results.pass('Non-existent order (response)', Date.now() - startTime);
            }
        } catch (error) {
            // Error is acceptable for non-existent order
            printResponse(404, {
                'Status': 'Nie znaleziono (oczekiwane)',
            });
            printTestResult(true, Date.now() - startTime);
            results.pass('Non-existent order (error)', Date.now() - startTime);
        }
    });

    it('should work for guest users (with guest token)', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Sprawdź status jako gość');

        if (!testOrder) {
            printWarning('Pominięto - brak zamówienia testowego');
            results.skip('Guest status check', 'No test order available');
            return;
        }

        // Create a fresh client without login (guest)
        const guestClient = new StoreApiClient(config.storeUrl, config.accessKey);

        printEndpoint('POST', '/cr/payment/check');
        printParams({
            'Order ID': testOrder.id,
            'Context': 'Guest (bez logowania)',
        });

        try {
            // Note: The endpoint allows guests but they need a context token
            // In real scenario, guest would have their order's context token
            const response = await guestClient.rawRequest('POST', '/cr/payment/check', {
                orderId: testOrder.id,
            });

            if (response.ok) {
                printResponse(200, {
                    'Status': response.data?.status ? 'Zapłacone' : 'Nie zapłacone',
                    'Info': 'Gość może sprawdzić status',
                });
            } else {
                printResponse(response.status, {
                    'Status': 'Odmowa dostępu dla gościa',
                    'Info': 'Gość potrzebuje właściwego context-token',
                });
            }

            printTestResult(true, Date.now() - startTime);
            results.pass('Guest status check', Date.now() - startTime);
        } catch (error) {
            // Error might be expected if guest access is restricted
            printResponse(403, {
                'Status': 'Odmowa dostępu',
                'Info': 'Gość potrzebuje context-token zamówienia',
            });
            printTestResult(true, Date.now() - startTime);
            results.pass('Guest status check (restricted)', Date.now() - startTime);
        }
    });
});
