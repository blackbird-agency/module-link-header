<?php

declare(strict_types=1);

namespace Blackbird\LinkHeader\Model;

use Blackbird\LinkHeader\ViewModel\LinkHeader;
use Magento\Framework\App\Cache\StateInterface as CacheState;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Psr\Log\LoggerInterface;

/**
 * Keeps the Link headers declared by a block in sync with the block HTML cache.
 *
 * Links are added by templates while they render. When a block (or one of its parents) is served from the block HTML
 * cache, its templates are not executed and its links would be lost: the links added while rendering a cached block are
 * stored next to its HTML (same key suffix, tags and lifetime) and replayed when its HTML comes from the cache.
 */
class BlockCacheLinks
{
    private const CACHE_KEY_PREFIX = 'LINK_HEADER_';

    /**
     * @var array<int, int> Number of link declarations before each cached block started rendering, by block object id
     */
    protected array $linksCountBefore = [];

    /**
     * @var array<int, bool> Cached blocks actually rendered (cache miss), by block object id
     */
    protected array $rendered = [];

    /**
     * @param LinkHeader $linkHeader
     * @param CacheInterface $cache
     * @param CacheState $cacheState
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected LinkHeader $linkHeader,
        protected CacheInterface $cache,
        protected CacheState $cacheState,
        protected SerializerInterface $serializer,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * Called before a block is rendered or loaded from the cache
     *
     * @param AbstractBlock $block
     * @return void
     */
    public function start(AbstractBlock $block): void
    {
        if (!$this->isCached($block)) {
            return;
        }
        $blockId = spl_object_id($block);
        $this->linksCountBefore[$blockId] = count($this->linkHeader->getDeclarations());
        unset($this->rendered[$blockId]);
    }

    /**
     * Called when a template is actually executed, which only happens on a block cache miss
     *
     * @param AbstractBlock $block
     * @return void
     */
    public function markRendered(AbstractBlock $block): void
    {
        $blockId = spl_object_id($block);
        if (isset($this->linksCountBefore[$blockId])) {
            $this->rendered[$blockId] = true;
        }
    }

    /**
     * Called after a block is rendered or loaded from the cache: store its links on a miss, replay them on a hit
     *
     * @param AbstractBlock $block
     * @return void
     */
    public function end(AbstractBlock $block): void
    {
        $blockId = spl_object_id($block);
        if (!isset($this->linksCountBefore[$blockId])) {
            return;
        }
        $countBefore = $this->linksCountBefore[$blockId];
        $wasRendered = isset($this->rendered[$blockId]);
        unset($this->linksCountBefore[$blockId], $this->rendered[$blockId]);

        // A missing preload must never break the page rendering
        try {
            $cacheKey = self::CACHE_KEY_PREFIX . $block->getCacheKey();
            if ($wasRendered) {
                // Same condition as AbstractBlock::_saveCache(): a 0 lifetime block is never stored, so never replayed
                if (!$this->getCacheLifetime($block)) {
                    return;
                }
                // Some blocks (content manager widgets) return null tags: keep at least the block_html tag, so that
                // cleaning the block HTML cache also cleans the links
                $cacheTags = array_unique(
                    [...(array) $this->callBlockMethod($block, 'getCacheTags'), AbstractBlock::CACHE_GROUP]
                );
                $this->cache->save(
                    $this->serializer->serialize(array_slice($this->linkHeader->getDeclarations(), $countBefore)),
                    $cacheKey,
                    $cacheTags,
                    $this->getCacheLifetime($block)
                );
                return;
            }

            $cachedLinks = $this->cache->load($cacheKey);
            if (!$cachedLinks) {
                return;
            }
            foreach ($this->serializer->unserialize($cachedLinks) as $link) {
                $this->linkHeader->addLinkHeader($link['link'], $link['attributes']);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Link header block cache: ' . $exception->getMessage(), ['exception' => $exception]);
        }
    }

    /**
     * Same condition as AbstractBlock::_loadCache()
     *
     * @param AbstractBlock $block
     * @return bool
     */
    protected function isCached(AbstractBlock $block): bool
    {
        return $this->getCacheLifetime($block) !== null && $this->cacheState->isEnabled(AbstractBlock::CACHE_GROUP);
    }

    /**
     * Cache lifetime of the block, as AbstractBlock::_loadCache() reads it
     *
     * @param AbstractBlock $block
     * @return int|string|null
     */
    protected function getCacheLifetime(AbstractBlock $block): int|string|null
    {
        return $this->callBlockMethod($block, 'getCacheLifetime');
    }

    /**
     * Call a protected AbstractBlock method (getCacheLifetime(), getCacheTags()) the way the block itself does.
     *
     * Called from outside, these methods would go through DataObject::__call() and return the raw "cache_lifetime" /
     * "cache_tags" data, ignoring the blocks that override them.
     *
     * @param AbstractBlock $block
     * @param string $method
     * @return mixed
     */
    protected function callBlockMethod(AbstractBlock $block, string $method): mixed
    {
        return (fn () => $this->{$method}())->call($block);
    }
}
