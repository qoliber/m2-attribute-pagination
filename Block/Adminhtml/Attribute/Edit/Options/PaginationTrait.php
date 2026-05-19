<?php
/**
 * Copyright © qoliber. All rights reserved.
 * @author      Jakub Winkler <jwinkler@qsolutionsstudio.com>
 *
 * @category    Qoliber
 * @package     Qoliber_AttributeOptionPager
 */

declare(strict_types=1);

namespace Qoliber\AttributeOptionPager\Block\Adminhtml\Attribute\Edit\Options;

use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection;

trait PaginationTrait
{
    /** @var int  */
    protected static int $defaultPage = 1;

    /** @var int  */
    protected static int $defaultLimit = 20;

    /** @var int[]  */
    protected static array $limit = [20, 30, 50, 100, 200];

    protected function getCurrentFilterQuery(): string
    {
        return trim((string)$this->getRequest()->getParam('option_filter', ''));
    }

    protected function applyFilterToCollection(Collection $collection): Collection
    {
        $filterQuery = $this->getCurrentFilterQuery();

        if ($filterQuery === '') {
            return $collection;
        }

        $collection->getSelect()->where(
            'sort_alpha_value.value LIKE ?',
            '%' . addcslashes($filterQuery, '%_') . '%'
        );

        return $collection;
    }

    protected function getFilteredOptionCollection(AbstractAttribute $attribute): Collection
    {
        $collection = $this->_attrOptionCollectionFactory->create()->setAttributeFilter(
            $attribute->getId()
        )->setPositionOrder(
            'asc',
            true
        );

        return $this->applyFilterToCollection($collection);
    }

    /**
     * Retrieve option values collection
     *
     * It is represented by an array in case of system attribute
     *
     * @param AbstractAttribute $attribute
     * @return array|Collection
     */
    protected function _getOptionValuesCollection(AbstractAttribute $attribute)
    {
        if ($this->canManageOptionDefaultOnly()) {
            return $this->_universalFactory->create(
                $attribute->getSourceModel()
            )->setAttribute(
                $attribute
            )->getAllOptions();
        } else {
            return $this->getFilteredOptionCollection($attribute)->setPageSize($this->getCurrentPageSize())
                ->setCurPage($this->getCurrentPageNumber())
                ->load();
        }
    }

    /**
     * Preparing values of attribute options
     *
     * @param AbstractAttribute $attribute
     * @param array|Collection $optionCollection
     * @return array
     */
    protected function _prepareOptionValues(
        AbstractAttribute $attribute,
        $optionCollection
    ): array
    {
        $type = $attribute->getFrontendInput();
        if ($type === 'select' || $type === 'multiselect') {
            $defaultValues = explode(',', $attribute->getDefaultValue() ?? '');
            $inputType = $type === 'select' ? 'radio' : 'checkbox';
        } else {
            $defaultValues = [];
            $inputType = '';
        }

        $values = [];
        $isSystemAttribute = is_array($optionCollection);
        if ($isSystemAttribute) {
            $values = $this->getPreparedValues($optionCollection, $isSystemAttribute, $inputType, $defaultValues);
        } else {
            $optionCollection->setPageSize($this->getCurrentPageSize());
            $values = array_merge(
                $values,
                $this->getPreparedValues($optionCollection, $isSystemAttribute, $inputType, $defaultValues)
            );
        }

        return $values;
    }


    /**
     * @param array|Collection $optionCollection
     * @param bool $isSystemAttribute
     * @param string $inputType
     * @param array $defaultValues
     * @return array
     */
    private function getPreparedValues(
        $optionCollection,
        bool $isSystemAttribute,
        string $inputType,
        array $defaultValues
    ): array
    {
        $values = [];
        foreach ($optionCollection as $option) {
            $bunch = $isSystemAttribute ? $this->_prepareSystemAttributeOptionValues(
                $option,
                $inputType,
                $defaultValues
            ) : $this->_prepareUserDefinedAttributeOptionValues(
                $option,
                $inputType,
                $defaultValues
            );
            foreach ($bunch as $value) {
                $values[] = new \Magento\Framework\DataObject($value);
            }
        }

        return $values;
    }

    /**
     * @return array|mixed|null
     */
    public function getOptionValues()
    {
        $values = $this->_getData('option_values');
        if ($values === null) {
            $values = [];

            $attribute = $this->getAttributeObject();
            $optionCollection = $this->_getOptionValuesCollection($attribute);
            if ($optionCollection) {
                $values = $this->_prepareOptionValues($attribute, $optionCollection);
            }

            $this->setData('option_values', $values);
        }

        return $values;
    }

    /**
     * @return int
     */
    public function getCurrentPageNumber(): int
    {
        $page = (int)$this->getRequest()->getParam('page', static::$defaultPage);

        return max(static::$defaultPage, min($page, $this->getMaxPageCount()));
    }

    /**
     * @return int
     */
    public function getCurrentPageSize(): int
    {
        if ($this->getRequest()->getParam('limit')) {
            return max(1, (int)$this->getRequest()->getParam('limit'));
        }

        return static::$defaultLimit;
    }

    /**
     * @return int[]
     */
    public function getLimits(): array
    {
        return static::$limit;
    }

    /**
     * @return int
     */
    public function getMaxPageCount(): int
    {
        $attribute = $this->getAttributeObject();

        return max(
            static::$defaultPage,
            (int)$this->getFilteredOptionCollection($attribute)
                ->setPageSize($this->getCurrentPageSize())
                ->getLastPageNumber()
        );
    }

    /**
     * @param int $limit
     * @param int $pageNumber
     * @return string
     */
    public function getNextUrl(int $limit, int $pageNumber): string
    {
        $requestParams = $this->getRequest()->getParams();
        $requestParams['page'] = $pageNumber;
        $requestParams['limit'] = $limit;
        return $this->getUrl('*/*/*', $requestParams);
    }

    /**
     * @return array<int, array<string, mixed>|mixed>
     */
    public function getOptionValuesData(): array
    {
        $values = [];

        foreach ($this->getOptionValues() as $value) {
            $value = $value->getData();
            $values[] = is_array($value) ? array_map(static function ($str) {
                return htmlspecialchars_decode($str, ENT_QUOTES);
            }, $value) : $value;
        }

        return $values;
    }

    /**
     * @return array<string, int|array<int, int>>
     */
    public function getPaginationData(): array
    {
        return [
            'currentPage' => $this->getCurrentPageNumber(),
            'pageSize' => $this->getCurrentPageSize(),
            'maxPageCount' => $this->getMaxPageCount(),
            'limits' => $this->getLimits(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPagerRequestParams(): array
    {
        $requestParams = $this->getRequest()->getParams();

        unset($requestParams['key'], $requestParams['isAjax'], $requestParams['panel']);

        return $requestParams;
    }

    public function getPagerAjaxUrl(): string
    {
        return $this->getUrl('qoliber_attributeoptionpager/pager/options');
    }

    public function getCurrentFilterValue(): string
    {
        return $this->getCurrentFilterQuery();
    }

}
