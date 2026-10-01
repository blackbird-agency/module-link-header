<?php

declare(strict_types=1);

namespace Blackbird\LinkHeader\Observer;

use Blackbird\LinkHeader\Model\BlockCacheLinks;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Stores the links of a rendered cached block, or replays them when the block comes from the block HTML cache
 */
class BlockCacheLinksEnd implements ObserverInterface
{
    /**
     * @param BlockCacheLinks $blockCacheLinks
     */
    public function __construct(
        protected BlockCacheLinks $blockCacheLinks
    ) {
    }

    /**
     * Observer for view_block_abstract_to_html_after
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $this->blockCacheLinks->end($observer->getEvent()->getData('block'));
    }
}
