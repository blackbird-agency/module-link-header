<?php

declare(strict_types=1);

namespace Blackbird\LinkHeader\Test\Unit\Model;

use Magento\Framework\View\Element\AbstractBlock;

/**
 * Block double: AbstractBlock::getCacheLifetime() and getCacheTags() are protected, like in real blocks
 */
class TestBlock extends AbstractBlock
{
    /**
     * @var int|null
     */
    public ?int $lifetime = null;

    /**
     * @var array|null
     */
    public ?array $tags = null;

    /**
     * @inheritDoc
     */
    public function getCacheKey()
    {
        return 'BLOCK_KEY';
    }

    /**
     * @inheritDoc
     */
    protected function getCacheLifetime()
    {
        return $this->lifetime;
    }

    /**
     * @inheritDoc
     */
    protected function getCacheTags()
    {
        return $this->tags;
    }
}
