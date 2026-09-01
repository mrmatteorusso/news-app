# Personal News Intelligence Dashboard

A small, local-first dashboard designed to answer one question:

> What happened that is important enough for me to know?

Stage 5 provides a responsive interface, live cached finance data, keyless RSS/Atom ingestion for Breaking, Finance, Crypto, and AI, deterministic ranking and clustering, and a private Gemma 3 4B review through LM Studio. X, Italy, and local intelligence remain later stages.

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
- LM Studio's OpenAI-compatible local server and Gemma 3 4B for private profile-guided review

LM Studio remains a separate Windows application. From inside Docker, PHP reaches it at `http://host.docker.internal:1234/v1`; the model does not run inside this container.

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

- Every successful feed batch triggers the versioned `deterministic-v3-fixed-representative` evaluator. An existing fresh batch is ranked automatically the next time the app reads it.
- The total score combines importance (32%), relevance (28%), evidence (18%), practical impact (12%), and novelty (10%). Category-specific total, importance, and relevance gates prevent a high source score alone from selecting a minor item.
- Similar titles inside a category time window form one event cluster. One representative appears with links to related publishers; every article remains in the 90-day archive.
- PHP assigns a small controlled set of topic/entity tags. Categories remain the visible navigation and analytics groups; a single stored article may belong to more than one category.
- Evidence improves only when a cluster contains distinct publishers. Repeated reports from one publisher do not inflate corroboration.
- Primary sources are preferred as representatives when they are close to the highest-scoring report. Unsupported community signals and contrarian analysis cannot be promoted as facts.
- Each selected card shows the full score breakdown, corroboration count, and deterministic reason. The dashboard has no five-item cap: all qualifying cluster representatives may appear up to the section display limit.
- Ranking runs, component scores, selection decisions, explanations, and cluster membership are stored durably in SQLite. If ranking fails, the previous successful ranked batch remains visible.

The live thresholds, latest run counts, and evidence policy are visible at <http://localhost:8080/methodology.php>.

## Stage 5 local-AI behaviour

- Install or open LM Studio, download and load **Gemma 3 4B Instruct** in a 4-bit GGUF quantization, then open **Developer** and press **Start Server** on port `1234`. Leave that server running while you want AI reviews. This is a one-time action per LM Studio session, not something you repeat for each article or refresh.
- Opening the dashboard displays SQLite immediately, refreshes stale feeds in the background, and then queues Gemma reviews one category at a time. Manual category refresh and **Refresh all** use the same feed → ranking → local-AI sequence.
- Gemma reviews up to ten highest-ranked event-cluster representatives per category, processed in chunks of eight and two. The full candidate set and 90-day archive remain in SQLite. Gemma sees the general Markdown profile, matching category profile, recent feedback, titles, short feed excerpts, source metadata, timestamps, score components, and corroboration counts. It does not browse websites or receive full article bodies.
- Gemma is a selector, not a writer. For every candidate it returns only keep/reject plus one restricted reason code. Publisher titles and feed excerpts remain unchanged on the dashboard; Gemma does not generate summaries, prose explanations, tags, relevance scores, or business angles.
- After validation, PHP applies a deterministic diversity guard: selected reports sharing an event term and a specific event anchor are reduced to the highest-ranked representative. This compensates for small models occasionally retaining secondary angles despite the prompt.
- The original deterministic reason remains inspectable. Each card can be marked **Useful**, **Too minor**, **Wrong category**, or **Not useful**; this append-only feedback becomes soft guidance on the next review.
- A profile edit changes its hash and automatically makes the old AI review stale. The deterministic briefing remains visible until Gemma completes a review under the new profile.
- If LM Studio is closed, the model is missing, a request times out, or the output is malformed, no partial AI data is published. The last valid AI briefing is retained when one exists; otherwise all deterministic Stage 4 survivors remain visible.

To personalise the reader profile without publishing it, copy the relevant file from `profiles/templates/` to `profiles/private/` and edit the private copy. A non-empty private file overrides the template of the same name. Private profiles are ignored by Git.

Optional environment settings are documented in `.env.example`. The default configuration needs no API key, cloud account, or ChatGPT subscription; inference runs locally on your PC.

For this 16 GB PC, load Gemma in LM Studio with an 8,192-token context and **parallelism 1**. The application deliberately sends one review at a time; higher LM Studio parallelism reduces individual-request speed and consumes extra memory.

## Privacy and GitHub

Runtime databases, `.env` files, logs, and `profiles/private/` are excluded from Git. Commit the reusable templates and code, not your personal reading history or secrets.

## Planned stages

1. Static interface with mock data — complete
2. Finance providers, calculations, SQLite caching, and refresh — complete
3. RSS/public-feed ingestion — complete
4. Deterministic ranking, corroboration, and story clustering — complete
5. Minimal Gemma final selection, reason-code telemetry, and feedback — complete
6. Lombardy, Sondrio, and Alta Valtellina local intelligence
