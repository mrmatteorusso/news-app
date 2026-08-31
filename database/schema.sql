PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS sources (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    category TEXT NOT NULL,
    geography TEXT NOT NULL,
    source_type TEXT NOT NULL,
    trust_level INTEGER NOT NULL CHECK (trust_level BETWEEN 1 AND 5),
    enabled INTEGER NOT NULL DEFAULT 1 CHECK (enabled IN (0, 1)),
    refresh_method TEXT NOT NULL,
    notes TEXT
);

CREATE TABLE IF NOT EXISTS refresh_batches (
    id TEXT PRIMARY KEY,
    section TEXT NOT NULL,
    trigger_type TEXT NOT NULL CHECK (trigger_type IN ('page_open', 'manual_section', 'manual_all')),
    status TEXT NOT NULL CHECK (status IN ('running', 'success', 'partial', 'failed')),
    started_at TEXT NOT NULL,
    completed_at TEXT,
    candidate_count INTEGER NOT NULL DEFAULT 0,
    selected_count INTEGER NOT NULL DEFAULT 0,
    error_summary TEXT
);

CREATE TABLE IF NOT EXISTS source_fetches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_id TEXT NOT NULL REFERENCES refresh_batches(id) ON DELETE CASCADE,
    source_id TEXT NOT NULL REFERENCES sources(id),
    request_kind TEXT NOT NULL DEFAULT 'scan' CHECK (request_kind IN ('api', 'scan', 'embed')),
    status TEXT NOT NULL CHECK (status IN ('success', 'failed', 'skipped')),
    http_status INTEGER,
    item_count INTEGER NOT NULL DEFAULT 0,
    started_at TEXT NOT NULL,
    completed_at TEXT,
    error_message TEXT
);

CREATE TABLE IF NOT EXISTS market_instrument_state (
    instrument_key TEXT PRIMARY KEY,
    provider TEXT NOT NULL,
    provider_symbol TEXT NOT NULL,
    currency TEXT,
    highest_close REAL,
    highest_close_at TEXT,
    history_checked_at TEXT,
    last_provider_timestamp TEXT,
    last_success_at TEXT,
    last_error TEXT,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS articles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    canonical_url TEXT NOT NULL UNIQUE,
    source_id TEXT NOT NULL REFERENCES sources(id),
    title TEXT NOT NULL,
    excerpt TEXT,
    author TEXT,
    published_at TEXT,
    source_updated_at TEXT,
    first_retrieved_at TEXT NOT NULL,
    last_retrieved_at TEXT NOT NULL,
    content_hash TEXT,
    raw_payload TEXT,
    expires_at TEXT
);

CREATE TABLE IF NOT EXISTS article_evaluations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    batch_id TEXT NOT NULL REFERENCES refresh_batches(id) ON DELETE CASCADE,
    section TEXT NOT NULL,
    deterministic_score REAL NOT NULL,
    importance_score INTEGER CHECK (importance_score BETWEEN 0 AND 100),
    relevance_score INTEGER CHECK (relevance_score BETWEEN 0 AND 100),
    evidence_confidence INTEGER CHECK (evidence_confidence BETWEEN 0 AND 100),
    practical_impact_score INTEGER CHECK (practical_impact_score BETWEEN 0 AND 100),
    novelty_score INTEGER CHECK (novelty_score BETWEEN 0 AND 100),
    selected INTEGER NOT NULL DEFAULT 0 CHECK (selected IN (0, 1)),
    summary TEXT,
    why_selected TEXT,
    business_angle TEXT,
    llm_model TEXT,
    evaluated_at TEXT NOT NULL,
    UNIQUE (article_id, batch_id, section)
);

CREATE TABLE IF NOT EXISTS story_clusters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_id TEXT NOT NULL REFERENCES refresh_batches(id) ON DELETE CASCADE,
    section TEXT NOT NULL,
    representative_article_id INTEGER REFERENCES articles(id),
    cluster_key TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS cluster_articles (
    cluster_id INTEGER NOT NULL REFERENCES story_clusters(id) ON DELETE CASCADE,
    article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    PRIMARY KEY (cluster_id, article_id)
);

CREATE TABLE IF NOT EXISTS market_quotes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_id TEXT NOT NULL REFERENCES refresh_batches(id) ON DELETE CASCADE,
    instrument_key TEXT NOT NULL,
    provider TEXT NOT NULL,
    symbol TEXT NOT NULL,
    currency TEXT,
    latest_value REAL NOT NULL,
    reference_value REAL,
    change_percent REAL,
    highest_close REAL,
    from_high_percent REAL,
    provider_timestamp TEXT,
    retrieved_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS section_state (
    section TEXT PRIMARY KEY,
    published_batch_id TEXT REFERENCES refresh_batches(id),
    last_success_at TEXT,
    last_attempt_at TEXT,
    status TEXT NOT NULL DEFAULT 'never' CHECK (status IN ('never', 'ready', 'partial', 'failed')),
    warning TEXT
);

CREATE TABLE IF NOT EXISTS feedback_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id INTEGER REFERENCES articles(id) ON DELETE SET NULL,
    section TEXT NOT NULL,
    action TEXT NOT NULL CHECK (action IN ('useful', 'not_useful', 'too_minor', 'wrong_category', 'hide_source')),
    note TEXT,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS interaction_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_type TEXT NOT NULL CHECK (event_type IN ('section_refresh', 'link_opened')),
    section TEXT NOT NULL,
    article_id INTEGER REFERENCES articles(id) ON DELETE SET NULL,
    batch_id TEXT REFERENCES refresh_batches(id) ON DELETE SET NULL,
    target_url TEXT,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS llm_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_id TEXT NOT NULL REFERENCES refresh_batches(id) ON DELETE CASCADE,
    section TEXT NOT NULL,
    model TEXT NOT NULL,
    candidate_count INTEGER NOT NULL DEFAULT 0,
    prompt_tokens INTEGER,
    completion_tokens INTEGER,
    duration_ms INTEGER NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('success', 'failed', 'skipped')),
    error_message TEXT,
    started_at TEXT NOT NULL,
    completed_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_refresh_batches_section_started
ON refresh_batches(section, started_at DESC);

CREATE INDEX IF NOT EXISTS idx_source_fetches_batch_status
ON source_fetches(batch_id, status);

CREATE INDEX IF NOT EXISTS idx_articles_source_published
ON articles(source_id, published_at DESC);

CREATE INDEX IF NOT EXISTS idx_articles_expires_at
ON articles(expires_at);

CREATE INDEX IF NOT EXISTS idx_evaluations_batch_section_selected
ON article_evaluations(batch_id, section, selected);

CREATE INDEX IF NOT EXISTS idx_market_quotes_instrument_retrieved
ON market_quotes(instrument_key, retrieved_at DESC);

CREATE INDEX IF NOT EXISTS idx_market_quotes_batch_instrument
ON market_quotes(batch_id, instrument_key);

CREATE INDEX IF NOT EXISTS idx_feedback_article_created
ON feedback_events(article_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_interaction_section_type_created
ON interaction_events(section, event_type, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_llm_runs_section_completed
ON llm_runs(section, completed_at DESC);

PRAGMA optimize;

PRAGMA user_version = 2;
