(() => {
    const ACTIVITY_KEY = 'personalBriefing.activity.v1';
    const THEME_KEY = 'personalBriefing.theme';

    const formatTime = () => new Intl.DateTimeFormat(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date());

    const delay = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));
    const toast = document.querySelector('#refresh-toast');
    let toastTimer;

    const readActivity = () => {
        try {
            const activity = JSON.parse(window.localStorage.getItem(ACTIVITY_KEY) || '[]');
            return Array.isArray(activity) ? activity : [];
        } catch (error) {
            return [];
        }
    };

    const recordActivity = (type, section, targetUrl = null) => {
        const activity = readActivity();
        activity.push({ type, section, at: new Date().toISOString() });
        try {
            window.localStorage.setItem(ACTIVITY_KEY, JSON.stringify(activity.slice(-5000)));
        } catch (error) {}

        void fetch('/api/interaction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                event_type: type,
                section,
                target_url: targetUrl,
            }),
            keepalive: type === 'link_opened',
        }).catch(() => {});
    };

    const applyThemeControls = () => {
        const controls = document.querySelectorAll('[data-theme-toggle]');
        const updateControls = () => {
            const dark = document.documentElement.dataset.theme === 'dark';
            controls.forEach((control) => {
                control.setAttribute('aria-pressed', dark ? 'true' : 'false');
                control.innerHTML = `◐ <span>${dark ? 'Light' : 'Dark'}</span>`;
            });
        };

        controls.forEach((control) => {
            control.addEventListener('click', () => {
                const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
                document.documentElement.dataset.theme = nextTheme;
                try {
                    window.localStorage.setItem(THEME_KEY, nextTheme);
                } catch (error) {}
                updateControls();
            });
        });
        updateControls();
    };

    const showToast = (message) => {
        if (!toast) return;
        window.clearTimeout(toastTimer);
        toast.textContent = message;
        toast.classList.add('toast--visible');
        toastTimer = window.setTimeout(() => toast.classList.remove('toast--visible'), 3200);
    };

    const setSectionStatus = (section, status, message) => {
        const state = section.querySelector('[data-section-state]');
        const dot = section.querySelector('.status-dot');
        if (state) state.textContent = message;
        if (dot) {
            dot.classList.remove('status-dot--ready', 'status-dot--working', 'status-dot--partial', 'status-dot--error', 'status-dot--down');
            dot.classList.add(`status-dot--${status}`);
        }
    };

    const applyFinanceSnapshot = (snapshot, section = document.querySelector('[data-section="finance"]')) => {
        if (!snapshot || !section) return;

        (snapshot.markets || []).forEach((market) => {
            const card = section.querySelector(`[data-market-key="${CSS.escape(market.key)}"]`);
            if (!card) return;
            const setField = (name, value) => {
                const field = card.querySelector(`[data-market-field="${name}"]`);
                if (field) field.textContent = value;
            };
            setField('currency', market.currency);
            setField('value', market.value);
            setField('basis', market.change_basis.charAt(0).toUpperCase() + market.change_basis.slice(1));
            setField('change', market.change);
            setField('from-high', market.from_high);
            setField('high', market.high);
            setField('retrieved', market.retrieved);
            setField('provider-updated', market.provider_updated);
            setField('provider', market.provider);

            const change = card.querySelector('[data-market-field="change"]');
            if (change) {
                change.classList.remove('change--up', 'change--down', 'change--flat');
                change.classList.add(`change--${market.direction}`);
            }
        });

        const updated = section.querySelector('[data-last-updated]');
        const batch = section.querySelector('[data-batch-id]');
        if (updated) updated.textContent = snapshot.last_success_display || '—';
        if (batch) batch.textContent = snapshot.batch_id || 'NOT-RUN';

        if (snapshot.status === 'ready') {
            setSectionStatus(section, snapshot.stale ? 'partial' : 'ready', snapshot.stale ? 'Live cache ready · refresh recommended' : 'Live data ready');
        } else if (snapshot.status === 'partial') {
            setSectionStatus(section, 'partial', 'Live data · some providers unavailable');
        } else if (snapshot.status === 'failed') {
            setSectionStatus(section, 'error', snapshot.has_data ? 'Refresh failed · previous live data retained' : 'Live refresh unavailable');
        } else {
            setSectionStatus(section, 'working', 'Waiting for first live refresh');
        }
    };

    const createStoryCard = (story, featured = false) => {
        const article = document.createElement('article');
        article.className = `story-card${featured ? ' story-card--breaking' : ''}`;
        if (story.article_id) article.dataset.articleId = String(story.article_id);

        const meta = document.createElement('div');
        meta.className = 'story-card__meta-top';
        const tag = document.createElement('span');
        tag.className = 'story-card__tag';
        tag.textContent = story.tag || 'LIVE INTAKE';
        meta.append(tag);
        if (story.confidence) {
            const confidence = document.createElement('span');
            confidence.className = 'confidence';
            confidence.textContent = `Evidence: ${story.confidence}`;
            confidence.title = story.confidence_title || 'Source-level trust only; article-level confidence arrives in Stage 4.';
            meta.append(confidence);
        }

        const heading = document.createElement('h3');
        heading.textContent = story.headline || 'Untitled feed item';
        const summary = document.createElement('p');
        summary.textContent = story.summary || '';
        let scoreStrip = null;
        let corroboration = null;
        if (story.score_breakdown && Object.keys(story.score_breakdown).length > 0) {
            scoreStrip = document.createElement('div');
            scoreStrip.className = 'score-strip';
            scoreStrip.setAttribute('aria-label', 'Deterministic ranking score breakdown');
            const total = document.createElement('span');
            total.className = 'score-strip__total';
            total.textContent = `Rank ${Number(story.rank_score || 0).toFixed(1)}`;
            scoreStrip.append(total);
            Object.entries(story.score_breakdown).forEach(([label, score]) => {
                const item = document.createElement('span');
                const name = document.createElement('small');
                name.textContent = label;
                item.append(name, document.createTextNode(` ${score}`));
                scoreStrip.append(item);
            });
            corroboration = document.createElement('p');
            corroboration.className = 'corroboration';
            const corroborationLabel = document.createElement('strong');
            corroborationLabel.textContent = 'Corroboration: ';
            corroboration.append(corroborationLabel, document.createTextNode(story.corroboration || 'Single-source cluster'));
        }
        const why = document.createElement('p');
        why.className = 'why';
        const whyLabel = document.createElement('strong');
        whyLabel.textContent = `${story.why_label || 'Why it appears'}: `;
        why.append(whyLabel, document.createTextNode(story.why || 'Recent feed candidate.'));

        const details = document.createElement('div');
        details.className = 'story-card__details';
        [
            story.source || 'Unknown source',
            `Published ${story.published || 'Not supplied'}`,
            `Source updated ${story.source_updated || 'Not supplied'}`,
            `Retrieved ${story.retrieved || 'Not supplied'}`,
        ].forEach((value) => {
            const span = document.createElement('span');
            span.textContent = value;
            details.append(span);
        });

        const link = document.createElement('a');
        link.className = 'story-link';
        link.href = story.url;
        link.target = '_blank';
        link.rel = 'noreferrer noopener';
        link.dataset.trackLink = '';
        link.append(document.createTextNode(`${story.link_label || 'Open original'} `));
        const arrow = document.createElement('span');
        arrow.setAttribute('aria-hidden', 'true');
        arrow.textContent = '→';
        link.append(arrow);

        article.append(meta, heading, summary);
        if (scoreStrip) article.append(scoreStrip);
        if (corroboration) article.append(corroboration);
        article.append(why, details, link);
        return article;
    };

    const applyNewsSnapshot = (snapshot, section) => {
        if (!snapshot || !section) return;
        const grid = section.querySelector('[data-news-grid]');
        if (grid) {
            grid.replaceChildren(...(snapshot.stories || []).map((story) => createStoryCard(story, section.dataset.section === 'breaking')));
        }
        const empty = section.querySelector('[data-news-empty]');
        if (empty) empty.hidden = (snapshot.stories || []).length > 0;

        const updated = section.querySelector('[data-last-updated]');
        const batch = section.querySelector('[data-batch-id]');
        if (updated) updated.textContent = snapshot.last_success_display || 'Not yet';
        if (batch && snapshot.batch_id) batch.textContent = snapshot.batch_id;

        const countLabel = snapshot.ranking_ready
            ? `${snapshot.selected_count || 0} selected / ${snapshot.candidate_count || 0} evaluated · ${snapshot.cluster_count || 0} clusters · ${snapshot.archive_count || 0} archived`
            : `${snapshot.archive_count || 0} stored · ${(snapshot.stories || []).length} shown`;
        if (snapshot.status === 'ready') {
            setSectionStatus(section, snapshot.stale ? 'partial' : 'ready', snapshot.stale ? `Ranked cache ready · background check due · ${countLabel}` : `Ranked briefing ready · ${countLabel}`);
        } else if (snapshot.status === 'partial') {
            setSectionStatus(section, 'partial', `Ranked briefing ready · some feeds unavailable · ${countLabel}`);
        } else if (snapshot.status === 'failed') {
            setSectionStatus(section, 'error', (snapshot.archive_count || 0) > 0 ? `Feed check failed · previous ranked briefing retained · ${countLabel}` : 'Feed check unavailable');
        } else {
            setSectionStatus(section, 'working', 'Waiting for first feed check');
        }
    };

    const refreshFinanceSection = async (section, quiet = false, trigger = 'manual_section', recordEvent = true) => {
        const button = section.querySelector('[data-refresh-section]');
        if (button) button.disabled = true;
        setSectionStatus(section, 'working', 'Retrieving live market data…');

        try {
            const response = await fetch('/api/finance.php?action=refresh', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ trigger }),
            });
            const payload = await response.json();
            if (payload.snapshot) applyFinanceSnapshot(payload.snapshot, section);
            if (!response.ok || !payload.ok) {
                throw new Error(payload.error || 'Finance refresh failed.');
            }

            if (recordEvent) recordActivity('section_refresh', 'finance');
            if (!quiet) {
                showToast(payload.skipped_cache ? 'Finance cache is already current.' : 'Finance market data refreshed successfully.');
            }
            return true;
        } catch (error) {
            setSectionStatus(section, 'error', 'Refresh failed · previous live data retained');
            if (!quiet) showToast(error.message || 'Finance refresh failed. Previous values were retained.');
            return false;
        } finally {
            if (button) button.disabled = false;
        }
    };

    const refreshNewsSection = async (section, quiet = false, trigger = 'manual_section', recordEvent = true) => {
        const label = section.dataset.section;
        const button = section.querySelector('[data-refresh-section]');
        if (button) button.disabled = true;
        setSectionStatus(section, 'working', 'Checking free RSS and Atom feeds…');

        try {
            const response = await fetch(`/api/news.php?action=refresh&section=${encodeURIComponent(label)}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ trigger }),
            });
            const payload = await response.json();
            if (payload.snapshot) applyNewsSnapshot(payload.snapshot, section);
            if (!response.ok || !payload.ok) throw new Error(payload.error || 'Feed refresh failed.');
            if (recordEvent) recordActivity('section_refresh', label);
            if (!quiet) {
                const count = payload.snapshot?.selected_count || 0;
                showToast(payload.warning || (payload.skipped_cache
                    ? `${label} ranked cache is already current.`
                    : `${label} checked; ${count} distinct stories selected.`));
            }
            return true;
        } catch (error) {
            setSectionStatus(section, 'error', 'Feed check failed · previous intake retained');
            if (!quiet) showToast(error.message || 'Feed refresh failed. Previous stories were retained.');
            return false;
        } finally {
            if (button) button.disabled = false;
        }
    };

    const updateSection = async (section, quiet = false, trigger = 'manual_section') => {
        if (section.dataset.section === 'finance') {
            const [marketsOk, newsOk] = await Promise.all([
                refreshFinanceSection(section, true, trigger, false),
                refreshNewsSection(section, true, trigger, false),
            ]);
            recordActivity('section_refresh', 'finance');
            if (marketsOk && newsOk) {
                setSectionStatus(section, 'ready', 'Live markets and finance-news intake checked');
                if (!quiet) showToast('Finance markets and news intake refreshed.');
            } else {
                setSectionStatus(section, 'partial', 'Finance refresh partial · previous valid data retained');
                if (!quiet) showToast('Finance refresh was partial. Previous valid data was retained.');
            }
            return marketsOk || newsOk;
        }

        if (['breaking', 'crypto', 'ai'].includes(section.dataset.section)) {
            return refreshNewsSection(section, quiet, trigger);
        }

        const button = section.querySelector('[data-refresh-section]');
        const state = section.querySelector('[data-section-state]');
        const dot = section.querySelector('.status-dot');
        const updated = section.querySelector('[data-last-updated]');
        const label = section.dataset.section || 'section';

        if (button) button.disabled = true;
        if (state) state.textContent = 'Checking configured mock sources…';
        if (dot) {
            dot.classList.remove('status-dot--ready');
            dot.classList.add('status-dot--working');
        }

        await delay(850);

        if (updated) updated.textContent = formatTime();
        section.querySelectorAll('[data-retrieved-at]').forEach((element) => {
            element.textContent = `Retrieved ${formatTime()}`;
        });
        if (state) state.textContent = 'Mock refresh complete';
        if (dot) {
            dot.classList.remove('status-dot--working');
            dot.classList.add('status-dot--ready');
        }
        if (button) button.disabled = false;
        recordActivity('section_refresh', label);
        if (!quiet) showToast(`${label} mock batch is ready. Live retrieval begins in later stages.`);
    };

    document.querySelectorAll('[data-refresh-section]').forEach((button) => {
        button.addEventListener('click', () => {
            const section = button.closest('[data-section]');
            if (section) void updateSection(section);
        });
    });

    const refreshAll = document.querySelector('#refresh-all');
    if (refreshAll) {
        refreshAll.addEventListener('click', async () => {
            refreshAll.disabled = true;
            refreshAll.innerHTML = '<span aria-hidden="true">↻</span> Preparing all sections…';
            const sections = [...document.querySelectorAll('[data-section]')];
            await Promise.all(sections.map((section) => updateSection(section, true, 'manual_all')));
            refreshAll.disabled = false;
            refreshAll.innerHTML = '<span aria-hidden="true">↻</span> Refresh all';
            showToast('All live sections checked; demonstration sections updated locally.');
        });
    }

    const lastOpened = document.querySelector('#last-opened');
    if (lastOpened) {
        const previous = window.localStorage.getItem('personalBriefing.lastOpened');
        lastOpened.textContent = previous ? new Date(previous).toLocaleString() : 'First visit on this device';
        window.localStorage.setItem('personalBriefing.lastOpened', new Date().toISOString());
    }

    document.addEventListener('click', (event) => {
        const link = event.target.closest?.('[data-track-link]');
        if (!link) return;
        const section = link.closest('[data-section]')?.dataset.section || 'other';
        recordActivity('link_opened', section, link.href);
    });

    const renderActivityDashboard = () => {
        const dashboard = document.querySelector('[data-activity-dashboard]');
        if (!dashboard) return;
        if (dashboard.dataset.serverMetrics === 'true') return;

        const activity = readActivity();
        const sevenDaysAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
        const isRecent = (event) => new Date(event.at).getTime() >= sevenDaysAgo;
        const refreshes = activity.filter((event) => event.type === 'section_refresh');
        const links = activity.filter((event) => event.type === 'link_opened');
        const recentRefreshes = refreshes.filter(isRecent);
        const recentLinks = links.filter(isRecent);

        const setSummary = (name, value) => {
            const element = dashboard.querySelector(`[data-summary="${name}"]`);
            if (element) element.textContent = String(value);
        };

        setSummary('refresh-total', refreshes.length);
        setSummary('refresh-7d', recentRefreshes.length);
        setSummary('refresh-daily', (recentRefreshes.length / 7).toFixed(1));
        setSummary('links-total', links.length);
        setSummary('links-7d', recentLinks.length);

        dashboard.querySelectorAll('[data-analytics-section]').forEach((row) => {
            const section = row.dataset.analyticsSection;
            const sectionRefreshes = refreshes.filter((event) => event.section === section);
            const sectionRecentRefreshes = sectionRefreshes.filter(isRecent);
            const sectionLinks = links.filter((event) => event.section === section);
            const sectionRecentLinks = sectionLinks.filter(isRecent);
            const values = {
                'refresh-total': sectionRefreshes.length,
                'refresh-7d': sectionRecentRefreshes.length,
                'refresh-daily': (sectionRecentRefreshes.length / 7).toFixed(1),
                'links-total': sectionLinks.length,
                'links-7d': sectionRecentLinks.length,
            };

            Object.entries(values).forEach(([metric, value]) => {
                const element = row.querySelector(`[data-metric="${metric}"]`);
                if (element) element.textContent = String(value);
            });
        });
    };

    const backgroundState = document.querySelector('#background-state');
    if (backgroundState) {
        const financeSection = document.querySelector('[data-section="finance"]');
        const liveNewsSections = ['breaking', 'finance', 'crypto', 'ai'];
        void Promise.all([
            fetch('/api/finance.php?action=status').then((response) => response.json()),
            ...liveNewsSections.map((section) => fetch(`/api/news.php?action=status&section=${section}`).then((response) => response.json())),
        ]).then(async ([marketPayload, ...newsPayloads]) => {
            let checksNeeded = 0;
            if (marketPayload.ok && marketPayload.snapshot) {
                applyFinanceSnapshot(marketPayload.snapshot, financeSection);
            }
            const jobs = [];
            if (!marketPayload.ok || marketPayload.snapshot?.stale || !marketPayload.snapshot?.has_data) {
                checksNeeded += 1;
                jobs.push(refreshFinanceSection(financeSection, true, 'page_open'));
            }
            newsPayloads.forEach((payload, index) => {
                const sectionName = liveNewsSections[index];
                const section = document.querySelector(`[data-section="${sectionName}"]`);
                if (payload.ok && payload.snapshot) applyNewsSnapshot(payload.snapshot, section);
                if (!payload.ok || payload.snapshot?.stale || !(payload.snapshot?.archive_count > 0)) {
                    checksNeeded += 1;
                    jobs.push(refreshNewsSection(section, true, 'page_open'));
                }
            });

            if (checksNeeded === 0) {
                backgroundState.textContent = 'All live caches already ready';
                return;
            }
            backgroundState.textContent = `Preparing ${checksNeeded} live cache${checksNeeded === 1 ? '' : 's'}…`;
            const results = await Promise.all(jobs);
            const succeeded = results.filter(Boolean).length;
            backgroundState.textContent = succeeded === results.length ? 'All live caches ready' : `${succeeded}/${results.length} live checks succeeded`;
        }).catch(() => {
            backgroundState.textContent = 'Background checks unavailable';
        });
    }

    const checkSources = document.querySelector('#check-sources');
    if (checkSources) {
        checkSources.addEventListener('click', async () => {
            checkSources.disabled = true;
            checkSources.innerHTML = '<span aria-hidden="true">↻</span> Checking all live sources…';
            try {
                const requests = [
                    ['finance', '/api/finance.php?action=refresh'],
                    ...['breaking', 'finance', 'crypto', 'ai'].map((section) => [section, `/api/news.php?action=refresh&section=${section}`]),
                ];
                const results = await Promise.all(requests.map(async ([section, url]) => {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ trigger: 'manual_all' }),
                    });
                    const payload = await response.json();
                    if (response.ok && payload.ok) recordActivity('section_refresh', section);
                    return response.ok && payload.ok;
                }));
                const succeeded = results.filter(Boolean).length;
                showToast(`${succeeded}/${results.length} live source groups checked. Reloading status…`);
                window.setTimeout(() => window.location.reload(), 500);
            } catch (error) {
                showToast(error.message || 'Provider check failed.');
                checkSources.disabled = false;
                checkSources.innerHTML = '<span aria-hidden="true">↻</span> Refresh all live sources';
            }
        });
    }

    applyThemeControls();
    renderActivityDashboard();
})();
