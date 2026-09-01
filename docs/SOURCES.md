# Stage 3 source register

This is the source set that currently populates the local SQLite database. Every active source is free to retrieve through RSS or Atom and requires no API key. “Primary,” “expert,” “contrarian,” and “signal” describe how the dashboard should use a source; they are not blanket endorsements of every item it publishes.

Stage 3 is an intake layer. It stores feed metadata and short feed-supplied excerpts, never full article bodies. A source's trust level controls the visible source label only. Stage 4 will score the individual article, compare sources, cluster duplicates, and decide whether an item is important enough for the briefing.

## Breaking / critical candidates

| Source | Role | Why it is included | Evidence policy |
|---|---|---|---|
| [BBC World](https://feeds.bbci.co.uk/news/world/rss.xml) | Mainstream reporting | Broad, fast global baseline with useful event coverage. | Good discovery and corroboration; use official documents for the decisive claim when available. |
| [The Guardian World](https://www.theguardian.com/world/rss) | Mainstream reporting | Adds international reporting depth and a second editorial lens. | Reporting/corroboration, not a substitute for primary records. |
| [Al Jazeera English](https://www.aljazeera.com/xml/rss/all.xml) | Mainstream, perspective-diverse | Improves coverage outside the usual Europe/US frame and reduces single-region blind spots. | Reporting/corroboration; material claims should be cross-checked. |
| [United Nations News](https://news.un.org/feed/subscribe/en/news/all/rss.xml) | Primary institution | Direct source for UN decisions, humanitarian emergencies, diplomacy, and agency reporting. | Primary for what the UN or its agencies said or did; not automatically an independent account of a dispute. |
| [European Central Bank news](https://www.ecb.europa.eu/rss/press.html) | Primary authority | Direct, time-stamped monetary-policy and financial-stability communication that can itself be breaking news. | Primary for ECB decisions and statements. |

## Finance and markets

| Source / expert | Role | Why it is included | Evidence policy |
|---|---|---|---|
| [BBC Business](https://feeds.bbci.co.uk/news/business/rss.xml) | Mainstream reporting | Broad business and economic discovery without a paid data subscription. | Discovery and corroboration; Stage 4 must remove routine company noise. |
| [European Central Bank news](https://www.ecb.europa.eu/rss/press.html) | Primary authority | Interest rates, monetary policy, financial stability, speeches, and official explanations for the euro area. | Primary for ECB actions and publications. |
| [Calculated Risk — Bill McBride](https://www.calculatedriskblog.com/) | Field expert / independent blog | Long-running, data-led analysis of housing, banking, labour, and the macroeconomy. | Expert interpretation; verify the underlying release and keep opinion distinct from fact. |
| [Wolf Street — Wolf Richter](https://wolfstreet.com/) | Authoritative contrarian / independent blog | A deliberately skeptical lens on debt, housing, banks, central banks, and asset prices helps expose risks hidden by consensus narratives. | Counterweight and hypothesis source only; lower trust weight and require primary-data confirmation. |

## Crypto

| Source / expert | Role | Why it is included | Evidence policy |
|---|---|---|---|
| [Bitcoin Core](https://bitcoincore.org/en/rss/) | Primary technical source | Security notices and release information from the Bitcoin Core project. | Primary for its own software releases and notices. |
| [Ethereum Foundation Blog](https://blog.ethereum.org/) | Primary technical source | Protocol upgrades, security, research, and ecosystem changes directly relevant to Ethereum. | Primary for Foundation announcements; broader ecosystem claims may still need confirmation. |
| [Cardano Forum Announcements](https://forum.cardano.org/c/announcements/13) | Primary community channel | Provides ADA/Cardano-specific governance, infrastructure, release, and ecosystem announcements. | Strong source for the named announcer; identify who made each forum announcement. |
| [CoinDesk](https://www.coindesk.com/) | Specialist reporting | Broad crypto discovery across regulation, markets, companies, infrastructure, and adoption. | Discovery/reporting; consequential claims should link back to filings, regulators, protocols, or named parties. |
| [Protos](https://protos.com/) | Investigative specialist | Useful focus on enforcement, fraud, ownership, market structure, and questionable industry claims. | Investigative lead and reporting; preserve attribution and check original documents. |
| [Web3 Is Going Just Great — Molly White](https://www.web3isgoinggreat.com/) | Documented contrarian / skeptic | Systematic counterweight covering hacks, failures, governance problems, and consumer harm that bullish feeds often underweight. | Skeptical analysis and incident discovery; use cited primary material before treating a claim as established. |

## AI / technology / business

| Source / expert | Role | Why it is included | Evidence policy |
|---|---|---|---|
| [BBC Technology](https://feeds.bbci.co.uk/news/technology/rss.xml) | Mainstream reporting | Broad technology context and major public-impact stories. | Discovery/reporting; Stage 4 excludes routine gadgets and minor releases. |
| [OpenAI News](https://openai.com/news/) | Primary company source | Direct product, research, safety, security, policy, and company announcements. | Primary for what OpenAI released or announced; capability claims still benefit from independent testing. |
| [Google AI](https://blog.google/technology/ai/) | Primary company source | Direct Google/DeepMind research, product, policy, and deployment updates. | Primary for its own releases and statements; benchmark claims need independent context. |
| [Qwen Team Blog](https://qwenlm.github.io/blog/) | Primary Chinese open-model source | Ensures Chinese open-source/open-weight releases, small-model efficiency, tooling, and technical reports are not missed. | Primary for Qwen releases; independent reproduction determines practical quality. |
| [Hugging Face Blog](https://huggingface.co/blog) | Open-source ecosystem | Wide discovery surface for models, libraries, datasets, inference tools, and community work. | Mixed authorship: treat as ecosystem discovery and inspect the author/model card/original repository. |
| [Simon Willison’s Weblog](https://simonwillison.net/) | Field expert / hands-on independent blog | Fast, reproducible testing of LLMs, agents, local tools, APIs, and practical limitations. | Expert analysis and evidence lead; distinguish his tests from vendor claims. |
| [Import AI — Jack Clark](https://importai.substack.com/) | Field expert newsletter | High-level synthesis connecting research capability, policy, safety, and strategic consequences. | Expert interpretation; follow links to papers and primary announcements for final evidence. |
| [Interconnects — Nathan Lambert](https://www.interconnects.ai/) | Field expert / independent blog | Technical analysis of open models, training, post-training, evaluation, and industry strategy. | Expert interpretation; verify material technical claims against papers, code, or model cards. |
| [Hacker News front page](https://news.ycombinator.com/) | Community discovery signal | Finds engineering releases, papers, and startup developments before broad coverage. | Signal only; the linked original page, not comments or popularity, is the evidence. |
| [Reddit r/LocalLLaMA](https://www.reddit.com/r/LocalLLaMA/) | Community discovery signal | Free source of local-model tests, open-model releases, quantisation experience, and practical problems relevant to the user's LM Studio setup. | Signal only, lowest trust tier; never promote a claim without an original source or independent confirmation. |

## Why these sources, and what is deliberately absent

The set is intentionally mixed:

- Primary authorities and project/company sources establish what was officially decided or released.
- Reputable general reporting catches broad events and supplies independent context.
- Field experts add technical or economic interpretation that a general newsroom may not provide.
- Contrarian voices are included to challenge consensus and surface failure modes, but carry a lower evidence weight.
- Community feeds such as Hacker News and Reddit are discovery sensors only.

No paid news API, X API, commercial market-news feed, paywall bypass, or full-page scraper is used. Reuters has no supported public RSS feed in this implementation. X remains a planned curated embed rather than database ingestion. Italy, Lombardy, Sondrio, and Bormio sources also remain planned because they need source-specific page adapters rather than a generic feed parser.

## Retrieval, copyright, and retention

- The app identifies itself as a local personal dashboard and follows ordinary feed refresh intervals rather than polling continuously.
- BBC supplies RSS for personal feed use; its feed terms and attribution requirements still apply. The card always names and links the publisher.
- Only the title, feed-supplied short excerpt, optional author, canonical link, publication/update/retrieval timestamps, and a content hash are stored. `raw_payload` remains `NULL`.
- Common tracking parameters are stripped from stored links. Duplicate canonical URLs are updated rather than inserted again.
- Articles expire after 90 days by default (`ARTICLE_RETENTION_DAYS`), while refresh/source telemetry remains available for the Source Status page.
- Each category has an independent batch and cache interval. A failed batch records the error and preserves the last successful archive.

The active machine-readable URLs and trust tiers are defined in [`config/sources.php`](../config/sources.php); refresh and retention rules are defined in [`config/news.php`](../config/news.php).
