# Personal News Intelligence Dashboard

A small, local-first dashboard designed to answer one question:

> What happened that is important enough for me to know?

Stage 2 provides a responsive interface, live cached finance data, per-provider health, and durable local telemetry. News, Crypto News, X, Reddit, and Qwen cards remain demonstration data until their later ingestion and ranking stages.

## Start locally

1. Start Docker Desktop.
2. Open this folder in VS Code.
3. In the VS Code terminal run:

   ```powershell
   .\scripts\dashboard.ps1 start
   ```

4. Open <http://localhost:8080>.

Stop it with:

```powershell
.\scripts\dashboard.ps1 stop
```

Check the container with `./scripts/dashboard.ps1 status`. This project helper deliberately selects the working per-user Docker 4.88.1 installation because an obsolete Docker command is still present under `C:\Program Files` on this PC.

## Technology

- PHP 8.3 and Apache
- Vanilla HTML, CSS, and JavaScript
- SQLite for cached quotes, refresh batches, provider health, and local telemetry
- CoinGecko's keyless public API for BTC, ETH, and ADA
- A monitored, replaceable Yahoo chart adapter for indices, ETFs, COMEX gold futures, and EUR/USD conversion
- LM Studio and Qwen3.5 4B planned for Stage 5

LM Studio remains a separate Windows application. From inside Docker, the future PHP LLM adapter will reach it at `http://host.docker.internal:1234/v1`; it does not need to run inside this container.

## Stage 2 finance behaviour

- Opening the dashboard reads SQLite immediately. If the successful finance cache is older than 60 minutes, the browser starts one background refresh.
- Pressing **Refresh** in Finance forces a new provider batch. **Refresh all** also forces Finance while the news sections still run their clearly labelled demonstration refreshes.
- The initial Yahoo refresh downloads daily history to calculate the highest previous close. That history check is cached for seven days; normal refreshes request only recent daily values.
- Each card shows the provider's update time separately from the time this app retrieved it.
- A failed or partial batch never deletes the previous successful values. The section and Source Status page show the problem.
- Gold is shown in euros per gram. The app converts the front-month COMEX futures quote (`GC=F`) from USD per troy ounce using the contemporaneous EUR/USD rate; it is therefore an indicative converted futures value, not a retail physical-gold price. ETF trading currencies are shown exactly as returned by the selected exchange listing.

No API key is required for the Stage 2 providers. The Yahoo endpoint is unofficial, so it is isolated behind an adapter and can be replaced without changing the dashboard or database.

## Privacy and GitHub

Runtime databases, `.env` files, logs, and `profiles/private/` are excluded from Git. Commit the reusable templates and code, not your personal reading history or secrets.

## Planned stages

1. Static interface with mock data — complete
2. Finance providers, calculations, SQLite caching, and refresh — complete
3. RSS/public-feed ingestion
4. Deterministic ranking and deduplication
5. Qwen relevance, summaries, “Why this was chosen,” and business angles
6. Lombardy, Sondrio, and Alta Valtellina local intelligence
