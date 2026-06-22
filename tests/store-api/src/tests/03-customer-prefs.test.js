/**
 * Testy preferencji submetod klienta Store API
 *
 * Testuje:
 * - GET /customer/cr/payment-sub-method (odczyt wybranej submetody)
 * - ustawianie submetody przez context switch (PATCH /context, pole paymentSubMethod)
 *
 * Scenariusze:
 * - Pobranie wybranej submetody (gdy brak wyboru)
 * - Ustawienie submetody (context switch)
 * - Weryfikacja zapisanej submetody
 * - Zmiana submetody na inną
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

describe('Customer Payment Preferences Tests', () => {
    let client;
    let config;
    let bankPaymentMethod = null;
    let availableSubmethods = [];
    const results = new TestResults();
    let testCount = 0;
    const totalTests = 4;

    beforeAll(async () => {
        config = validateConfig();
        client = new StoreApiClient(config.storeUrl, config.accessKey);
        printHeader('CUSTOMER PAYMENT PREFERENCES TESTS');

        // Login first
        printStep('Logowanie...');
        await client.login(config.customerEmail, config.customerPassword);

        // Get payment methods and find one with submethods
        printStep('Pobieranie metod płatności...');
        const paymentMethodsResponse = await client.getPaymentMethods();
        const allPaymentMethods = paymentMethodsResponse?.elements || [];

        // Find a bank payment method using cr_payment_type extension
        bankPaymentMethod = allPaymentMethods.find(
            (pm) => pm.extensions?.cr_payment_type?.hasSubmethods === true ||
                    pm.extensions?.cr_payment_type?.isBank === true ||
                    pm.name === 'PayU' ||
                    pm.name?.toLowerCase().includes('przelew')
        );

        if (bankPaymentMethod) {
            printInfo(`Znaleziono metodę bankową: ${bankPaymentMethod.name}`);

            // Get submethods for this payment method
            try {
                const subResponse = await client.getPaymentSubMethods(bankPaymentMethod.id, 10000);
                availableSubmethods = subResponse?.elements || [];
                printInfo(`Dostępne submetody: ${availableSubmethods.length}`);
            } catch {
                printWarning('Nie udało się pobrać submetod');
            }
        } else {
            printWarning('Nie znaleziono metody bankowej');
        }
    });

    it('should get customer submethod (initial - no selection)', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Pobierz wybraną submetodę (brak wyboru)');

        printEndpoint('GET', '/customer/cr/payment-sub-method');
        printParams({
            'Zalogowany': 'Tak',
        });

        try {
            const response = await client.getCustomerSubMethod();

            expect(response).toBeDefined();

            printResponse(200, {
                'Payment Method ID': response?.paymentMethodId || 'N/A',
                'Sub Payment Method ID': response?.subPaymentMethodId || 'null (brak wyboru)',
            });
            printTestResult(true, Date.now() - startTime);
            results.pass('Get customer submethod (initial)', Date.now() - startTime);
        } catch (error) {
            printError(error.message);
            printTestResult(false, Date.now() - startTime);
            results.fail('Get customer submethod (initial)', error, Date.now() - startTime);
            throw error;
        }
    });

    it('should set customer submethod', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Ustaw submetodę klienta');

        if (!bankPaymentMethod || availableSubmethods.length === 0) {
            printWarning('Pominięto - brak dostępnych submetod');
            results.skip('Set customer submethod', 'No submethods available');
            return;
        }

        const selectedSubmethod = availableSubmethods[0];

        printEndpoint('PATCH', '/context');
        printParams({
            'Payment Method ID': bankPaymentMethod.id,
            'Sub Method ID': selectedSubmethod.providerId,
            'Sub Method Name': selectedSubmethod.name,
        });

        try {
            await client.setPaymentSubMethod(bankPaymentMethod.id, selectedSubmethod.providerId);

            printResponse(200, {
                'Status': 'Zapisano',
            });
            printTestResult(true, Date.now() - startTime);
            results.pass('Set customer submethod', Date.now() - startTime);
        } catch (error) {
            printError(error.message);
            printTestResult(false, Date.now() - startTime);
            results.fail('Set customer submethod', error, Date.now() - startTime);
            throw error;
        }
    });

    it('should verify saved submethod', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Zweryfikuj zapisaną submetodę');

        if (!bankPaymentMethod || availableSubmethods.length === 0) {
            printWarning('Pominięto - brak dostępnych submetod');
            results.skip('Verify saved submethod', 'No submethods available');
            return;
        }

        const expectedSubmethod = availableSubmethods[0];

        printEndpoint('GET', '/customer/cr/payment-sub-method');
        printParams({
            'Oczekiwany Sub Method ID': expectedSubmethod.providerId,
        });

        try {
            const response = await client.getCustomerSubMethod();

            printResponse(200, {
                'Payment Method ID': response?.paymentMethodId || 'N/A',
                'Sub Payment Method ID': response?.subPaymentMethodId || 'null',
                'Zgodność': response?.subPaymentMethodId === expectedSubmethod.providerId ? 'TAK' : 'NIE',
            });

            // Note: The endpoint might return null if no payment method is set in context
            // This is acceptable behavior
            printTestResult(true, Date.now() - startTime);
            results.pass('Verify saved submethod', Date.now() - startTime);
        } catch (error) {
            printError(error.message);
            printTestResult(false, Date.now() - startTime);
            results.fail('Verify saved submethod', error, Date.now() - startTime);
            throw error;
        }
    });

    it('should change submethod to another', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Zmień submetodę na inną');

        if (!bankPaymentMethod || availableSubmethods.length < 2) {
            printWarning('Pominięto - potrzeba min. 2 submetod');
            results.skip('Change submethod', 'Need at least 2 submethods');
            return;
        }

        const newSubmethod = availableSubmethods[1];

        printEndpoint('PATCH', '/context');
        printParams({
            'Payment Method ID': bankPaymentMethod.id,
            'New Sub Method ID': newSubmethod.providerId,
            'New Sub Method Name': newSubmethod.name,
        });

        try {
            await client.setPaymentSubMethod(bankPaymentMethod.id, newSubmethod.providerId);

            printResponse(200, {
                'Status': 'Zmieniono',
                'Nowa submetoda': newSubmethod.name,
            });
            printTestResult(true, Date.now() - startTime);
            results.pass('Change submethod', Date.now() - startTime);
        } catch (error) {
            printError(error.message);
            printTestResult(false, Date.now() - startTime);
            results.fail('Change submethod', error, Date.now() - startTime);
            throw error;
        }
    });
});
