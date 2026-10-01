# Link Header &nbsp; [![Latest Stable Version](https://img.shields.io/badge/version-1.0.0-blue)](https://packagist.org/packages/blackbird/module-link-header) [![License: MIT](https://img.shields.io/badge/license-MIT-green)](./LICENSE)

Magento 2 module that lets any template declare resources to fetch early (LCP image, preconnect to a third-party
origin...) and sends them as an HTTP `Link` response header.

```http
Link: <https://cdn.example.com/hero-mobile.webp>; media="(max-width: 767.98px)"; fetchpriority="high"; rel="preload"; as="image"; nopush,
      <https://cdn.example.com/hero-desktop.webp>; media="(min-width: 768px)"; fetchpriority="high"; rel="preload"; as="image"; nopush
```

## Why choose this extension?

The image that holds the Largest Contentful Paint (hero banner, first product image, article header...) is usually
rendered after the header, the menu and the filters. On a Magento page, that is often several hundred kilobytes of
HTML: the browser only discovers the image once it has parsed them.

A `Link` header is read as soon as the response headers arrive, before the first byte of HTML. The browser starts
downloading the image right away. Measured on Magento 2 / Hyvä product and listing pages (mobile, Slow 4G, CPU 4x),
with images served from a CDN: **LCP −330 to −450 ms**.

Declaring the link from the template that renders the image means the URL, the breakpoints and the priority stay in
one place, and only the pages that display the image preload it.

## Features

- Declare links from any template or PHP class through a shared view model.
- Image preload helper (`rel=preload; as=image; nopush` by default), with `media`, `imagesrcset`, `imagesizes`,
  `fetchpriority`...
- Any other link: `preconnect`, `dns-prefetch`, `preload` of a font or a script...
- **Full page cache and Varnish compatible**: the header is part of the cached response.
- **Block HTML cache compatible**: links declared inside a cached block are stored with it and replayed when the block
  is served from the cache (see [How it works](#how-it-works)).
- Identical links are only sent once.
- Line breaks are stripped from URLs and values (no header injection, no rejected response).

## Installation

```bash
composer require blackbird/module-link-header
```

```bash
php bin/magento setup:upgrade
```

In production mode, run `php bin/magento setup:di:compile`: the module declares a plugin.

## Usage

### Preload the LCP image (Hyvä)

```php
<?php
use Blackbird\LinkHeader\ViewModel\LinkHeader;

/** @var \Hyva\Theme\Model\ViewModelRegistry $viewModels */
$viewModels->require(LinkHeader::class)
    ->addImagePreloadHeader($imageUrl, ['fetchpriority' => 'high']);
?>
<img src="<?= $escaper->escapeUrl($imageUrl) ?>" fetchpriority="high" alt="">
```

### Responsive image

The media queries must mirror the `<picture>` sources: otherwise the browser preloads an image it does not display,
and downloads two images.

```php
<?php
$viewModels->require(LinkHeader::class)
    ->addImagePreloadHeader($mobileUrl, ['media' => '(max-width: 767.98px)', 'fetchpriority' => 'high'])
    ->addImagePreloadHeader($desktopUrl, ['media' => '(min-width: 768px)', 'fetchpriority' => 'high']);
?>
<picture>
    <source media="(min-width: 768px)" srcset="<?= $escaper->escapeUrl($desktopUrl) ?>">
    <img src="<?= $escaper->escapeUrl($mobileUrl) ?>" fetchpriority="high" alt="">
</picture>
```

`imagesrcset` and `imagesizes` are supported as well, see
[Preload responsive images](https://web.dev/articles/preload-responsive-images).

### Other links

```php
<?php
$viewModels->require(LinkHeader::class)
    ->addLinkHeader('https://www.googletagmanager.com', ['rel' => 'preconnect', 'crossorigin' => '']);
```

A `null` value renders the attribute without value (`nopush`). An array of URLs adds one link per URL, with the same
attributes.

### Without Hyvä (Luma)

Pass the view model as a block argument in the layout:

```xml
<referenceBlock name="my.block">
    <arguments>
        <argument name="link_header" xsi:type="object">Blackbird\LinkHeader\ViewModel\LinkHeader</argument>
    </arguments>
</referenceBlock>
```

```php
<?php $block->getData('link_header')->addImagePreloadHeader($imageUrl); ?>
```

Or inject `Blackbird\LinkHeader\ViewModel\LinkHeader` in a PHP class: it is a shared instance, every caller adds to the
same header.

## How it works

1. **Collect.** `Blackbird\LinkHeader\ViewModel\LinkHeader` is a shared object. Each `addLinkHeader()` /
   `addImagePreloadHeader()` call, made while the page renders, adds a link to it.
2. **Send.** On `controller_front_send_response_before` (frontend area), the
   `AddLinkHeaderToResponse` observer formats the links and sets the `Link` header on the response. The full page
   cache (built-in or Varnish) caches the response with its headers, so a cached page keeps its preloads.
3. **Survive the block HTML cache.** Links are declared by templates, and a block served from the block HTML cache
   does not execute its templates, nor those of its children. Without care, a cached parent block (a CMS content, a
   product list...) loses the preloads of its images, and the full page cache then stores the page without them. It
   typically happens after a full page cache purge, while the block HTML cache is still warm.

   To prevent it, `Blackbird\LinkHeader\Model\BlockCacheLinks` follows every block that has a cache lifetime:

   - `view_block_abstract_to_html_before`: remember how many links were declared so far.
   - `Template::fetchView()` (plugin): a template is only fetched on a block cache miss, so the block is flagged as
     rendered.
   - `view_block_abstract_to_html_after`:
     - **rendered** (cache miss): the links declared while rendering the block, its children included, are saved in the
       cache under `LINK_HEADER_<block cache key>`, with the block cache tags (plus `block_html`) and lifetime. Cleaning
       the block HTML cache cleans them too;
     - **not rendered** (cache hit): the saved links are added again.

   Nested cached blocks work the same way: a parent saves the links of its children, replayed or rendered.

Any error in this process is logged and never breaks the page rendering.

## Good practices

- **Only preload what is above the fold and displayed**: the LCP image, maybe a hero font. A preload competes for the
  bandwidth with the render-blocking CSS: preloading everything slows the first render down.
- Keep `loading="eager"` (never `lazy`) on a preloaded image, and use the same `fetchpriority`.
- Preload the exact URL rendered in the HTML (same size, same query string), or the image is downloaded twice.
- Mirror the `<picture>` breakpoints in `media`.
- Same-origin images gain less than images served by a CDN or another domain: they share the HTML and CSS connection
  and bandwidth. Measure.

## Limitations

- Frontend area only.
- Response headers have size limits (e.g. Varnish `http_resp_hdr_len`, 8 KB by default, nginx `proxy_buffer_size`,
  4 or 8 KB). Keep the header to a few links.
- Two requests rendering the same cached block at the same time: the second one can read the block HTML before its
  links are saved, and serve that page once without the preload.

## Tests

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist vendor/blackbird/module-link-header/Test/Unit
```

## License

This module is licensed under the MIT License, see [LICENSE](./LICENSE).
