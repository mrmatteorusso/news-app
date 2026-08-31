<?php

declare(strict_types=1);

$now = new DateTimeImmutable();

$sections = [
    'breaking' => ['icon' => '●', 'title' => 'Breaking / Critical', 'status' => 'ready', 'state' => 'Current mock batch', 'updated' => mock_time('-18 minutes'), 'batch' => 'BRK-DEMO-01'],
    'finance' => ['icon' => '◆', 'title' => 'Finance & Markets', 'status' => 'ready', 'state' => 'Current mock batch', 'updated' => mock_time('-22 minutes'), 'batch' => 'FIN-DEMO-01'],
    'crypto' => ['icon' => '₿', 'title' => 'Crypto News', 'status' => 'ready', 'state' => 'Current mock batch', 'updated' => mock_time('-90 minutes'), 'batch' => 'CRY-DEMO-01'],
    'ai' => ['icon' => '✦', 'title' => 'AI / Technology / Business', 'status' => 'ready', 'state' => 'Current mock batch', 'updated' => mock_time('-2 hours'), 'batch' => 'AI-DEMO-01'],
    'x' => ['icon' => '𝕏', 'title' => 'Curated X Signals', 'status' => 'ready', 'state' => 'Signals only — not verification', 'updated' => mock_time('-2 hours'), 'batch' => 'X-DEMO-01'],
    'italy' => ['icon' => 'IT', 'title' => 'Italy', 'status' => 'ready', 'state' => 'Current mock batch', 'updated' => mock_time('-3 hours'), 'batch' => 'ITA-DEMO-01'],
    'local' => ['icon' => '▲', 'title' => 'My Area', 'status' => 'ready', 'state' => 'Current mock batch', 'updated' => mock_time('-3 hours'), 'batch' => 'LOC-DEMO-01'],
];

$breakingStories = [
    [
        'tag' => 'DEMO · Global security',
        'confidence' => 'High',
        'headline' => 'Illustrative major event: an unexpected government transition begins',
        'summary' => 'This fictional headline demonstrates the level of consequence required for the Breaking section. Routine political commentary would not appear here.',
        'why' => 'A sudden national leadership change could affect security, markets, and international policy.',
        'source' => 'BBC example',
        'published' => mock_time('-49 minutes'),
        'source_updated' => mock_time('-31 minutes'),
        'retrieved' => mock_time('-18 minutes'),
        'url' => 'https://www.bbc.com/news/world',
    ],
    [
        'tag' => 'DEMO · Financial stability',
        'confidence' => 'High',
        'headline' => 'Illustrative emergency central-bank action follows a systemic banking shock',
        'summary' => 'This fictional example shows a second event that could justify Critical status when official action confirms broad financial risk.',
        'why' => 'Emergency intervention may affect deposits, credit, markets, and confidence across several countries.',
        'source' => 'Central-bank example',
        'published' => mock_time('-2 hours 8 minutes'),
        'source_updated' => mock_time('-1 hour 42 minutes'),
        'retrieved' => mock_time('-18 minutes'),
        'url' => 'https://www.ecb.europa.eu/press/html/index.en.html',
    ],
];

$financeStories = [
    [
        'tag' => 'DEMO · Central banks', 'confidence' => 'High',
        'headline' => 'Illustrative policy decision changes the expected interest-rate path',
        'summary' => 'A short factual summary would explain exactly what changed, without repeating routine market commentary.',
        'why' => 'The decision could affect borrowing costs, currencies, bonds, and long-term portfolios.',
        'source' => 'ECB example', 'published' => mock_time('-3 hours'), 'source_updated' => mock_time('-2 hours 45 minutes'), 'retrieved' => mock_time('-22 minutes'),
        'url' => 'https://www.ecb.europa.eu/press/html/index.en.html',
    ],
    [
        'tag' => 'DEMO · Government budget', 'confidence' => 'Medium',
        'headline' => 'Illustrative final budget package introduces material tax and spending changes',
        'summary' => 'The selected card would isolate approved measures with broad consequences and omit minor parliamentary amendments.',
        'why' => 'Final tax and spending rules can directly affect households, businesses, growth, and markets.',
        'source' => 'European Commission example', 'published' => mock_time('-5 hours'), 'source_updated' => mock_time('-4 hours 20 minutes'), 'retrieved' => mock_time('-22 minutes'),
        'url' => 'https://commission.europa.eu/news-and-media_en',
    ],
    [
        'tag' => 'DEMO · Trade policy', 'confidence' => 'High',
        'headline' => 'Illustrative tariff package materially changes transatlantic trade costs',
        'summary' => 'The selected card would identify the approved rates, affected sectors, effective date, and likely second-order effects.',
        'why' => 'A broad tariff change can alter inflation, company margins, supply chains, and growth expectations.',
        'source' => 'EU primary-source example', 'published' => mock_time('-7 hours'), 'source_updated' => mock_time('-6 hours 20 minutes'), 'retrieved' => mock_time('-22 minutes'),
        'url' => 'https://commission.europa.eu/news-and-media_en',
    ],
    [
        'tag' => 'DEMO · Banking', 'confidence' => 'Medium',
        'headline' => 'Illustrative regional lender discloses losses large enough to require intervention',
        'summary' => 'The dashboard would distinguish a contained company problem from evidence of wider banking-system stress.',
        'why' => 'Material bank weakness can restrict credit and become systemic if similar exposures exist elsewhere.',
        'source' => 'Financial authority example', 'published' => mock_time('-8 hours'), 'source_updated' => mock_time('-7 hours 30 minutes'), 'retrieved' => mock_time('-22 minutes'),
        'url' => 'https://www.bankingsupervision.europa.eu/home/html/index.en.html',
    ],
];

$cryptoStories = [
    [
        'tag' => 'DEMO · Bitcoin', 'confidence' => 'High',
        'headline' => 'Illustrative Bitcoin development materially changes institutional access',
        'summary' => 'The live card would identify the confirmed rule, product, or infrastructure change and separate it from ordinary price commentary.',
        'why' => 'A structural access change can affect liquidity, ownership, regulation, and long-term adoption.',
        'source' => 'Primary-source example', 'published' => mock_time('-3 hours'), 'source_updated' => mock_time('-2 hours 40 minutes'), 'retrieved' => mock_time('-90 minutes'),
        'url' => 'https://bitcoin.org/en/blog',
    ],
    [
        'tag' => 'DEMO · Ethereum', 'confidence' => 'High',
        'headline' => 'Illustrative Ethereum upgrade reaches confirmed activation',
        'summary' => 'Coverage would focus on the practical effect on cost, capacity, security, staking, or application development.',
        'why' => 'A major network upgrade can change Ethereum’s economics and what developers can reliably build.',
        'source' => 'Ethereum Foundation example', 'published' => mock_time('-5 hours'), 'source_updated' => mock_time('-4 hours 20 minutes'), 'retrieved' => mock_time('-90 minutes'),
        'url' => 'https://blog.ethereum.org/',
    ],
    [
        'tag' => 'DEMO · Cardano / ADA', 'confidence' => 'Medium',
        'headline' => 'Illustrative Cardano governance decision changes protocol funding',
        'summary' => 'The selected item would explain what was approved, the amount or mechanism involved, and the likely network consequences.',
        'why' => 'Material governance and treasury decisions can affect development priorities and ADA holder expectations.',
        'source' => 'Cardano Foundation example', 'published' => mock_time('-7 hours'), 'source_updated' => mock_time('-6 hours 35 minutes'), 'retrieved' => mock_time('-90 minutes'),
        'url' => 'https://cardanofoundation.org/en/news/',
    ],
    [
        'tag' => 'DEMO · Regulation / market structure', 'confidence' => 'High',
        'headline' => 'Illustrative regulation materially changes custody or stablecoin obligations',
        'summary' => 'General crypto news appears only when it changes legal access, systemic risk, infrastructure, security, or the market’s structure.',
        'why' => 'Broad rules can affect exchanges, custody, liquidity, users, and the viability of crypto businesses.',
        'source' => 'Regulator example', 'published' => mock_time('-10 hours'), 'source_updated' => mock_time('-9 hours 15 minutes'), 'retrieved' => mock_time('-90 minutes'),
        'url' => 'https://www.esma.europa.eu/press-news/esma-news',
    ],
];

$aiStories = [
    [
        'tag' => 'DEMO · Major model release', 'confidence' => 'High',
        'headline' => 'Illustrative open model improves reliable tool use on ordinary hardware',
        'summary' => 'The card would focus on the practical capability change, access conditions, and who can use it—not benchmark noise.',
        'why' => 'Useful local tool use could make private, low-cost automation accessible to small businesses.',
        'business_angle' => 'Local deployment may create demand for installation, workflow design, and ongoing support.',
        'source' => 'Model developer example', 'published' => mock_time('-4 hours'), 'source_updated' => mock_time('-3 hours 40 minutes'), 'retrieved' => mock_time('-2 hours'),
        'url' => 'https://huggingface.co/models',
    ],
    [
        'tag' => 'DEMO · AI adoption', 'confidence' => 'Medium',
        'headline' => 'Illustrative company redesigns a paid operational workflow around AI',
        'summary' => 'Evidence of real deployment, customer demand, cost, and limitations would be prioritised over a generic partnership announcement.',
        'why' => 'Measured adoption provides evidence about where AI is actually creating—or failing to create—value.',
        'business_angle' => 'Repeated integration problems may indicate a service opportunity for specialised implementers.',
        'source' => 'Company source example', 'published' => mock_time('-6 hours'), 'source_updated' => mock_time('-5 hours 35 minutes'), 'retrieved' => mock_time('-2 hours'),
        'url' => 'https://news.ycombinator.com/',
    ],
    [
        'tag' => 'DEMO · Chinese open source', 'confidence' => 'High',
        'headline' => 'Illustrative Chinese model release targets efficient local inference',
        'summary' => 'The useful facts would be licence, model size, memory requirement, languages, tool support, and independently reproducible limitations.',
        'why' => 'Efficient open models can reduce dependency on paid APIs and expand private local workflows.',
        'business_angle' => 'Low-cost local inference may make specialist automation viable for privacy-sensitive small firms.',
        'source' => 'Developer release example', 'published' => mock_time('-8 hours'), 'source_updated' => mock_time('-7 hours 25 minutes'), 'retrieved' => mock_time('-2 hours'),
        'url' => 'https://huggingface.co/models',
    ],
    [
        'tag' => 'DEMO · Coding agents', 'confidence' => 'Medium',
        'headline' => 'Illustrative coding agent gains a verifiable long-running workflow',
        'summary' => 'Selection would require a material capability change, practical evidence, and clear access conditions—not a small interface update.',
        'why' => 'Long-running verified work could change how small teams build and maintain software.',
        'business_angle' => 'Teams may need help redesigning review, testing, and accountability around agent-produced changes.',
        'source' => 'Official product example', 'published' => mock_time('-10 hours'), 'source_updated' => mock_time('-9 hours 10 minutes'), 'retrieved' => mock_time('-2 hours'),
        'url' => 'https://openai.com/news/',
    ],
    [
        'tag' => 'DEMO · Cybersecurity', 'confidence' => 'High',
        'headline' => 'Illustrative infrastructure vulnerability triggers coordinated emergency patches',
        'summary' => 'A concise card would identify affected systems, exploitation status, remediation, and authoritative advisories.',
        'why' => 'A widely deployed vulnerable component can create immediate operational and financial risk.',
        'business_angle' => 'Rapid inventory and patch-assurance services become valuable during ecosystem-wide incidents.',
        'source' => 'Security authority example', 'published' => mock_time('-11 hours'), 'source_updated' => mock_time('-9 hours 40 minutes'), 'retrieved' => mock_time('-2 hours'),
        'url' => 'https://www.cisa.gov/news-events/cybersecurity-advisories',
    ],
];

$italyStories = [
    [
        'tag' => 'DEMO · Rules and obligations', 'confidence' => 'High',
        'headline' => 'Illustrative national digital-identity requirement receives a firm start date',
        'summary' => 'A concise card would say who is affected, what must be done, and when the change begins.',
        'why' => 'A new administrative obligation could require personal action and affect access to public services.',
        'source' => 'Italian Government example', 'published' => mock_time('-7 hours'), 'source_updated' => mock_time('-6 hours 15 minutes'), 'retrieved' => mock_time('-3 hours'),
        'url' => 'https://www.governo.it/',
    ],
    [
        'tag' => 'DEMO · Taxation', 'confidence' => 'High',
        'headline' => 'Illustrative tax deadline and eligibility rule change receives final approval',
        'summary' => 'The card would state the affected taxpayers, new deadline, required action, and official reference.',
        'why' => 'Missing an approved tax change could cause unnecessary cost, penalties, or lost eligibility.',
        'source' => 'Agenzia delle Entrate example', 'published' => mock_time('-9 hours'), 'source_updated' => mock_time('-8 hours 15 minutes'), 'retrieved' => mock_time('-3 hours'),
        'url' => 'https://www.agenziaentrate.gov.it/',
    ],
    [
        'tag' => 'DEMO · Healthcare', 'confidence' => 'Medium',
        'headline' => 'Illustrative national prescription procedure changes from a confirmed date',
        'summary' => 'The useful summary would focus on who must change behaviour and which official service or document is required.',
        'why' => 'A procedural change could affect timely access to medicines and healthcare administration.',
        'source' => 'Health Ministry example', 'published' => mock_time('-12 hours'), 'source_updated' => mock_time('-11 hours 5 minutes'), 'retrieved' => mock_time('-3 hours'),
        'url' => 'https://www.salute.gov.it/',
    ],
    [
        'tag' => 'DEMO · Energy support', 'confidence' => 'Medium',
        'headline' => 'Illustrative household energy support scheme changes its income threshold',
        'summary' => 'The selected item would include effective date, eligibility, application route, and whether action is automatic.',
        'why' => 'The revised threshold may change household costs or require an application.',
        'source' => 'Italian Government example', 'published' => mock_time('-14 hours'), 'source_updated' => mock_time('-13 hours 20 minutes'), 'retrieved' => mock_time('-3 hours'),
        'url' => 'https://www.governo.it/',
    ],
];

$localStories = [
    [
        'tag' => 'DEMO · Alta Valtellina · Transport', 'confidence' => 'High',
        'headline' => 'Illustrative overnight road closure affects the main route near Bormio',
        'summary' => 'The card would show the exact road, closure window, diversion, responsible authority, and original notice.',
        'why' => 'Knowing before travelling could save time, disruption, and an unsafe diversion.',
        'source' => 'Municipality example', 'published' => mock_time('-5 hours'), 'source_updated' => mock_time('-4 hours 35 minutes'), 'retrieved' => mock_time('-3 hours'),
        'url' => 'https://www.comune.bormio.so.it/',
    ],
    [
        'tag' => 'DEMO · Lombardy · Healthcare', 'confidence' => 'Medium',
        'headline' => 'Illustrative regional healthcare procedure changes next month',
        'summary' => 'The dashboard would surface the practical change, effective date, and people affected rather than routine institutional news.',
        'why' => 'The change may affect appointments, documents, or access to a service.',
        'source' => 'Regione Lombardia example', 'published' => mock_time('-9 hours'), 'source_updated' => mock_time('-8 hours 10 minutes'), 'retrieved' => mock_time('-3 hours'),
        'url' => 'https://www.regione.lombardia.it/',
    ],
    [
        'tag' => 'DEMO · Sondrio · Public works', 'confidence' => 'High',
        'headline' => 'Illustrative tunnel maintenance changes overnight access for four days',
        'summary' => 'The live card would include exact closure windows, vehicle restrictions, diversions, and the responsible authority.',
        'why' => 'Advance notice can prevent a long diversion and missed appointments or deliveries.',
        'source' => 'Provincia di Sondrio example', 'published' => mock_time('-10 hours'), 'source_updated' => mock_time('-9 hours 5 minutes'), 'retrieved' => mock_time('-3 hours'),
        'url' => 'https://www.provinciasondrio.it/',
    ],
    [
        'tag' => 'DEMO · Livigno · Services', 'confidence' => 'Medium',
        'headline' => 'Illustrative scheduled utility interruption affects several local streets',
        'summary' => 'A practical notice would list streets, start and end time, expected impact, and the operator’s update link.',
        'why' => 'Knowing early allows residents and businesses to prepare and avoid disruption.',
        'source' => 'Municipal example', 'published' => mock_time('-13 hours'), 'source_updated' => mock_time('-12 hours 25 minutes'), 'retrieved' => mock_time('-3 hours'),
        'url' => 'https://www.comune.livigno.so.it/',
    ],
];

$xSignals = [
    ['account' => '@OpenAI', 'topic' => 'AI product access', 'text' => 'Demo signal: a product-access change is being discussed. The app would verify it against an official announcement before treating it as news.', 'url' => 'https://x.com/OpenAI'],
    ['account' => '@AnthropicAI', 'topic' => 'Agents', 'text' => 'Demo signal: a new agent capability may warrant a closer look if it changes real workflows.', 'url' => 'https://x.com/AnthropicAI'],
    ['account' => '@GoogleDeepMind', 'topic' => 'Research to product', 'text' => 'Demo signal: a research result appears to have a practical product path, pending primary-source review.', 'url' => 'https://x.com/GoogleDeepMind'],
    ['account' => '@huggingface', 'topic' => 'Open source', 'text' => 'Demo signal: an open model release may be relevant for local 16 GB machines.', 'url' => 'https://x.com/huggingface'],
    ['account' => '@TechCrunch', 'topic' => 'AI business', 'text' => 'Demo signal: reported customer adoption could reveal a commercially useful pattern after corroboration.', 'url' => 'https://x.com/TechCrunch'],
];
