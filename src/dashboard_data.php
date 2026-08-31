<?php

declare(strict_types=1);

$now = new DateTimeImmutable();

$sections = [
    'breaking' => ['icon' => '●', 'title' => 'Breaking / Critical', 'status' => 'ready', 'state' => 'Current mock batch', 'updated' => mock_time('-18 minutes'), 'batch' => 'BRK-DEMO-01'],
    'finance' => ['icon' => '◆', 'title' => 'Finance & Markets', 'status' => 'ready', 'state' => 'Current mock batch', 'updated' => mock_time('-22 minutes'), 'batch' => 'FIN-DEMO-01'],
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

$markets = [
    ['name' => 'S&P 500', 'symbol' => 'SPX', 'value' => '6,248.10', 'currency' => 'USD', 'day' => '-1.2%', 'from_high' => '-3.8%', 'high' => '6,493.20', 'direction' => 'down'],
    ['name' => 'Vanguard All-World', 'symbol' => 'IE00BK5BQT80', 'value' => '€142.50', 'currency' => 'EUR', 'day' => '-0.8%', 'from_high' => '-4.1%', 'high' => '€148.59', 'direction' => 'down'],
    ['name' => 'S&P 500 UCITS Acc', 'symbol' => 'VUAG', 'value' => '€118.42', 'currency' => 'EUR', 'day' => '-1.1%', 'from_high' => '-3.6%', 'high' => '€122.84', 'direction' => 'down'],
    ['name' => 'Developed Europe Acc', 'symbol' => 'VEUA', 'value' => '€38.74', 'currency' => 'EUR', 'day' => '+0.4%', 'from_high' => '-2.2%', 'high' => '€39.61', 'direction' => 'up'],
    ['name' => 'Emerging Markets Acc', 'symbol' => 'VFEG', 'value' => '€54.17', 'currency' => 'EUR', 'day' => '+0.7%', 'from_high' => '-6.5%', 'high' => '€57.94', 'direction' => 'up'],
    ['name' => 'Japan Acc', 'symbol' => 'VJPB', 'value' => '€31.66', 'currency' => 'EUR', 'day' => '-0.3%', 'from_high' => '-4.8%', 'high' => '€33.26', 'direction' => 'down'],
    ['name' => 'Euro Government Bond 1–3Y', 'symbol' => 'ETF candidate', 'value' => '€101.32', 'currency' => 'EUR', 'day' => '+0.1%', 'from_high' => '-1.4%', 'high' => '€102.76', 'direction' => 'up'],
    ['name' => 'Gold', 'symbol' => 'XAU/USD', 'value' => '$3,450', 'currency' => 'USD', 'day' => '+0.6%', 'from_high' => '-1.2%', 'high' => '$3,492', 'direction' => 'up'],
    ['name' => 'Bitcoin', 'symbol' => 'BTC', 'value' => '$112,000', 'currency' => 'USD', 'day' => '-3.4%', 'from_high' => '-8.2%', 'high' => '$122,004', 'direction' => 'down'],
    ['name' => 'Ethereum', 'symbol' => 'ETH', 'value' => '$4,420', 'currency' => 'USD', 'day' => '-4.1%', 'from_high' => '-12.3%', 'high' => '$5,040', 'direction' => 'down'],
    ['name' => 'Cardano', 'symbol' => 'ADA', 'value' => '$1.18', 'currency' => 'USD', 'day' => '+1.7%', 'from_high' => '-61.9%', 'high' => '$3.10', 'direction' => 'up'],
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
