# Personal News Intelligence Dashboard

A small, local-first dashboard designed to answer one question:

> What happened that is important enough for me to know?

Stage 1 provides a responsive interface with demonstration data. It deliberately makes no external news, market, X, Reddit, or LLM requests yet.

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
- SQLite support included in the Docker image for Stage 2 onward
- LM Studio and Qwen3.5 4B planned for Stage 5

LM Studio remains a separate Windows application. From inside Docker, the future PHP LLM adapter will reach it at `http://host.docker.internal:1234/v1`; it does not need to run inside this container.

## Privacy and GitHub

Runtime databases, `.env` files, logs, and `profiles/private/` are excluded from Git. Commit the reusable templates and code, not your personal reading history or secrets.

## Planned stages

1. Static interface with mock data
2. Finance providers, calculations, SQLite caching, and refresh
3. RSS/public-feed ingestion
4. Deterministic ranking and deduplication
5. Qwen relevance, summaries, “Why this was chosen,” and business angles
6. Lombardy, Sondrio, and Alta Valtellina local intelligence
