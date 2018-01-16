<?php
namespace App\Parser\Spider\QueueManager;

use App\Models\Links;
use App\Models\TemporarySearchResults;
use VDB\Spider\QueueManager\QueueManagerInterface;
use VDB\Spider\Uri\DiscoveredUri;
use VDB\Spider\Exception\QueueException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use VDB\Spider\Event\SpiderEvents;
use VDB\Uri\Uri;

class UpdateDBQueueManager implements QueueManagerInterface
{
    /** @var int The maximum size of the process queue for this spider. 0 means infinite */
    public $maxQueueSize = 0;

    /** @var int The traversal algorithm to use. Choose from the class constants */
    private $traversalAlgorithm = self::ALGORITHM_DEPTH_FIRST;

    /** @var EventDispatcherInterface */
    private $dispatcher;

    /** @var array The list of URIs to process */
    private $traversalQueue = array();

    private $siteConfigName;

    function __construct($siteConfigName)
    {
        $this->siteConfigName = $siteConfigName;
        $this->init();
    }

    private function init() {
        $this->traversalQueue = TemporarySearchResults::select('content->url as url')
            ->where(['config_site_name' => $this->siteConfigName])
            ->where('version', '>', 0)
            ->whereNull('need_delete')
            ->get()
            ->toArray();
        $this->traversalQueue = array_map(function ($item) {
            return new DiscoveredUri(new Uri(trim($item['url'], '"')));
        }, $this->traversalQueue);
    }

    /**
     * @param int $traversalAlgorithm Choose from the class constants
     * TODO: This should be extracted to a Strategy pattern
     */
    public function setTraversalAlgorithm($traversalAlgorithm)
    {
        $this->traversalAlgorithm = $traversalAlgorithm;
    }

    /**
     * @return int
     */
    public function getTraversalAlgorithm()
    {
        return $this->traversalAlgorithm;
    }

    /**
     * @param EventDispatcherInterface $eventDispatcher
     * @return $this
     */
    public function setDispatcher(EventDispatcherInterface $eventDispatcher)
    {
        $this->dispatcher = $eventDispatcher;

        return $this;
    }

    /**
     * @return EventDispatcherInterface
     */
    public function getDispatcher()
    {
        if (!$this->dispatcher) {
            $this->dispatcher = new EventDispatcher();
        }
        return $this->dispatcher;
    }

    /**
     * @param DiscoveredUri
     */
    public function addUri(DiscoveredUri $uri)
    {
        $this->getDispatcher()->dispatch(
            SpiderEvents::SPIDER_CRAWL_POST_ENQUEUE,
            new GenericEvent($this, array('uri' => $uri))
        );
    }

    public function next()
    {
        if ($this->traversalAlgorithm === static::ALGORITHM_DEPTH_FIRST) {
            return array_pop($this->traversalQueue);
        } elseif ($this->traversalAlgorithm === static::ALGORITHM_BREADTH_FIRST) {
            return array_shift($this->traversalQueue);
        } else {
            throw new \LogicException('No search algorithm set');
        }
    }
}
