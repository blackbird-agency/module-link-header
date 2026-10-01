<?php

declare(strict_types=1);

namespace Blackbird\LinkHeader\Test\Unit\ViewModel;

use Blackbird\LinkHeader\ViewModel\LinkHeader;
use PHPUnit\Framework\TestCase;

class LinkHeaderTest extends TestCase
{
    /**
     * @var LinkHeader
     */
    private LinkHeader $linkHeader;

    protected function setUp(): void
    {
        $this->linkHeader = new LinkHeader();
    }

    public function testEmptyHeader(): void
    {
        self::assertSame('', (string) $this->linkHeader);
    }

    public function testImagePreloadDefaults(): void
    {
        $this->linkHeader->addImagePreloadHeader('https://example.com/a.webp', ['media' => '(max-width: 767.98px)']);

        self::assertSame(
            '<https://example.com/a.webp>; media="(max-width: 767.98px)"; rel="preload"; as="image"; nopush',
            (string) $this->linkHeader
        );
    }

    public function testDefaultsCanBeOverridden(): void
    {
        $this->linkHeader->addImagePreloadHeader('https://example.com/a.webp', ['rel' => 'prefetch']);

        self::assertSame(
            '<https://example.com/a.webp>; rel="prefetch"; as="image"; nopush',
            (string) $this->linkHeader
        );
    }

    public function testSeveralLinksAreCommaSeparated(): void
    {
        $this->linkHeader
            ->addLinkHeader('https://www.googletagmanager.com', ['rel' => 'preconnect', 'crossorigin' => ''])
            ->addImagePreloadHeader('https://example.com/a.webp');

        self::assertSame(
            '<https://www.googletagmanager.com>; rel="preconnect"; crossorigin="",'
            . '<https://example.com/a.webp>; rel="preload"; as="image"; nopush',
            (string) $this->linkHeader
        );
    }

    public function testIdenticalLinksAreSentOnce(): void
    {
        $this->linkHeader->addImagePreloadHeader('https://example.com/a.webp');
        $this->linkHeader->addImagePreloadHeader('https://example.com/a.webp');

        self::assertCount(1, $this->linkHeader->getLinks());
        self::assertCount(2, $this->linkHeader->getDeclarations());
    }

    public function testArrayOfUrlsAddsOneLinkPerUrl(): void
    {
        $this->linkHeader->addLinkHeader(['https://a.example', 'https://b.example'], ['rel' => 'preconnect']);

        self::assertSame(
            '<https://a.example>; rel="preconnect",<https://b.example>; rel="preconnect"',
            (string) $this->linkHeader
        );
    }

    public function testValuesAreEscapedAndLineBreaksRemoved(): void
    {
        $this->linkHeader->addLinkHeader("https://example.com/a.webp\r\nX-Injected: 1", ['title' => 'a "b"']);

        self::assertSame(
            '<https://example.com/a.webpX-Injected: 1>; title="a \"b\""',
            (string) $this->linkHeader
        );
    }
}
