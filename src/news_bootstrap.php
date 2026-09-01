<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/Feedback/FeedbackRepository.php';
require_once __DIR__ . '/Llm/LlmClientInterface.php';
require_once __DIR__ . '/Llm/LmStudioClient.php';
require_once __DIR__ . '/Llm/ProfileLoader.php';
require_once __DIR__ . '/Llm/LlmRepository.php';
require_once __DIR__ . '/Llm/LlmEnrichmentService.php';
require_once __DIR__ . '/News/FeedParser.php';
require_once __DIR__ . '/News/NewsRepository.php';
require_once __DIR__ . '/News/TopicTagger.php';
require_once __DIR__ . '/News/NewsRefreshService.php';
require_once __DIR__ . '/News/NewsPresenter.php';
require_once __DIR__ . '/Ranking/DeterministicRanker.php';
require_once __DIR__ . '/Ranking/StoryClusterer.php';
require_once __DIR__ . '/Ranking/RankingRepository.php';
require_once __DIR__ . '/Ranking/RankingService.php';

function news_config(): array
{
    static $config;
    return $config ??= require '/var/www/config/news.php';
}

function configured_sources(): array
{
    static $sources;
    return $sources ??= require '/var/www/config/sources.php';
}

function ranking_config(): array
{
    static $config;
    return $config ??= require '/var/www/config/ranking.php';
}

function llm_config(): array
{
    static $config;
    return $config ??= require '/var/www/config/llm.php';
}

function topic_config(): array
{
    static $config;
    return $config ??= require '/var/www/config/topics.php';
}

function news_repository(): NewsRepository
{
    static $repository;
    if (!$repository instanceof NewsRepository) {
        $repository = new NewsRepository(Database::connection());
        $repository->seedSources(configured_sources());
    }
    return $repository;
}

function ranking_repository(): RankingRepository
{
    static $repository;
    return $repository ??= new RankingRepository(Database::connection());
}

function ranking_service(): RankingService
{
    static $service;
    $config = ranking_config();
    return $service ??= new RankingService(
        ranking_repository(),
        new DeterministicRanker($config),
        new StoryClusterer(),
        $config,
        news_config(),
    );
}

function feedback_repository(): FeedbackRepository
{
    static $repository;
    return $repository ??= new FeedbackRepository(Database::connection());
}

function llm_repository(): LlmRepository
{
    static $repository;
    return $repository ??= new LlmRepository(Database::connection());
}

function llm_enrichment_service(): LlmEnrichmentService
{
    static $service;
    $config = llm_config();
    return $service ??= new LlmEnrichmentService(
        llm_repository(),
        feedback_repository(),
        new ProfileLoader('/var/www/profiles/templates', '/var/www/profiles/private', $config['profile_map']),
        new LmStudioClient($config),
        $config,
    );
}

function news_refresh_service(): NewsRefreshService
{
    static $service;
    $config = news_config();
    return $service ??= new NewsRefreshService(
        news_repository(),
        new FeedParser($config['max_items_per_source'], $config['excerpt_characters']),
        new HttpClient(),
        new TopicTagger(topic_config()),
        configured_sources(),
        $config,
        ranking_service(),
        llm_enrichment_service(),
    );
}

function news_snapshot(string $section): array
{
    return NewsPresenter::presentSnapshot(news_refresh_service()->snapshot($section));
}
