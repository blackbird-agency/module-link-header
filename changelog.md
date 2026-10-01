## Version 1.0.0 ##
*Released the 2026-10-01*

* **Feat** `LinkHeader` view model: declare links (`addLinkHeader()`, `addImagePreloadHeader()`) from any template, sent
as a single `Link` response header on the frontend.
* **Feat** links declared inside a block stored in the block HTML cache are kept with it and replayed when the block is
served from the cache, so the header is not lost after a full page cache purge.
* **Feat** identical links are only sent once.
* **Fix** an array of URLs adds one link per URL (it used to produce an invalid `<url1,url2>`), line breaks are removed
from URLs and attribute values.
