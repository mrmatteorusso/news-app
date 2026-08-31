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

    const refreshFinanceSection = async (section, quiet = false, trigger = 'manual_section') => {
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

            recordActivity('section_refresh', 'finance');
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

    const updateSection = async (section, quiet = false, trigger = 'manual_section') => {
        if (section.dataset.section === 'finance') {
            return refreshFinanceSection(section, quiet, trigger);
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
            showToast('Finance data and all demonstration news sections refreshed.');
        });
    }

    const lastOpened = document.querySelector('#last-opened');
    if (lastOpened) {
        const previous = window.localStorage.getItem('personalBriefing.lastOpened');
        lastOpened.textContent = previous ? new Date(previous).toLocaleString() : 'First visit on this device';
        window.localStorage.setItem('personalBriefing.lastOpened', new Date().toISOString());
    }

    document.querySelectorAll('[data-track-link]').forEach((link) => {
        link.addEventListener('click', () => {
            const section = link.closest('[data-section]')?.dataset.section || 'other';
            recordActivity('link_opened', section, link.href);
        });
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
        void fetch('/api/finance.php?action=status')
            .then((response) => response.json())
            .then(async (payload) => {
                if (!payload.ok || !payload.snapshot) throw new Error('Finance status unavailable.');
                applyFinanceSnapshot(payload.snapshot, financeSection);
                if (payload.snapshot.stale || !payload.snapshot.has_data) {
                    backgroundState.textContent = 'Preparing live finance data…';
                    await refreshFinanceSection(financeSection, true, 'page_open');
                    backgroundState.textContent = 'Finance cache ready';
                } else {
                    backgroundState.textContent = 'Finance cache already ready';
                }
            })
            .catch(() => {
                backgroundState.textContent = 'Finance check unavailable';
            });
    }

    const checkSources = document.querySelector('#check-sources');
    if (checkSources) {
        checkSources.addEventListener('click', async () => {
            checkSources.disabled = true;
            checkSources.innerHTML = '<span aria-hidden="true">↻</span> Refreshing finance…';
            try {
                const response = await fetch('/api/finance.php?action=refresh', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ trigger: 'manual_section' }),
                });
                const payload = await response.json();
                if (!response.ok || !payload.ok) throw new Error(payload.error || 'Provider check failed.');
                recordActivity('section_refresh', 'finance');
                showToast('Finance providers checked. Reloading status…');
                window.setTimeout(() => window.location.reload(), 500);
            } catch (error) {
                showToast(error.message || 'Provider check failed.');
                checkSources.disabled = false;
                checkSources.innerHTML = '<span aria-hidden="true">↻</span> Refresh finance providers';
            }
        });
    }

    applyThemeControls();
    renderActivityDashboard();
})();
