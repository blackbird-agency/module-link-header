<?php

declare(strict_types=1);

namespace Blackbird\LinkHeader\Observer;

use Blackbird\LinkHeader\ViewModel\LinkHeader;
use Magento\Framework\App\Response\HttpInterface as HttpResponse;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Sends the links collected while rendering the page as a `Link` response header
 */
class AddLinkHeaderToResponse implements ObserverInterface
{
    /**
     * @param LinkHeader $linkHeader
     */
    public function __construct(
        protected LinkHeader $linkHeader
    ) {
    }

    /**
     * Observer for controller_front_send_response_before
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var HttpResponse|null $response */
        $response = $observer->getEvent()->getData('response');
        $linkHeader = (string) $this->linkHeader;
        if ($response instanceof HttpResponse && $linkHeader !== '') {
            $response->setHeader('Link', $linkHeader);
        }
    }
}
