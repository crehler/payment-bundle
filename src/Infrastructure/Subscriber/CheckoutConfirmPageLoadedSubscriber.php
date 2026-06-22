<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Subscriber;

use Crehler\PaymentBundle\Infrastructure\Configuration\PaymentBundleConfigService;
use Crehler\PaymentBundle\Infrastructure\Enum\PaymentHandlersEnum;
use Crehler\PaymentBundle\Infrastructure\Port\ConsentProvider;
use Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSavedCard\Abstract\AbstractSavedCardTokenRoute;
use Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSavedCard\RouteSavedCardTokenRoute;
use Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSubMethods\Abstract\AbstractCustomerPaymentSubMethodRoute;
use Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSubMethods\CustomerPaymentSubMethodRoute;
use Crehler\PaymentBundle\Infrastructure\StoreApi\PaymentSubMethods\Abstract\AbstractPaymentSubMethodRoute;
use Crehler\PaymentBundle\Infrastructure\StoreApi\PaymentSubMethods\PaymentSubMethodRoute;
use Crehler\PaymentBundle\Infrastructure\Struct\{CheckoutConfirmPageExtensionStruct, ConsentStruct, PaymentMethodTypeStruct};
use Crehler\PaymentBundle\Shared\AmountFormat;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Order\{AccountEditOrderPage, AccountEditOrderPageLoadedEvent};
use Shopware\Storefront\Page\Checkout\Confirm\{CheckoutConfirmPage, CheckoutConfirmPageLoadedEvent};
use Symfony\Component\DependencyInjection\Attribute\{Autoconfigure, Autowire, AutowireIterator};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function str_ends_with;
use function str_starts_with;
use function strtolower;

/**
 * Universal checkout page subscriber.
 * Handles payment sub-methods, card tokens, and consent for all payment providers.
 */
#[Autoconfigure(tags: [['name' => 'kernel.event_subscriber']])]
final readonly class CheckoutConfirmPageLoadedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: PaymentSubMethodRoute::class)]
        private AbstractPaymentSubMethodRoute $subPaymentMethodsRoute,
        #[Autowire(service: CustomerPaymentSubMethodRoute::class)]
        private AbstractCustomerPaymentSubMethodRoute $getCustomerPaymentSubMethodRoute,
        #[Autowire(service: RouteSavedCardTokenRoute::class)]
        private AbstractSavedCardTokenRoute $cardTokenRoute,
        private AmountFormat $amountFormat,
        private PaymentBundleConfigService $bundleConfigService,
        #[AutowireIterator(ConsentProvider::class)]
        private iterable $consentProviders,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => ['onCheckoutConfirmPageLoaded', 999],
            AccountEditOrderPageLoadedEvent::class => ['onAccountEditOrderPageLoadedEvent', 999],
        ];
    }

    public function onCheckoutConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $this->setCheckoutExtensions(event: $event);
    }

    public function onAccountEditOrderPageLoadedEvent(AccountEditOrderPageLoadedEvent $event): void
    {
        $this->setCheckoutExtensions(event: $event);
    }

    private function setCheckoutExtensions(CheckoutConfirmPageLoadedEvent|AccountEditOrderPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $context = $event->getSalesChannelContext();
        $currentPaymentMethod = $context->getPaymentMethod();
        $salesChannelId = $context->getSalesChannelId();

        // Determine payment type based on handler
        $checkoutExtension = $this->createCheckoutExtension(paymentMethod: $currentPaymentMethod);

        // Get consent from providers for current payment method
        $consent = $this->getConsentForPaymentMethod(
            paymentMethod: $currentPaymentMethod,
            context: $context
        );

        if ($consent !== null) {
            $checkoutExtension = $checkoutExtension->withConsent($consent);
        }

        // Add bundle configuration (embedCardForm, blikInputPosition)
        $embedCardForm = $this->bundleConfigService->isEmbedCardFormEnabled($currentPaymentMethod, $salesChannelId);
        $blikInputPosition = $this->bundleConfigService->getBlikInputPosition($currentPaymentMethod, $salesChannelId);
        $checkoutExtension = $checkoutExtension->withBundleConfig($embedCardForm, $blikInputPosition);

        // Add extensions to each payment method
        foreach ($page->getPaymentMethods()->getElements() as $payMethod) {
            $this->addPaymentMethodExtensions(
                paymentMethod: $payMethod,
                page: $page,
                context: $context
            );
        }

        $page->addExtension(name: $checkoutExtension->getApiAlias(), extension: $checkoutExtension);
    }

    private function createCheckoutExtension(PaymentMethodEntity $paymentMethod): CheckoutConfirmPageExtensionStruct
    {
        $handlerType = $this->resolveHandlerType($paymentMethod->getHandlerIdentifier());

        return match ($handlerType) {
            PaymentHandlersEnum::BANK_HANDLER,
            PaymentHandlersEnum::DEFERED_HANDLER,
            PaymentHandlersEnum::EWALLET_HANDLER => new CheckoutConfirmPageExtensionStruct(isBankPayment: true),
            PaymentHandlersEnum::CARD_HANDLER => new CheckoutConfirmPageExtensionStruct(isCardPayment: true),
            PaymentHandlersEnum::BLIK_HANDLER => new CheckoutConfirmPageExtensionStruct(isBlikPayment: true),
            default => new CheckoutConfirmPageExtensionStruct(),
        };
    }

    private function resolveHandlerType(?string $handlerIdentifier): ?PaymentHandlersEnum
    {
        if ($handlerIdentifier === null) {
            return null;
        }

        foreach (PaymentHandlersEnum::cases() as $handlerEnum) {
            if (str_ends_with(strtolower($handlerIdentifier), $handlerEnum->value)) {
                return $handlerEnum;
            }
        }

        return null;
    }

    private function getConsentForPaymentMethod(
        PaymentMethodEntity $paymentMethod,
        SalesChannelContext $context,
    ): ?ConsentStruct {
        foreach ($this->consentProviders as $provider) {
            if ($provider->supportsPaymentMethod($paymentMethod)) {
                return $provider->getConsent($paymentMethod, $context);
            }
        }

        return null;
    }

    private function addPaymentMethodExtensions(
        PaymentMethodEntity $paymentMethod,
        CheckoutConfirmPage|AccountEditOrderPage $page,
        SalesChannelContext $context,
    ): void {
        $paymentValue = $this->getPaymentValue(page: $page);

        $paymentSubMethods = $this->subPaymentMethodsRoute->getForPayment(
            paymentId: $paymentMethod->getId(),
            paymentValue: $paymentValue,
            context: $context
        );

        $customerSelectedSubMethod = $this->getCustomerPaymentSubMethodRoute->get(
            paymentMethodEntity: $paymentMethod,
            context: $context
        );

        $customerSavedCardTokens = $this->cardTokenRoute->getCustomerCardTokens(
            paymentMethod: $paymentMethod,
            context: $context
        );

        // Add payment type struct for frontend data attributes
        $paymentTypeStruct = $this->createPaymentTypeStruct($paymentMethod);

        $this->addPaymentExtension(paymentMethod: $paymentMethod, extension: $paymentSubMethods);
        $this->addPaymentExtension(paymentMethod: $paymentMethod, extension: $customerSelectedSubMethod);
        $this->addPaymentExtension(paymentMethod: $paymentMethod, extension: $customerSavedCardTokens);
        $paymentMethod->addExtension($paymentTypeStruct->getApiAlias(), $paymentTypeStruct);
    }

    private function createPaymentTypeStruct(PaymentMethodEntity $paymentMethod): PaymentMethodTypeStruct
    {
        $handlerIdentifier = $paymentMethod->getHandlerIdentifier();
        $handlerType = $this->resolveHandlerType($handlerIdentifier);

        return new PaymentMethodTypeStruct(
            isBlik: $handlerType === PaymentHandlersEnum::BLIK_HANDLER,
            isCard: $handlerType === PaymentHandlersEnum::CARD_HANDLER,
            isBank: $handlerType === PaymentHandlersEnum::BANK_HANDLER,
            isEwallet: $handlerType === PaymentHandlersEnum::EWALLET_HANDLER,
            isDeferred: $handlerType === PaymentHandlersEnum::DEFERED_HANDLER,
            hasSubmethods: $this->hasSubmethods($handlerType),
            isCrehlerPayment: $this->isCrehlerPaymentMethod($handlerIdentifier),
        );
    }

    private function isCrehlerPaymentMethod(?string $handlerIdentifier): bool
    {
        if ($handlerIdentifier === null) {
            return false;
        }

        return str_starts_with($handlerIdentifier, 'Crehler\\');
    }

    private function hasSubmethods(?PaymentHandlersEnum $handlerType): bool
    {
        if ($handlerType === null) {
            return false;
        }

        return match ($handlerType) {
            PaymentHandlersEnum::BANK_HANDLER,
            PaymentHandlersEnum::EWALLET_HANDLER,
            PaymentHandlersEnum::DEFERED_HANDLER => true,
            default => false,
        };
    }

    private function addPaymentExtension(PaymentMethodEntity $paymentMethod, object $extension): void
    {
        if ($extension instanceof Collection && $extension->getElements() === []) {
            return;
        }

        $paymentMethod->addExtension(name: $extension->getApiAlias(), extension: $extension->get());
    }

    private function getPaymentValue(CheckoutConfirmPage|AccountEditOrderPage $page): int
    {
        if ($page instanceof AccountEditOrderPage) {
            return $this->amountFormat->floatToInt($page->getOrder()->getAmountTotal());
        }

        if ($page instanceof CheckoutConfirmPage) {
            return $this->amountFormat->floatToInt($page->getCart()->getPrice()->getTotalPrice());
        }

        return 0;
    }
}
