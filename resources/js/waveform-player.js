import WaveSurfer from 'wavesurfer.js';

/** @type {WeakMap<HTMLElement, import('wavesurfer.js').default>} */
const players = new WeakMap();

/** @type {WeakMap<HTMLElement, AbortController>} */
const controllers = new WeakMap();

function audioSourceKey(url) {
    if (!url) {
        return '';
    }

    try {
        const parsed = new URL(url, window.location.origin);

        return `${parsed.origin}${parsed.pathname}`;
    } catch {
        return url.split('?')[0] ?? url;
    }
}

function formatTime(seconds) {
    if (!Number.isFinite(seconds) || seconds < 0) {
        return '0:00';
    }

    const minutes = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);

    return `${minutes}:${secs.toString().padStart(2, '0')}`;
}

function themeColors() {
    const dark = document.documentElement.classList.contains('dark');

    return {
        waveColor: dark ? 'rgb(63, 63, 70)' : 'rgb(228, 228, 231)',
        progressColor: dark ? 'rgb(250, 250, 250)' : 'rgb(24, 24, 27)',
        cursorColor: dark ? 'rgb(250, 250, 250)' : 'rgb(24, 24, 27)',
    };
}

function setPlayState(root, playing) {
    const playIcon = root.querySelector('[data-waveform-icon-play]');
    const pauseIcon = root.querySelector('[data-waveform-icon-pause]');

    playIcon?.classList.toggle('hidden', playing);
    pauseIcon?.classList.toggle('hidden', !playing);
}

function setLoadingState(root, { loading = false, error = false, percent = 0 } = {}) {
    const loadingEl = root.querySelector('[data-waveform-loading]');
    const canvasWrap = root.querySelector('[data-waveform-canvas-wrap]');

    if (!loadingEl) {
        return;
    }

    if (error) {
        loadingEl.hidden = false;
        loadingEl.innerHTML = '<span class="text-sm text-red-600 dark:text-red-400">خطا در بارگذاری صوت</span>';
        canvasWrap?.classList.add('opacity-40');
        return;
    }

    if (loading) {
        loadingEl.hidden = false;
        loadingEl.innerHTML = `
            <div class="flex items-center gap-2 text-sm text-zinc-500">
                <span class="waveform-player__spinner" aria-hidden="true"></span>
                <span>در حال بارگذاری موج صوتی${percent > 0 ? ` (${Math.round(percent)}%)` : ''}…</span>
            </div>
        `;
        canvasWrap?.classList.add('opacity-40');
        return;
    }

    loadingEl.hidden = true;
    loadingEl.innerHTML = '';
    canvasWrap?.classList.remove('opacity-40');
}

function destroyPlayer(root) {
    controllers.get(root)?.abort();
    controllers.delete(root);

    const instance = players.get(root);

    if (instance) {
        instance.destroy();
        players.delete(root);
    }

    delete root.dataset.waveformReady;
    delete root.dataset.waveformSourceKey;
}

function initPlayer(root) {
    if (root.dataset.waveformReady === '1') {
        return;
    }

    const canvas = root.querySelector('[data-waveform-canvas]');
    const url = root.dataset.url;

    if (!canvas || !url) {
        return;
    }

    destroyPlayer(root);

    const currentTimeEl = root.querySelector('[data-waveform-current]');
    const durationEl = root.querySelector('[data-waveform-duration]');
    const playButton = root.querySelector('[data-waveform-play]');
    const colors = themeColors();

    setLoadingState(root, { loading: true });
    setPlayState(root, false);

    const wavesurfer = WaveSurfer.create({
        container: canvas,
        url,
        height: 72,
        barWidth: 2,
        barGap: 1,
        barRadius: 2,
        normalize: true,
        interact: true,
        dragToSeek: true,
        hideScrollbar: true,
        fillParent: true,
        sampleRate: 8000,
        waveColor: colors.waveColor,
        progressColor: colors.progressColor,
        cursorColor: colors.cursorColor,
        cursorWidth: 2,
        fetchParams: { credentials: 'same-origin' },
    });

    players.set(root, wavesurfer);
    root.dataset.waveformReady = '1';
    root.dataset.waveformSourceKey = audioSourceKey(url);

    const themeObserver = new MutationObserver(() => {
        const nextColors = themeColors();
        wavesurfer.setOptions({
            waveColor: nextColors.waveColor,
            progressColor: nextColors.progressColor,
            cursorColor: nextColors.cursorColor,
        });
    });

    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });

    wavesurfer.on('destroy', () => themeObserver.disconnect());

    wavesurfer.on('loading', (percent) => {
        setLoadingState(root, { loading: true, percent });
    });

    wavesurfer.on('ready', (duration) => {
        setLoadingState(root, { loading: false });
        if (durationEl) {
            durationEl.textContent = formatTime(duration);
        }
        if (currentTimeEl) {
            currentTimeEl.textContent = formatTime(wavesurfer.getCurrentTime());
        }
        playButton?.removeAttribute('disabled');
    });

    wavesurfer.on('timeupdate', (time) => {
        if (currentTimeEl) {
            currentTimeEl.textContent = formatTime(time);
        }
    });

    wavesurfer.on('play', () => setPlayState(root, true));
    wavesurfer.on('pause', () => setPlayState(root, false));
    wavesurfer.on('finish', () => setPlayState(root, false));

    wavesurfer.on('error', () => {
        setLoadingState(root, { error: true });
        playButton?.setAttribute('disabled', 'disabled');
    });

    const controller = new AbortController();
    controllers.set(root, controller);

    root.addEventListener('click', (event) => {
        if (!event.target.closest('[data-waveform-play]')) {
            return;
        }

        event.preventDefault();
        wavesurfer.playPause();
    }, { signal: controller.signal });

    playButton?.addEventListener('keydown', (event) => {
        if (event.code !== 'Space' && event.code !== 'Enter') {
            return;
        }

        event.preventDefault();
        wavesurfer.playPause();
    }, { signal: controller.signal });
}

export function initWaveformPlayers(scope = document) {
    scope.querySelectorAll('[data-waveform-player]').forEach(initPlayer);
}

export function destroyWaveformPlayers(scope = document) {
    const roots = scope.matches?.('[data-waveform-player]')
        ? [scope]
        : [...scope.querySelectorAll('[data-waveform-player]')];

    roots.forEach(destroyPlayer);
}

function boot() {
    initWaveformPlayers();

    document.addEventListener('livewire:navigated', () => {
        destroyWaveformPlayers();
        initWaveformPlayers();
    });

    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('morph.removed', ({ el }) => {
            destroyWaveformPlayers(el);
        });

        window.Livewire.hook('morph.updated', ({ el }) => {
            const roots = el.matches?.('[data-waveform-player]')
                ? [el]
                : [...el.querySelectorAll('[data-waveform-player]')];

            roots.forEach((root) => {
                if (root.closest('[wire\\:ignore]')) {
                    return;
                }

                const instance = players.get(root);
                const previousKey = root.dataset.waveformSourceKey ?? '';
                const nextKey = audioSourceKey(root.dataset.url ?? '');

                if (instance && root.dataset.waveformReady === '1' && previousKey !== '' && previousKey === nextKey) {
                    return;
                }

                destroyPlayer(root);
                initPlayer(root);
            });
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
