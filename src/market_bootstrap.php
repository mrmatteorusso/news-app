<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/TelemetryRepository.php';
require_once __DIR__ . '/Market/MarketProviderInterface.php';
require_once __DIR__ . '/Market/YahooChartProvider.php';
require_once __DIR__ . '/Market/CoinGeckoProvider.php';
require_once __DIR__ . '/Market/MarketRepository.php';
require_once __DIR__ . '/Market/MarketRefreshService.php';
require_once __DIR__ . '/Market/MarketPresenter.php';

function market_instruments(): array
{
    static $instruments;
    return $instruments ??= require '/var/www/config/markets.php';
}

function market_repository(): MarketRepository
{
    static $repository;
    if (!$repository instanceof MarketRepository) {
        $repository = new MarketRepository(Database::connection());
        $repository->seedSources(require '/var/www/config/sources.php');
    }
    return $repository;
}

function market_refresh_service(): MarketRefreshService
{
    static $service;
    return $service ??= new MarketRefreshService(
        market_repository(),
        market_instruments(),
        [
            new YahooChartProvider(new HttpClient()),
            new CoinGeckoProvider(new HttpClient()),
        ],
    );
}

function market_snapshot(): array
{
    $cacheMinutes = max(1, (int) (getenv('FINANCE_CACHE_MINUTES') ?: 60));
    return MarketPresenter::presentSnapshot(
        market_repository()->latestSnapshot(market_instruments(), $cacheMinutes)
    );
}

