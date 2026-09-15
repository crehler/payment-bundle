<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Subscriber;

use Crehler\PaymentBundle\Application\Port\Driven\CardFormTemplateProviderPort;
use Crehler\PaymentBundle\Domain\Enum\PaymentType;
use Crehler\PaymentBundle\Infrastructure\Configuration\PaymentBundleConfigService;
use Crehler\PaymentBundle\Infrastructure\Port\ConsentProvider;
use Crehler\PaymentBundle\Infrastructure\Resolver\PaymentMethodContractResolver;
use Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSavedCard\Abstract\AbstractSavedCardTokenRoute;
use Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSavedCard\RouteSavedCardTokenRoute;
use Crehler\PaymentBundle\Infrastructure\Struct\{CheckoutConfirmPageExtensionStruct, ConsentStruct};
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\DependencyInjection\Attribute\{Autoconfigure, Autowire, AutowireIterator};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Universal checkout page subscriber: the handler contract, saved cards, consent and the
 * bundle's display config.
 *
 * Deliberately does no gateway I/O — see addPaymentMethodExtensions().
 */
#[Autoconfigure(tags: [['name' => 'kernel.event_subscriber']])]
final readonly class CheckoutConfirmPageLoadedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: RouteSavedCardTokenRoute::class)]
        private AbstractSavedCardTokenRoute $cardTokenRoute,
        private PaymentBundleConfigService $bundleConfigService,
        private PaymentMethodContractResolver $contractResolver,
        #[AutowireIterator(ConsentProvider::class)]
        private iterable $consentProviders,
        #[AutowireIterator(CardFormTemplateProviderPort::class)]
        private iterable $cardFormTemplateProviders,
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

        // Card form is provider-specific and rendered by path, not by overriding a shared
        // Twig block — see CardFormTemplateProviderPort for why the block never worked.
        if ($checkoutExtension->isCardPayment && $embedCardForm) {
            $checkoutExtension = $checkoutExtension->withCardFormTemplate(
                $this->resolveCardFormTemplate($currentPaymentMethod->getHandlerIdentifier()),
            );
        }

        // Add extensions to each payment method
        foreach ($page->getPaymentMethods()->getElements() as $payMethod) {
            $this->addPaymentMethodExtensions(paymentMethod: $payMethod, context: $context);
        }

        $page->addExtension(name: $checkoutExtension->getApiAlias(), extension: $checkoutExtension);
    }

    /**
     * Page-level flags for the selected method. Only BLIK and card need one: BLIK to place
     * the code field, card to resolve the embedded form. The sub-method selector no longer
     * appears here — it asks the method's own contract instead of a page-wide flag named
     * after one of the three families it used to cover.
     */
    private function createCheckoutExtension(PaymentMethodEntity $paymentMethod): CheckoutConfirmPageExtensionStruct
    {
        $contract = $this->contractResolver->resolve($paymentMethod);

        return match ($contract?->type) {
            PaymentType::CARD => new CheckoutConfirmPageExtensionStruct(isCardPayment: true),
            PaymentType::BLIK => new CheckoutConfirmPageExtensionStruct(isBlikPayment: true),
            default => new CheckoutConfirmPageExtensionStruct(),
        };
    }

    private function resolveCardFormTemplate(?string $handlerIdentifier): ?string
    {
        if ($handlerIdentifier === null) {
            return null;
        }

        foreach ($this->cardFormTemplateProviders as $provider) {
            if ($provider->supports($handlerIdentifier)) {
                return $provider->getTemplate();
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

    /**
     * Channels are NOT fetched here any more.
     *
     * This loop runs once per payment method on every checkout render, and the channel
     * lookup inside it was an HTTP call to the gateway: at ING four identical POSTs to
     * get-payment-methods per render, because four of its methods draw channels from the
     * same list. A slow gateway multiplied straight into checkout TTFB and a dead one took
     * the page with it. The storefront controller loads them over AJAX now
     * (frontend.cr.payment.sub-methods) and renders the same Twig partial.
     *
     * Saved cards stay: that is a local DAL read, not a gateway call.
     */
    private function addPaymentMethodExtensions(
        PaymentMethodEntity $paymentMethod,
        SalesChannelContext $context,
    ): void {
        $customerSavedCardTokens = $this->cardTokenRoute->getCustomerCardTokens(
            paymentMethod: $paymentMethod,
            context: $context
        );

        $this->addPaymentExtension(paymentMethod: $paymentMethod, extension: $customerSavedCardTokens);
    }

    private function addPaymentExtension(PaymentMethodEntity $paymentMethod, object $extension): void
    {
        if ($extension instanceof Collection && $extension->getElements() === []) {
            return;
        }

        $paymentMethod->addExtension(name: $extension->getApiAlias(), extension: $extension->get());
    }
}
