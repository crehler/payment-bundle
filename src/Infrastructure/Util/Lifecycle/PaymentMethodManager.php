<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle;

use Crehler\PaymentBundle\Application\Service\CurrencyRuleService;
use Crehler\PaymentBundle\Infrastructure\Enum\{InstallClasses, PaymentDirectoriesPathEnum};
use Crehler\PaymentBundle\Infrastructure\Repository\{CurrencyRepository, RuleRepository};
use Crehler\PaymentBundle\Infrastructure\Repository\Install\PluginPaymentMethodsRepository;
use Crehler\PaymentBundle\Infrastructure\Util\{PaymentMethodDataMapper, PaymentMethodMediaManager};
use ReflectionException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

use function array_map;
use function count;
use function in_array;

final class PaymentMethodManager
{
    use PluginHelperAwareTrait;

    private EntityRepository $ruleRepository;
    private EntityRepository $currencyRepository;
    private EntityRepository $languageRepository;
    private CurrencyRuleService $currencyRuleService;
    private PaymentMethodMediaManager $paymentMediaManager;
    private PluginPaymentMethodsRepository $pluginPaymentMethodsRepository;

    public function __construct(private readonly ContainerInterface $container)
    {
        $this->ruleRepository = $this->container->get('rule.repository');
        $this->currencyRepository = $this->container->get('currency.repository');
        $this->languageRepository = $this->container->get('language.repository');
        $this->currencyRuleService = $this->initializeCurrencyService(Context::createDefaultContext());
        $this->paymentMediaManager = new PaymentMethodMediaManager(
            $this->container->get('media.repository'),
            $this->container->get('media_folder.repository'),
            $this->container->get('payment_method.repository'),
            $this->container->get(FileSaver::class),
            $this->getPluginHelper()
        );
        $this->pluginPaymentMethodsRepository = new PluginPaymentMethodsRepository($this->container->get('payment_method.repository'));
    }

    /**
     * @throws ReflectionException
     */
    public function create(Context $context, string $pluginId, string $baseClass): void
    {
        $this->upsert(
            context: $context,
            pluginId: $pluginId,
            baseClass: $baseClass,
            paymentMethods: $this->getPaymentMethodClassLocator()->getMethods(
                baseClass: $baseClass,
                pathToFolder: PaymentDirectoriesPathEnum::PAYMENT_METHODS
            )
        );
    }

    /**
     * Add new payment methods that not already exist.
     */
    public function update(Context $context, string $pluginId, string $baseClass): void
    {
        $methods = $this->getPaymentMethodClassLocator()->getMethods(
            baseClass: $baseClass,
            pathToFolder: PaymentDirectoriesPathEnum::PAYMENT_METHODS
        );

        $methodsTechnicalNames = array_map(fn (ShopwarePaymentMethod $method) => $method->technicalName, $methods);

        $existMethodsTechnicalNames = array_map(fn (PaymentMethodEntity $method) => $method->getTechnicalName(),
            $this->pluginPaymentMethodsRepository->getPluginPaymentMethods(
                pluginId: $pluginId,
                context: $context,
                methodsTechnicalNames: $methodsTechnicalNames)->getElements()
        );

        foreach ($methods as $key => $method) {
            if (in_array($method->technicalName, $existMethodsTechnicalNames)) {
                unset($methods[$key]);
            }
        }

        $this->upsert(context: $context, pluginId: $pluginId, baseClass: $baseClass, paymentMethods: $methods);
    }

    public function activate(Context $context, string $pluginId, string $baseClass): void
    {
        $methods = $this->getPaymentMethodClassLocator()->getMethods(
            baseClass: $baseClass,
            pathToFolder: PaymentDirectoriesPathEnum::PAYMENT_METHODS
        );

        $this->pluginPaymentMethodsRepository->setPaymentMethodsActiveState(
            context: $context,
            methods: $methods,
            pluginId: $pluginId,
            active: true
        );
    }

    public function deactivate(Context $context, string $pluginId, string $baseClass): void
    {
        $methods = $this->getPaymentMethodClassLocator()->getMethods(
            baseClass: $baseClass,
            pathToFolder: PaymentDirectoriesPathEnum::PAYMENT_METHODS
        );

        $this->pluginPaymentMethodsRepository->setPaymentMethodsActiveState(
            context: $context,
            methods: $methods,
            pluginId: $pluginId,
            active: false
        );
    }

    protected function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    private function upsert(Context $context, string $pluginId, string $baseClass, array $paymentMethods): void
    {
        $methodsToUpsert = $this->pluginPaymentMethodsRepository->getPaymentMethodsToUpsert(
            paymentMethods: $paymentMethods,
            context: $context
        );

        $pluginNamespace = $this->getPluginHelper()->getPluginNamespaceRoot(pluginClass: $baseClass);

        $ruleClass = $pluginNamespace . InstallClasses::PATH->value . InstallClasses::SUPPORT_CURRENCY_CLASS->value;
        $ruleId = $this->currencyRuleService->createOrUpdateCurrencyRule(new $ruleClass());

        $dataMapper = new PaymentMethodDataMapper($this->languageRepository, $this->paymentMediaManager, $baseClass);
        $upsertData = $dataMapper->map($paymentMethods, $methodsToUpsert, $pluginId, $ruleId, $context);

        if (count($upsertData) === 0) {
            return;
        }

        $this->pluginPaymentMethodsRepository->upsert($upsertData, $context);
    }

    private function initializeCurrencyService(Context $context): CurrencyRuleService
    {
        return new CurrencyRuleService(
            new CurrencyRepository(
                $this->currencyRepository,
                $context
            ),
            new RuleRepository(
                $this->ruleRepository,
                $context
            ),
        );
    }
}
