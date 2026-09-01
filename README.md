# Personal News Intelligence Dashboard

A small, local-first dashboard designed to answer one question:

> What happened that is important enough for me to know?

Stage 4 provides a responsive interface, live cached finance data, keyless RSS/Atom ingestion for Breaking, Finance, Crypto, and AI, deterministic ranking, cross-source corroboration, story clustering, per-source health, and durable local telemetry. X, Italy, local intelligence, and Qwen judgment remain later stages.

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
- SQLite for cached quotes, feed metadata, section archives, refresh batches, source health, and local telemetry
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

## Stage 3 news behaviour

- Opening the dashboard reads SQLite immediately; it never waits for the internet before showing the last successful data.
- The browser checks Breaking, Finance News, Crypto, and AI independently. Stale caches are prepared in the background at 15, 30, 45, and 45 minute intervals respectively.
- A section's **Refresh** button forces only that section. Finance refreshes both its market providers and finance-news feeds. **Refresh all** starts each independent group.
- Every refresh creates a batch row and one source-fetch row per configured feed, including HTTP result, accepted item count, and failure text.
- PHP accepts only recent items with a title and valid HTTP link, removes common tracking parameters, converts feed HTML to plain text, and deduplicates canonical URLs.
- SQLite stores titles, short feed excerpts, authors when supplied, original links, source/publish/update/retrieval times, and hashes. It does not store full article bodies or raw feed payloads.
- Articles remain in the local archive for 90 days by default. A failed refresh changes the visible status but never replaces or deletes the last valid stories.
- Before a successful ranking run exists, cards say **LIVE INTAKE** and explain why they entered the candidate pool. Stage 4 replaces them with ranked cluster representatives.

The complete, reviewed source register is in [docs/SOURCES.md](docs/SOURCES.md), including the role, category, reason, and evidence policy for every active feed.

## Stage 4 ranking behaviour

- Every successful feed batch triggers the versioned `deterministic-v2` evaluator. An existing fresh batch is ranked automatically the next time the app reads it.
- The total score combines importance (32%), relevance (28%), evidence (18%), practical impact (12%), and novelty (10%). Category-specific total, importance, and relevance gates prevent a high source score alone from selecting a minor item.
- Similar titles inside a category time window form one story cluster. One representative appears on the dashboard; every related article remains in the 90-day archive.
- Evidence improves only when a cluster contains distinct publishers. Repeated reports from one publisher do not inflate corroboration.
- Primary sources are preferred as representatives when they are close to the highest-scoring report. Unsupported community signals and contrarian analysis cannot be promoted as facts.
- Each selected card shows the full score breakdown, corroboration count, and deterministic reason. The dashboard has no five-item cap: all qualifying cluster representatives may appear up to the section display limit.
- Ranking runs, component scores, selection decisions, explanations, and cluster membership are stored durably in SQLite. If ranking fails, the previous successful ranked batch remains visible.

The live thresholds, latest run counts, and evidence policy are visible at <http://localhost:8080/methodology.php>.

## Privacy and GitHub

Runtime databases, `.env` files, logs, and `profiles/private/` are excluded from Git. Commit the reusable templates and code, not your personal reading history or secrets.

## Planned stages

1. Static interface with mock data — complete
2. Finance providers, calculations, SQLite caching, and refresh — complete
3. RSS/public-feed ingestion — complete
4. Deterministic ranking, corroboration, and story clustering — complete
5. Qwen relevance, summaries, “Why this was chosen,” and business angles
6. Lombardy, Sondrio, and Alta Valtellina local intelligence
