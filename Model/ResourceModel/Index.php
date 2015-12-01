<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Elasticsearch\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Eav\Model\Config;
use Magento\Catalog\Api\Data\ProductAttributeInterface;

/**
 * Elasticsearch index resource model
 */
class Index extends \Magento\AdvancedSearch\Model\ResourceModel\Index
{
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var Config
     */
    protected $eavConfig;

    /**
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param ProductRepositoryInterface $productRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param Config $eavConfig
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        CategoryRepositoryInterface $categoryRepository,
        Config $eavConfig,
        $connectionName = null
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->eavConfig = $eavConfig;
        parent::__construct($context, $storeManager, $connectionName);
    }

    /**
     * Retrieve all attributes for given product ids
     *
     * @param array $productIds
     * @return array
     */
    public function getFullProductIndexData(array $productIds)
    {
        $attributeCodes = $this->eavConfig->getEntityAttributeCodes(ProductAttributeInterface::ENTITY_TYPE_CODE);
        foreach ($productIds as $productId) {
            $product = $this->productRepository->getById($productId);
            $productAttributesWithValues = $product->getData();
            foreach ($productAttributesWithValues as $attributeCode => $value) {
                $attribute = $this->eavConfig->getAttribute(
                    ProductAttributeInterface::ENTITY_TYPE_CODE,
                    $attributeCode
                );
                $frontendInput = $attribute->getFrontendInput();
                if (in_array($attributeCode, $attributeCodes)) {
                    $productAttributes[$productId][$attributeCode] = $value;
                    if ($frontendInput == 'select') {
                        foreach ($attribute->getOptions() as $option) {
                            if ($option->getValue() == $value) {
                                $productAttributes[$productId][$attributeCode . '_value'] = $option->getLabel();
                            }
                        }
                    }
                }
            }
        }
        return $productAttributes;
    }

    /**
     * Prepare full category index data for products.
     *
     * @param int $storeId
     * @param null|array $productIds
     * @return array
     */
    public function getFullCategoryProductIndexData($storeId = null, $productIds = null)
    {
        $categoryPositions = $this->getCategoryProductIndexData($storeId, $productIds);
        $categoryData = [];

        foreach ($categoryPositions as $productId => $positions) {
            foreach ($positions as $categoryId => $position) {
                $category = $this->categoryRepository->get($categoryId, $storeId);
                $categoryName = $category->getName();
                $categoryData[$productId][] = [
                    'id' => $categoryId,
                    'name' => $categoryName,
                    'position' => $position
                ];
            }
        }
        return $categoryData;
    }
}
