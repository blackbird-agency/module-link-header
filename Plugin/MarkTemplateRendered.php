<?php

declare(strict_types=1);

namespace Blackbird\LinkHeader\Plugin;

use Blackbird\LinkHeader\Model\BlockCacheLinks;
use Magento\Framework\View\Element\Template;

/**
 * Flags the blocks whose template is actually executed, i.e. not served from the block HTML cache
 */
class MarkTemplateRendered
{
    /**
     * @param BlockCacheLinks $blockCacheLinks
     */
    public function __construct(
        protected BlockCacheLinks $blockCacheLinks
    ) {
    }

    /**
     * A template is only fetched when its block is not served from the block HTML cache
     *
     * @param Template $subject
     * @param string $fileName
     * @return null
     */
    public function beforeFetchView(Template $subject, $fileName)
    {
        $this->blockCacheLinks->markRendered($subject);
        return null;
    }
}
