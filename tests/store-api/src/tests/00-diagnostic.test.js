/**
 * Test diagnostyczny - sprawdza dostępne metody płatności
 *
 * Uruchom osobno: npm test -- src/tests/00-diagnostic.test.js
 */

import { describe, it, beforeAll } from 'vitest';
import { StoreApiClient } from '../store-api-client.js';
import { validateConfig } from '../config.js';
import { printHeader, printInfo, printStep } from '../utils/logger.js';

describe('Diagnostic Tests', () => {
    let client;
    let config;

    beforeAll(async () => {
        config = validateConfig();
        client = new StoreApiClient(config.storeUrl, config.accessKey);
        printHeader('DIAGNOSTIC TESTS');

        printStep('Logowanie...');
        await client.login(config.customerEmail, config.customerPassword);
    });

    it('should list all payment methods with handlers', async () => {
        printStep('Pobieranie metod płatności...');
        const response = await client.getPaymentMethods();
        const methods = response?.elements || [];

        console.log('\n');
        printInfo(`Znaleziono ${methods.length} metod płatności:\n`);

        methods.forEach((pm, index) => {
            console.log(`  ${index + 1}. ${pm.name}`);
            console.log(`     ID: ${pm.id}`);
            console.log(`     Handler: ${pm.handlerIdentifier || 'N/A'}`);
            console.log(`     Technical: ${pm.technicalName || 'N/A'}`);
            console.log(`     Active: ${pm.active}`);

            // Show crPaymentType extension if available (check multiple locations)
            const ext = pm.extensions?.crPaymentType || pm.crPaymentType || pm.extensions?.cr_payment_type;
            if (ext) {
                const flags = [];
                if (ext.isBlik) flags.push('BLIK');
                if (ext.isCard) flags.push('Card');
                if (ext.isBank) flags.push('Bank');
                if (ext.isEwallet) flags.push('Ewallet');
                if (ext.isDeferred) flags.push('Deferred');
                if (ext.hasSubmethods) flags.push('HasSubmethods');
                if (ext.isCrehlerPayment) flags.push('Crehler');
                console.log(`     Type: ${flags.length > 0 ? flags.join(', ') : 'Generic'}`);
            } else {
                // Debug: show what extensions are available
                const extKeys = pm.extensions ? Object.keys(pm.extensions) : [];
                console.log(`     Type: (no extension) [available: ${extKeys.join(', ') || 'none'}]`);
            }
            console.log('');
        });

        // Categorize by handler type
        console.log('\n--- Kategorie handlerów ---\n');

        const categories = {
            blik: methods.filter(pm => pm.handlerIdentifier?.toLowerCase().includes('blik')),
            bank: methods.filter(pm => pm.handlerIdentifier?.toLowerCase().includes('bank')),
            card: methods.filter(pm => pm.handlerIdentifier?.toLowerCase().includes('card')),
            crehler: methods.filter(pm => pm.handlerIdentifier?.includes('Crehler')),
            payu: methods.filter(pm => pm.handlerIdentifier?.toLowerCase().includes('payu')),
            paynow: methods.filter(pm => pm.handlerIdentifier?.toLowerCase().includes('paynow')),
        };

        Object.entries(categories).forEach(([category, items]) => {
            if (items.length > 0) {
                console.log(`  ${category.toUpperCase()}: ${items.length} metod`);
                items.forEach(pm => console.log(`    - ${pm.name} (${pm.handlerIdentifier})`));
            }
        });

        // List unique handlers
        console.log('\n--- Unikalne handlery ---\n');
        const uniqueHandlers = [...new Set(methods.map(pm => pm.handlerIdentifier).filter(Boolean))];
        uniqueHandlers.forEach(handler => {
            console.log(`  ${handler}`);
        });
    });

    it('should check if product exists', async () => {
        printStep(`Szukanie produktu: ${config.productNumber}...`);

        try {
            const product = await client.findProductByNumber(config.productNumber);
            console.log('\n');
            printInfo('Produkt znaleziony:');
            console.log(`  Nazwa: ${product.name}`);
            console.log(`  ID: ${product.id}`);
            console.log(`  Numer: ${product.productNumber}`);
            console.log(`  Cena: ${product.calculatedPrice?.totalPrice || 'N/A'} PLN`);
        } catch (error) {
            console.log(`\n  ❌ Produkt nie znaleziony: ${error.message}`);
        }

        // List available products
        printStep('Szukanie dostępnych produktów...');
        try {
            const response = await client.searchProducts({ limit: 10 });
            const products = response?.elements || [];

            console.log(`\n  Znaleziono ${products.length} produktów:`);
            products.forEach((p, i) => {
                console.log(`    ${i + 1}. ${p.name} (${p.productNumber}) - ${p.calculatedPrice?.totalPrice || '?'} PLN`);
            });
        } catch (error) {
            console.log(`\n  ❌ Błąd wyszukiwania produktów: ${error.message}`);
        }
    });

    it('should check submethods for each payment method', async () => {
        printStep('Sprawdzanie submetod...');
        const response = await client.getPaymentMethods();
        const methods = response?.elements || [];

        console.log('\n');

        for (const pm of methods) {
            try {
                const submethodsResponse = await client.getPaymentSubMethods(pm.id, 10000);
                const submethods = submethodsResponse?.elements || [];

                if (submethods.length > 0) {
                    console.log(`  ${pm.name}: ${submethods.length} submetod`);
                    submethods.slice(0, 3).forEach(sub => {
                        console.log(`    - ${sub.name} (${sub.providerId})`);
                    });
                    if (submethods.length > 3) {
                        console.log(`    ... i ${submethods.length - 3} więcej`);
                    }
                }
            } catch {
                // Skip methods without submethods
            }
        }
    });
});
