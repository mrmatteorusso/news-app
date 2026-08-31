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

    const recordActivity = (type, section) => {
        const activity = readActivity();
        activity.push({ type, section, at: new Date().toISOString() });
        try {
            window.localStorage.setItem(ACTIVITY_KEY, JSON.stringify(activity.slice(-5000)));
        } catch (error) {}
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

    const updateSection = async (section, quiet = false) => {
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
            await Promise.all(sections.map((section) => updateSection(section, true)));
            refreshAll.disabled = false;
            refreshAll.innerHTML = '<span aria-hidden="true">↻</span> Refresh all';
            showToast('All mock sections refreshed successfully.');
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
            recordActivity('link_opened', section);
        });
    });

    const renderActivityDashboard = () => {
        const dashboard = document.querySelector('[data-activity-dashboard]');
        if (!dashboard) return;

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
        window.setTimeout(() => {
            backgroundState.textContent = 'Mock candidates ready';
        }, 1100);
    }

    const checkSources = document.querySelector('#check-sources');
    if (checkSources) {
        checkSources.addEventListener('click', async () => {
            checkSources.disabled = true;
            checkSources.innerHTML = '<span aria-hidden="true">↻</span> Checking…';
            await delay(1000);
            const checkTime = document.querySelector('[data-source-check-time]');
            if (checkTime) checkTime.textContent = formatTime();
            checkSources.disabled = false;
            checkSources.innerHTML = '<span aria-hidden="true">↻</span> Check all sources';
            showToast('Mock source check complete. Existing example warnings were retained.');
        });
    }

    applyThemeControls();
    renderActivityDashboard();
})();
