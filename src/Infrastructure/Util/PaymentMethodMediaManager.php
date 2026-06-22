<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util;

use Crehler\PaymentBundle\Infrastructure\Enum\PaymentDirectoriesPathEnum;
use Shopware\Core\Content\Media\File\{FileSaver, MediaFile};
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

use function filesize;
use function is_dir;
use function is_file;
use function pathinfo;
use function rtrim;
use function strtolower;

final readonly class PaymentMethodMediaManager
{
    private const ALLOWED_ICON_EXTENSIONS = ['png', 'svg', 'jpg', 'jpeg', 'webp'];

    public function __construct(
        private EntityRepository $mediaRepository,
        private EntityRepository $mediaFolderRepository,
        private EntityRepository $paymentMethodRepository,
        private FileSaver $fileSaver,
        private PluginHelper $pluginHelper,
    ) {
    }

    public function getPaymentMethodIcon(
        string $paymentMethodId,
        string $iconName,
        string $baseClass,
        Context $context,
    ): ?string {
        $mediaId = $this->getMediaId($paymentMethodId, $context);
        if ($mediaId !== null) {
            return $mediaId;
        }

        $baseName = pathinfo($iconName, PATHINFO_FILENAME) ?: $iconName;
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $baseName));
        $existingMedia = $this->mediaRepository->search($criteria, $context)->first();

        if ($existingMedia) {
            return $existingMedia->getId();
        }

        $mediaId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'private' => false,
                ],
            ],
            $context
        );

        $pluginDir = $this->pluginHelper->getPluginDirectory($baseClass);
        if ($pluginDir === null) {
            return null;
        }

        $iconDir = rtrim($pluginDir, '/') . PaymentDirectoriesPathEnum::PAYMENT_ICONS->value;
        if (!is_dir($iconDir)) {
            return null;
        }

        $providedExt = strtolower((string) pathinfo($iconName, PATHINFO_EXTENSION));
        $extensions = $providedExt !== '' ? [$providedExt] : self::ALLOWED_ICON_EXTENSIONS;

        $iconFilePath = null;
        $foundExt = '';
        foreach ($extensions as $ext) {
            $candidate = $iconDir . $baseName . '.' . $ext;
            if (is_file($candidate)) {
                $iconFilePath = $candidate;
                $foundExt = $ext;
                break;
            }
        }

        if ($iconFilePath === null) {
            return null;
        }

        $mime = match ($foundExt) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };

        $this->fileSaver->persistFileToMedia(
            new MediaFile(
                $iconFilePath,
                $mime,
                $foundExt,
                filesize($iconFilePath)
            ),
            $baseName,
            $mediaId,
            $context
        );

        return $mediaId;
    }

    public function getMediaDefaultFolderId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addAssociation('defaultFolder');
        $criteria->addFilter(new EqualsFilter('defaultFolder.entity', 'payment_method'));
        $criteria->setLimit(1);

        return $this->mediaFolderRepository->search($criteria, $context)->first()?->getId();
    }

    private function getMediaId(string $paymentMethodId, Context $context): ?string
    {
        $criteria = new Criteria([$paymentMethodId]);
        $criteria->addAssociation('media');

        return $this->paymentMethodRepository->search($criteria, $context)->first()?->getMediaId();
    }
}
