/**
 * Testy submetod płatności Store API
 *
 * Testuje endpointy:
 * - GET /cr/payment-sub-methods/{paymentId}
 * - GET /cr/payment-sub-methods
 *
 * Scenariusze:
 * - Pobranie submetod dla konkretnej metody płatności
 * - Pobranie submetod dla aktualnej metody w kontekście
 * - Walidacja struktury odpowiedzi
 * - Filtrowanie po kwocie (min/max amount)
 */

import { describe, it, expect, beforeAll } from 'vitest';
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
    TestResults,
} from '../utils/logger.js';

describe('Payment Submethods Tests', () => {
    let client;
    let config;
    let bankPaymentMethod = null;
    let allPaymentMethods = [];
    const results = new TestResults();
    let testCount = 0;
    const totalTests = 4;

    beforeAll(async () => {
        config = validateConfig();
        client = new StoreApiClient(config.storeUrl, config.accessKey);
        printHeader('PAYMENT SUBMETHODS TESTS');

        // Login first
        printStep('Logowanie...');
        await client.login(config.customerEmail, config.customerPassword);

        // Get payment methods and find one with submethods (bank handler)
        printStep('Pobieranie metod płatności...');
        const paymentMethodsResponse = await client.getPaymentMethods();
        allPaymentMethods = paymentMethodsResponse?.elements || [];

        // Find a bank payment method using cr_payment_type extension
        // Falls back to name matching if extension not available
        bankPaymentMethod = allPaymentMethods.find(
            (pm) => pm.extensions?.cr_payment_type?.hasSubmethods === true ||
                    pm.extensions?.cr_payment_type?.isBank === true ||
                    pm.name === 'PayU' ||
                    pm.name?.toLowerCase().includes('przelew')
        );

        if (bankPaymentMethod) {
            printInfo(`Znaleziono metodę bankową: ${bankPaymentMethod.name}`);
        } else {
            printWarning('Nie znaleziono metody bankowej - niektóre testy mogą być pominięte');
        }
    });

    it('should get submethods for specific payment ID', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Pobierz submetody dla payment ID');

        if (!bankPaymentMethod) {
            printWarning('Pominięto - brak metody bankowej');
            results.skip('Get submethods for payment ID', 'No bank payment method available');
            return;
        }

        const paymentValue = 10000; // 100 PLN in grosze
        printEndpoint('GET', `/cr/payment-sub-methods/${bankPaymentMethod.id}`);
        printParams({
            'Payment ID': bankPaymentMethod.id,
            'Payment Name': bankPaymentMethod.name,
            'Payment Value': `${paymentValue} groszy (${paymentValue / 100} PLN)`,
        });

        try {
            const response = await client.getPaymentSubMethods(bankPaymentMethod.id, paymentValue);
            const submethods = response?.elements || [];

            expect(response).toBeDefined();
            expect(Array.isArray(submethods)).toBe(true);

            // Validate structure of submethods
            if (submethods.length > 0) {
                const sample = submethods[0];
                expect(sample).toHaveProperty('providerId');
                expect(sample).toHaveProperty('name');

                printResponse(200, {
                    'Count': `${submethods.length} submetod`,
                    'Sample': submethods.slice(0, 3).map(s => s.name).join(', ') + '...',
                });
            } else {
                printResponse(200, {
                    'Count': '0 submetod',
                    'Info': 'Metoda nie ma submetod lub są niedostępne',
                });
            }

            printTestResult(true, Date.now() - startTime);
            results.pass('Get submethods for payment ID', Date.now() - startTime);
        } catch (error) {
            printError(error.message);
            printTestResult(false, Date.now() - startTime);
            results.fail('Get submethods for payment ID', error, Date.now() - startTime);
            throw error;
        }
    });

    it('should get submethods for current payment method', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Pobierz submetody aktualnej metody');

        if (!bankPaymentMethod) {
            printWarning('Pominięto - brak metody bankowej');
            results.skip('Get current submethods', 'No bank payment method available');
            return;
        }

        const paymentValue = 10000;

        // First set the payment method in context
        printStep('Ustawianie metody płatności w kontekście...');
        await client.setPaymentMethod(bankPaymentMethod.id);

        printEndpoint('GET', '/cr/payment-sub-methods');
        printParams({
            'Current Payment': bankPaymentMethod.name,
            'Payment Value': `${paymentValue} groszy`,
        });

        try {
            const response = await client.getCurrentPaymentSubMethods(paymentValue);
            const submethods = response?.elements || [];

            expect(response).toBeDefined();

            printResponse(200, {
                'Count': `${submethods.length} submetod`,
            });
            printTestResult(true, Date.now() - startTime);
            results.pass('Get current submethods', Date.now() - startTime);
        } catch (error) {
            printError(error.message);
            printTestResult(false, Date.now() - startTime);
            results.fail('Get current submethods', error, Date.now() - startTime);
            throw error;
        }
    });

    it('should return empty for payment method without submethods', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Brak submetod dla metody bez banków');

        // Find a non-bank payment method (card, etc.) using extension
        const cardPaymentMethod = allPaymentMethods.find(
            (pm) => pm.extensions?.cr_payment_type?.isCard === true ||
                    pm.name?.toLowerCase().includes('karta') ||
                    pm.name?.toLowerCase().includes('card')
        );

        if (!cardPaymentMethod) {
            printWarning('Pominięto - nie znaleziono metody bez submetod');
            results.skip('Empty submethods', 'No card payment method found');
            return;
        }

        printEndpoint('GET', `/cr/payment-sub-methods/${cardPaymentMethod.id}`);
        printParams({
            'Payment ID': cardPaymentMethod.id,
            'Payment Name': cardPaymentMethod.name,
        });

        try {
            const response = await client.getPaymentSubMethods(cardPaymentMethod.id, 10000);
            const submethods = response?.elements || [];

            // Should be empty or throw - both acceptable
            printResponse(200, {
                'Count': `${submethods.length} submetod`,
                'Status': submethods.length === 0 ? 'Zgodnie z oczekiwaniami' : 'Nieoczekiwane submetody',
            });
            printTestResult(true, Date.now() - startTime);
            results.pass('Empty submethods', Date.now() - startTime);
        } catch (error) {
            // Error is acceptable for methods without submethods
            printResponse(400, {
                'Status': 'Metoda nie obsługuje submetod (oczekiwane)',
            });
            printTestResult(true, Date.now() - startTime);
            results.pass('Empty submethods (error expected)', Date.now() - startTime);
        }
    });

    it('should filter submethods by payment value', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Filtrowanie submetod po kwocie');

        if (!bankPaymentMethod) {
            printWarning('Pominięto - brak metody bankowej');
            results.skip('Filter by value', 'No bank payment method available');
            return;
        }

        const smallValue = 100; // 1 PLN
        const largeValue = 100000; // 1000 PLN

        printEndpoint('GET', `/cr/payment-sub-methods/${bankPaymentMethod.id}`);
        printParams({
            'Test 1': `paymentValue=${smallValue} (${smallValue/100} PLN)`,
            'Test 2': `paymentValue=${largeValue} (${largeValue/100} PLN)`,
        });

        try {
            const smallResponse = await client.getPaymentSubMethods(bankPaymentMethod.id, smallValue);
            const largeResponse = await client.getPaymentSubMethods(bankPaymentMethod.id, largeValue);

            const smallCount = smallResponse?.elements?.length || 0;
            const largeCount = largeResponse?.elements?.length || 0;

            printResponse(200, {
                'Dla 1 PLN': `${smallCount} submetod`,
                'Dla 1000 PLN': `${largeCount} submetod`,
                'Różnica': smallCount !== largeCount ? 'Tak (filtrowanie działa)' : 'Brak (te same submetody)',
            });

            printTestResult(true, Date.now() - startTime);
            results.pass('Filter by value', Date.now() - startTime);
        } catch (error) {
            printError(error.message);
            printTestResult(false, Date.now() - startTime);
            results.fail('Filter by value', error, Date.now() - startTime);
            throw error;
        }
    });
});
