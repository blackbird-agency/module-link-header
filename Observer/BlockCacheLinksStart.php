<?php

declare(strict_types=1);

namespace Blackbird\LinkHeader\Observer;

use Blackbird\LinkHeader\Model\BlockCacheLinks;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Records the links count before a cached block renders or is loaded from the block HTML cache
 */
class BlockCacheLinksStart implements ObserverInterface
{
    /**
     * @param BlockCacheLinks $blockCacheLinks
     */
    public function __construct(
        protected BlockCacheLinks $blockCacheLinks
    ) {
    }

    /**
     * Observer for view_block_abstract_to_html_before
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $this->blockCacheLinks->start($observer->getEvent()->getData('block'));
    }
}
