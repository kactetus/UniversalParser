<?php

namespace App\Parser\Spider\PersistenceHandler;

use App\Models\TemporarySearchResults;
use InvalidArgumentException;
use VDB\Spider\PersistenceHandler\PersistenceHandlerInterface;
use VDB\Spider\Resource;


class DBPersistenceHandler implements PersistenceHandlerInterface
{
    /**
     * @var Resource[]
     */
    private $resources = array();

    private $selectors;
    private $siteUrl;
    private $sessionId;

    public function __construct($selectors, $siteUrl, $sessionId) {
        $this->selectors = $selectors;
        $this->siteUrl = $siteUrl;
        $this->sessionId = $sessionId;
    }

    public function count()
    {
        return count($this->resources);
    }

    public function persist(Resource $resource)
    {
        if ($selectorVals = $this->getSelectorValues($resource)) {
            $insertSuccess = TemporarySearchResults::insertToTempTable($selectorVals, $this->siteUrl, $this->sessionId);
            $insertSuccess && $this->resources[] = $resource;
        }
    }

    private function getSelectorValues($resource) {
        $result['url'] = $resource->getCrawler()->getUri();
        foreach ($this->selectors as $key => $selector) {
            if (!$content = $this->getSelectorContent($resource, $selector)) {
                unset($result);
                continue;
            }
            $result[$key] = $content;
        }

        if (isset($result)) {
            return $result;
        }
    }

    /**
     * @param $resource
     * @param $selector
     * @return mixed
     */
    private function getSelectorContent($resource, $selector) {
        $item = $resource->getCrawler()->filterXpath($selector);
        if ($item->count()) {
            return $item->text();
        }
    }

    /**
     * @return Resource
     */
    public function current()
    {
    }

    /**
     * @return Resource|false
     */
    public function next()
    {
    }

    /**
     * @return int
     */
    public function key()
    {
    }

    /**
     * @return boolean
     */
    public function valid()
    {
    }

    /**
     * @return void
     */
    public function rewind()
    {

    }

    /**
     * @param string $spiderId
     *
     * @return void
     */
    public function setIdsession($spiderId) {

    }
}
