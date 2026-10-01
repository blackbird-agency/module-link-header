<?php

declare(strict_types=1);

namespace Blackbird\LinkHeader\Test\Unit\Model;

use Blackbird\LinkHeader\Model\BlockCacheLinks;
use Blackbird\LinkHeader\ViewModel\LinkHeader;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\AbstractBlock;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BlockCacheLinksTest extends TestCase
{
    /**
     * @var LinkHeader
     */
    private LinkHeader $linkHeader;

    /**
     * @var array<string, array{data: string, tags: array, lifetime: mixed}> In-memory block cache
     */
    private array $storage = [];

    /**
     * @var BlockCacheLinks
     */
    private BlockCacheLinks $blockCacheLinks;

    protected function setUp(): void
    {
        $this->linkHeader = new LinkHeader();
        $this->storage = [];

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('save')->willReturnCallback(
            function ($data, $identifier, $tags = [], $lifetime = null) {
                $this->storage[$identifier] = ['data' => $data, 'tags' => $tags, 'lifetime' => $lifetime];
                return true;
            }
        );
        $cache->method('load')->willReturnCallback(fn ($identifier) => $this->storage[$identifier]['data'] ?? false);

        $cacheState = $this->createMock(StateInterface::class);
        $cacheState->method('isEnabled')->willReturn(true);

        $this->blockCacheLinks = new BlockCacheLinks(
            $this->linkHeader,
            $cache,
            $cacheState,
            new Json(),
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testLinksOfARenderedBlockAreStoredAndReplayedOnCacheHit(): void
    {
        $block = $this->createBlock(3600, ['CONTENT_1']);

        // Cache miss: the template is executed and declares a preload
        $this->blockCacheLinks->start($block);
        $this->blockCacheLinks->markRendered($block);
        $this->linkHeader->addImagePreloadHeader('https://example.com/a.webp');
        $this->blockCacheLinks->end($block);

        self::assertSame(['CONTENT_1', AbstractBlock::CACHE_GROUP], $this->storage['LINK_HEADER_BLOCK_KEY']['tags']);
        self::assertSame(3600, $this->storage['LINK_HEADER_BLOCK_KEY']['lifetime']);

        // Next request, cache hit: the template is not executed, the link is replayed
        $this->resetLinkHeader();
        $this->blockCacheLinks->start($block);
        $this->blockCacheLinks->end($block);

        self::assertSame(
            '<https://example.com/a.webp>; rel="preload"; as="image"; nopush',
            (string) $this->linkHeader
        );
    }

    public function testLinkAlreadyDeclaredBeforeTheBlockIsStoredWithIt(): void
    {
        $this->linkHeader->addImagePreloadHeader('https://example.com/a.webp');
        $block = $this->createBlock(3600, []);

        $this->blockCacheLinks->start($block);
        $this->blockCacheLinks->markRendered($block);
        $this->linkHeader->addImagePreloadHeader('https://example.com/a.webp');
        $this->blockCacheLinks->end($block);

        $this->resetLinkHeader();
        $this->blockCacheLinks->start($block);
        $this->blockCacheLinks->end($block);

        self::assertCount(1, $this->linkHeader->getLinks());
    }

    public function testBlocksWithoutCacheLifetimeAreIgnored(): void
    {
        $block = $this->createBlock(null, []);

        $this->blockCacheLinks->start($block);
        $this->blockCacheLinks->markRendered($block);
        $this->linkHeader->addImagePreloadHeader('https://example.com/a.webp');
        $this->blockCacheLinks->end($block);

        self::assertSame([], $this->storage);
    }

    public function testZeroLifetimeBlocksAreNotStored(): void
    {
        $block = $this->createBlock(0, []);

        $this->blockCacheLinks->start($block);
        $this->blockCacheLinks->markRendered($block);
        $this->linkHeader->addImagePreloadHeader('https://example.com/a.webp');
        $this->blockCacheLinks->end($block);

        self::assertSame([], $this->storage);
    }

    public function testNullCacheTagsKeepTheBlockHtmlTag(): void
    {
        $block = $this->createBlock(3600, null);

        $this->blockCacheLinks->start($block);
        $this->blockCacheLinks->markRendered($block);
        $this->blockCacheLinks->end($block);

        self::assertSame([AbstractBlock::CACHE_GROUP], $this->storage['LINK_HEADER_BLOCK_KEY']['tags']);
    }

    /**
     * The view model is shared for a request: a new request starts with an empty one
     */
    private function resetLinkHeader(): void
    {
        foreach (['links', 'declarations'] as $property) {
            (new \ReflectionProperty(LinkHeader::class, $property))->setValue($this->linkHeader, []);
        }
    }

    private function createBlock(?int $lifetime, ?array $tags): AbstractBlock
    {
        /** @var TestBlock $block */
        $block = (new \ReflectionClass(TestBlock::class))->newInstanceWithoutConstructor();
        $block->lifetime = $lifetime;
        $block->tags = $tags;

        return $block;
    }
}
