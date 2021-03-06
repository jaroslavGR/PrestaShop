<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Product\VirtualProduct\Update;

use PrestaShop\PrestaShop\Adapter\File\Uploader\VirtualProductFileUploader;
use PrestaShop\PrestaShop\Adapter\Product\Repository\ProductRepository;
use PrestaShop\PrestaShop\Adapter\Product\VirtualProduct\Repository\VirtualProductFileRepository;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductId;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Exception\VirtualProductFileConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\ValueObject\VirtualProductFileId;
use ProductDownload as VirtualProductFile;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Provides update methods specific to virtual product
 * Legacy object ProductDownload is referred as VirtualProductFile in Core
 */
class VirtualProductUpdater
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var VirtualProductFileUploader
     */
    private $virtualProductFileUploader;

    /**
     * @var VirtualProductFileRepository
     */
    private $virtualProductFileRepository;

    /**
     * @var string
     */
    private $virtualProductFileDir;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @param ProductRepository $productRepository
     * @param VirtualProductFileUploader $virtualProductFileUploader
     * @param VirtualProductFileRepository $virtualProductFileRepository
     * @param Filesystem $filesystem
     * @param string $virtualProductFileDir
     */
    public function __construct(
        ProductRepository $productRepository,
        VirtualProductFileUploader $virtualProductFileUploader,
        VirtualProductFileRepository $virtualProductFileRepository,
        Filesystem $filesystem,
        string $virtualProductFileDir
    ) {
        $this->productRepository = $productRepository;
        $this->virtualProductFileUploader = $virtualProductFileUploader;
        $this->virtualProductFileRepository = $virtualProductFileRepository;
        $this->virtualProductFileDir = $virtualProductFileDir;
        $this->filesystem = $filesystem;
    }

    /**
     * @param VirtualProductFile $virtualProductFile
     * @param string|null $newFilePath
     */
    public function updateFile(VirtualProductFile $virtualProductFile, ?string $newFilePath): void
    {
        if ($newFilePath) {
            $uploadedFilePath = $this->virtualProductFileUploader->replace($newFilePath, $virtualProductFile->filename);
            $virtualProductFile->filename = pathinfo($uploadedFilePath, PATHINFO_FILENAME);
        }

        $this->virtualProductFileRepository->update($virtualProductFile);
    }

    /**
     * Add virtual product file to a product
     * Legacy object ProductDownload is referred as VirtualProductFile in Core
     *
     * @param ProductId $productId
     * @param string $filePath
     * @param VirtualProductFile $virtualProductFile
     *
     * @return VirtualProductFileId
     */
    public function addFile(ProductId $productId, string $filePath, VirtualProductFile $virtualProductFile): VirtualProductFileId
    {
        $product = $this->productRepository->get($productId);

        if (!$product->is_virtual) {
            throw new VirtualProductFileConstraintException(
                'Only virtual product can have file',
                VirtualProductFileConstraintException::INVALID_PRODUCT_TYPE
            );
        }

        if ($this->virtualProductFileRepository->findByProductId($productId)) {
            throw new VirtualProductFileConstraintException(
                sprintf('File already exists for product #%d', $product->id),
                VirtualProductFileConstraintException::ALREADY_HAS_A_FILE
            );
        }

        $uploadedFilePath = $this->virtualProductFileUploader->upload($filePath);
        $virtualProductFile->filename = pathinfo($uploadedFilePath, PATHINFO_FILENAME);
        $virtualProductFile->id_product = $productId->getValue();

        return $this->virtualProductFileRepository->add($virtualProductFile);
    }

    /**
     * @param VirtualProductFileId $virtualProductFileId
     */
    public function deleteFile(VirtualProductFileId $virtualProductFileId): void
    {
        $virtualProductFile = $this->virtualProductFileRepository->get($virtualProductFileId);
        $this->virtualProductFileUploader->remove($virtualProductFile->filename);

        $this->virtualProductFileRepository->delete($virtualProductFileId);
    }
}
