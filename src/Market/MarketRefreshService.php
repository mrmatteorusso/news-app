<?php

declare(strict_types=1);

final class MarketRefreshService
{
    /** @var array<string, MarketProviderInterface> */
    private array $providers = [];

    public function __construct(
        private readonly MarketRepository $repository,
        private readonly array $instruments,
        array $providers,
    ) {
        foreach ($providers as $provider) {
            $this->providers[$provider->key()] = $provider;
        }
    }

    public function refresh(string $triggerType): array
    {
        $cacheMinutes = max(1, (int) (getenv('FINANCE_CACHE_MINUTES') ?: 60));
        if ($triggerType === 'page_open' && $this->repository->isFinanceFresh($cacheMinutes)) {
            return [
                'skipped_cache' => true,
                'result' => ['status' => 'cached', 'selected_count' => count($this->instruments), 'warning' => null],
                'snapshot' => $this->repository->latestSnapshot($this->instruments, $cacheMinutes),
            ];
        }

        $batchId = 'FIN-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $this->repository->startBatch($batchId, $triggerType);

        try {
            $states = $this->repository->instrumentStates();
            $grouped = [];
            foreach ($this->instruments as $instrument) {
                $grouped[$instrument['provider']][] = $instrument;
            }

            $results = [];
            $calls = [];
            $errors = [];
            foreach ($grouped as $providerKey => $providerInstruments) {
                if (!isset($this->providers[$providerKey])) {
                    $errors[] = 'No provider is configured for ' . $providerKey . '.';
                    continue;
                }
                $providerResult = $this->providers[$providerKey]->fetch($providerInstruments, $states);
                $results = array_replace($results, $providerResult['results']);
                $calls = array_merge($calls, $providerResult['calls']);
                $errors = array_merge($errors, $providerResult['errors']);
            }

            $result = $this->repository->completeBatch($batchId, $results, $calls, $errors, count($this->instruments));
            return [
                'skipped_cache' => false,
                'batch_id' => $batchId,
                'result' => $result,
                'snapshot' => $this->repository->latestSnapshot($this->instruments, $cacheMinutes),
            ];
        } catch (Throwable $exception) {
            $this->repository->failRunningBatch($batchId, $exception->getMessage());
            throw $exception;
        }
    }
}

