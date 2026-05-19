<?php
/**
 * Copyright © qoliber. All rights reserved.
 *
 * @category    Qoliber
 * @package     Qoliber_AttributeOptionPager
 */

declare(strict_types=1);

namespace Qoliber\AttributeOptionPager\Controller\Adminhtml\Pager;

use Magento\Backend\App\Action;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Frontend\Inputtype\Presentation;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as AttributeModel;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutFactory;
use Magento\Eav\Model\Entity;
use Magento\Eav\Model\EntityFactory;
use Qoliber\AttributeOptionPager\Block\Adminhtml\Attribute\Edit\Options\Options as OptionsBlock;
use Qoliber\AttributeOptionPager\Block\Adminhtml\Attribute\Edit\Options\Text as TextBlock;
use Qoliber\AttributeOptionPager\Block\Adminhtml\Attribute\Edit\Options\Visual as VisualBlock;

class Options extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Catalog::attributes_attributes';

    /**
     * @var array<string, class-string>
     */
    private array $blockMap = [
        'options' => OptionsBlock::class,
        'text' => TextBlock::class,
        'visual' => VisualBlock::class,
    ];

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly LayoutFactory $layoutFactory,
        private readonly Registry $registry,
        private readonly AttributeFactory $attributeFactory,
        private readonly EntityFactory $entityFactory,
        private readonly Presentation $presentation
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        /** @var Json $result */
        $result = $this->resultJsonFactory->create();
        $panel = (string)$this->getRequest()->getParam('panel');
        $attribute = $this->getAttribute();

        if (!isset($this->blockMap[$panel]) || !$attribute) {
            return $result->setHttpResponseCode(400)->setData([
                'error' => true,
                'message' => __('Unable to load the requested attribute option panel.'),
            ]);
        }

        $layout = $this->layoutFactory->create();
        $blockClass = $this->blockMap[$panel];
        $block = $layout->createBlock($blockClass);

        return $result->setData([
            'error' => false,
            'attributesData' => $block->getOptionValuesData(),
            'pagination' => $block->getPaginationData(),
            'currentUrl' => $this->getEditPageUrl(
                (int)$block->getPaginationData()['currentPage'],
                (int)$block->getPaginationData()['pageSize']
            ),
        ]);
    }

    private function getAttribute(): ?AttributeModel
    {
        $attributeId = (int)$this->getRequest()->getParam('attribute_id');

        if ($attributeId <= 0) {
            return null;
        }

        /** @var AttributeModel $attribute */
        $attribute = $this->attributeFactory->create();
        $attribute->setEntityTypeId($this->getEntityTypeId());
        $attribute->load($attributeId);

        if (!$attribute->getId() || (int)$attribute->getEntityTypeId() !== $this->getEntityTypeId()) {
            return null;
        }

        $attribute->setFrontendInput($this->presentation->getPresentationInputType($attribute));

        if ($this->registry->registry('entity_attribute')) {
            $this->registry->unregister('entity_attribute');
        }
        $this->registry->register('entity_attribute', $attribute);

        return $attribute;
    }

    private function getEntityTypeId(): int
    {
        return (int)$this->entityFactory->create()
            ->setType(Product::ENTITY)
            ->getTypeId();
    }

    private function getEditPageUrl(int $page, int $limit): string
    {
        $requestParams = $this->getRequest()->getParams();

        unset($requestParams['isAjax'], $requestParams['panel'], $requestParams['key']);

        $requestParams['page'] = $page;
        $requestParams['limit'] = $limit;

        return $this->_url->getUrl('catalog/product_attribute/edit', $requestParams);
    }
}
