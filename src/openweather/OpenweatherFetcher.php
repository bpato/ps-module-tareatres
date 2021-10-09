<?php

namespace Bpato\Tareatres\openweather;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Doctrine\Common\Cache\FilesystemCache;
use DOMDocument;
use DOMNode;
use DOMXPath;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Subscriber\Cache\CacheStorage;
use GuzzleHttp\Subscriber\Cache\CacheSubscriber;
use PrestaShop\CircuitBreaker\AdvancedCircuitBreakerFactory;
use PrestaShop\CircuitBreaker\Contract\FactoryInterface;
use PrestaShop\CircuitBreaker\Contract\FactorySettingsInterface;
use PrestaShop\CircuitBreaker\FactorySettings;
use PrestaShop\CircuitBreaker\Storage\DoctrineCache;
use Symfony\Component\CssSelector\CssSelectorConverter;

class OpenweatherFetcher
{
    const CACHE_DURATION = 300; // 5minutos

    const OW_ENDPOINT_URL = 'api.openweathermap.org/data/2.5/weather';
    const API_KEY = '3f9d369e64b3d9a1f5ad7cd5cb34ca50';

    const CLOSED_ALLOWED_FAILURES = 2;
    const API_TIMEOUT_SECONDS = 0.3;

    const PLATFORM_TIMEOUT_SECONDS = 1;

    const OPEN_ALLOWED_FAILURES = 1;
    const OPEN_TIMEOUT_SECONDS = 1.2;

    const OPEN_THRESHOLD_SECONDS = 60;

    /** @var FactoryInterface */
    private $factory;

    /** @var FactorySettingsInterface */
    private $apiSettings;

    /** @var FactorySettingsInterface */
    private $platformSettings;

    public function __construct()
    {
        //Doctrine cache used for Guzzle and CircuitBreaker storage
        $doctrineCache = new FilesystemCache(_PS_CACHE_DIR_ . '/openweather');

        //Init Guzzle cache
        $cacheStorage = new CacheStorage($doctrineCache, null, self::CACHE_DURATION);
        $cacheSubscriber = new CacheSubscriber($cacheStorage, function (Request $request) { return true; });

        //Init circuit breaker factory
        $storage = new DoctrineCache($doctrineCache);
        $this->apiSettings = new FactorySettings(self::CLOSED_ALLOWED_FAILURES, self::API_TIMEOUT_SECONDS, 0);
        $this->apiSettings
            ->setThreshold(self::OPEN_THRESHOLD_SECONDS)
            ->setStrippedFailures(self::OPEN_ALLOWED_FAILURES)
            ->setStrippedTimeout(self::OPEN_TIMEOUT_SECONDS)
            ->setStorage($storage)
            ->setClientOptions([
                'subscribers' => [$cacheSubscriber],
                'method' => 'GET',
            ])
        ;

        /* $this->platformSettings = new FactorySettings(self::CLOSED_ALLOWED_FAILURES, self::PLATFORM_TIMEOUT_SECONDS, 0);
        $this->platformSettings
            ->setThreshold(self::OPEN_THRESHOLD_SECONDS)
            ->setStrippedFailures(self::OPEN_ALLOWED_FAILURES)
            ->setStrippedTimeout(self::OPEN_TIMEOUT_SECONDS)
            ->setStorage($storage)
            ->setClientOptions([
                'subscribers' => [$cacheSubscriber],
                'method' => 'GET',
            ])
        ; */

        $this->factory = new AdvancedCircuitBreakerFactory();
    }

    public function getData($args)
    {
        $weatherdata = $this->getWeatherFromApi([
            'q' => $args['city'],
            'units' => 'metric',
            'appid' => self::API_KEY
        ]);
        return $weatherdata;
    }

    private function getWeatherFromApi(array $args)
    {   
        $url = self::OW_ENDPOINT_URL . '?' . http_build_query($args);
        $circuitBreaker = $this->factory->create($this->apiSettings);
        $apiJsonResponse = $circuitBreaker->call(
            $url,
            array('body' => $args ) // Solo para POST
        );
        return !empty($apiJsonResponse) ? json_decode($apiJsonResponse, true) : false;
    }
}