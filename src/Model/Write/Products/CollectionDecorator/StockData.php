<?php
/**
 * @author Emico <info@emico.nl>
 * @copyright (c) Emico B.V. 2017
 */

namespace Emico\TweakwiseExport\Model\Write\Products\CollectionDecorator;

use Emico\TweakwiseExport\Model\Config;
use Emico\TweakwiseExport\Model\Config\Source\StockCalculation;
use Emico\TweakwiseExport\Model\Write\Products\Collection;
use Emico\TweakwiseExport\Model\Write\Products\ExportEntity;
use Emico\TweakwiseExport\Model\Write\Products\ExportEntityFactory;
use Magento\CatalogInventory\Api\StockItemCriteriaInterfaceFactory;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;

class StockData implements DecoratorInterface
{
    /**
     * @var StockItemRepositoryInterface
     */
    private $stockItemRepository;

    /**
     * @var StockItemCriteriaInterfaceFactory
     */
    private $criteriaFactory;
    /**
     * @var Config
     */
    private $config;

    /**
     * StockData constructor.
     *
     * @param StockItemRepositoryInterface $stockItemRepository
     * @param StockItemCriteriaInterfaceFactory $criteriaFactory
     * @param Config $config
     */
    public function __construct(
        StockItemRepositoryInterface $stockItemRepository,
        StockItemCriteriaInterfaceFactory $criteriaFactory,
        Config $config
    )
    {
        $this->stockItemRepository = $stockItemRepository;
        $this->criteriaFactory = $criteriaFactory;
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function decorate(Collection $collection)
    {
        $this->addStockItems($collection);
        foreach ($collection as $item) {
            $this->combineStock($item, $collection->getStoreId());
        }
    }

    /**
     * @param Collection $collection
     */
    private function addStockItems(Collection $collection)
    {
        $entities = $collection->getAll();

        $criteria = $this->criteriaFactory->create();
        $criteria->addFilter('product_id', 'product_id', ['in' => implode(',', array_keys($entities))]);

        $items = $this->stockItemRepository->getList($criteria)->getItems();
        foreach ($items as $item) {
            $productId = (int) $item->getProductId();
            $entities[$productId]->setStockItem($item);
        }
    }

    /**
     * @param ExportEntity $entity
     * @param int $storeId
     */
    private function combineStock(ExportEntity $entity, int $storeId)
    {
        if ($entity->isComposite()) {
            return;
        }

        $combinedStock = $this->getCombinedStock($entity, $storeId);
        $entity->getStockItem()->setQty($combinedStock);
    }

    /**
     * @param ExportEntity $entity
     * @param int $storeId
     * @return float
     */
    private function getCombinedStock(ExportEntity $entity, int $storeId): float
    {
        $stockQty = [];
        foreach ($entity->getChildren() as $child) {
            $stockQty[] = $child->getStockQty();
        }

        switch ($this->config->getStockCalculation($storeId)) {
            case StockCalculation::OPTION_MAX:
                return max($stockQty);
            case StockCalculation::OPTION_MIN:
                return min($stockQty);
            case StockCalculation::OPTION_SUM:
            default:
                return array_sum($stockQty);
        }
    }
}