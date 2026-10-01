<?php

declare(strict_types=1);

namespace Blackbird\LinkHeader\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Stringable;

/**
 * Collects the links (preload, preconnect...) declared while the page renders and formats them as an HTTP `Link`
 * response header, sent by \Blackbird\LinkHeader\Observer\AddLinkHeaderToResponse.
 *
 * Shared instance: every template that requires this view model adds to the same header.
 */
class LinkHeader implements ArgumentInterface, Stringable
{
    /**
     * @var array<int, array{link: string|string[], attributes: array<string, string|int|float|null>}>
     */
    protected array $links = [];

    /**
     * Every addLinkHeader() call, duplicates included: lets a block cache entry keep a link that another part of the
     * page had already declared
     *
     * @var array<int, array{link: string|string[], attributes: array<string, string|int|float|null>}>
     */
    protected array $declarations = [];

    /**
     * Add a link to the response header
     *
     * A null attribute value renders the attribute without value (e.g. `nopush`). An array of URLs adds one link per
     * URL, with the same attributes.
     *
     * @param string|string[] $link
     * @param array $attributes attribute => value (string|int|float|null)
     * @return $this
     */
    public function addLinkHeader(string|array $link, array $attributes = []): self
    {
        $newLink = [
            'link' => $link,
            'attributes' => $attributes,
        ];
        $this->declarations[] = $newLink;
        // The same resource can be declared twice (product rendered in two lists, replay from the block cache)
        if (!in_array($newLink, $this->links, true)) {
            $this->links[] = $newLink;
        }

        return $this;
    }

    /**
     * Add an image preload: rel=preload; as=image; nopush by default
     *
     * Pass `media`, `imagesrcset`, `imagesizes` or `fetchpriority` in the attributes. The media queries must match the
     * <picture> sources of the image, otherwise the browser downloads an image it does not display.
     *
     * @param string|string[] $link
     * @param array $attributes attribute => value (string|int|float|null)
     * @return $this
     * @see https://web.dev/articles/preload-responsive-images
     */
    public function addImagePreloadHeader(string|array $link, array $attributes = []): self
    {
        $attributes += [
            'rel' => 'preload',
            'as' => 'image',
            'nopush' => null,
        ];

        return $this->addLinkHeader($link, $attributes);
    }

    /**
     * Links added so far, in the format expected by addLinkHeader()
     *
     * @return array<int, array{link: string|string[], attributes: array<string, string|int|float|null>}>
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * Every addLinkHeader() call so far, duplicates included, in call order
     *
     * @return array<int, array{link: string|string[], attributes: array<string, string|int|float|null>}>
     */
    public function getDeclarations(): array
    {
        return $this->declarations;
    }

    /**
     * Format the links as the value of a `Link` header
     *
     * Example:
     * <https://example.com/hero-mobile.webp>; media="(max-width: 767.98px)"; fetchpriority="high"; rel="preload";
     * as="image"; nopush,<https://example.com/hero-desktop.webp>; media="(min-width: 768px)"; ...
     *
     * @return string
     */
    public function __toString(): string
    {
        $rows = [];
        foreach ($this->links as $link) {
            $attributes = [];
            foreach ($link['attributes'] as $attribute => $value) {
                $attribute = $this->sanitize((string) $attribute);
                $attributes[] = $value === null
                    ? $attribute
                    : sprintf('%s="%s"', $attribute, $this->quote($this->sanitize((string) $value)));
            }

            foreach ((array) $link['link'] as $url) {
                $url = $this->sanitize((string) $url);
                if ($url === '') {
                    continue;
                }
                $rows[] = implode('; ', ["<$url>", ...$attributes]);
            }
        }

        return implode(',', $rows);
    }

    /**
     * Escape a quoted-string value (RFC 9110): backslash and double quote
     *
     * @param string $value
     * @return string
     */
    protected function quote(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    /**
     * Header values cannot contain line breaks (header injection, rejected by the HTTP response)
     *
     * @param string $value
     * @return string
     */
    protected function sanitize(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $value));
    }
}
