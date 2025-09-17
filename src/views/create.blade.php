@extends('layout')

@section('content')
<div class="container-fluid" id="wpi-app" v-cloak>
    {{-- hero -------------------------------------------------------------- --}}
    <div class="row mb-3">
        <div class="col-12 col-xl-10">
            <div class="d-flex align-items-center gap-3">
                <div class="spinner-border" role="status" v-if="state.isSubmitting && !state.hasProgress">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h2 class="h4 mb-0">Import WordPress</h2>
                <span class="badge bg-primary-subtle text-primary fw-normal">WP Pete</span>
            </div>
        </div>
    </div>

    {{-- form card --------------------------------------------------------- --}}
    <div class="row">
        <div class="col-lg-8 col-xl-6">
            <div class="panel position-relative">
                {{-- overlay while submitting --}}
                <div v-if="state.isSubmitting"
                     class="position-absolute top-0 start-0 end-0 bottom-0 d-flex flex-column justify-content-center align-items-center bg-white bg-opacity-75 rounded-3"
                     style="z-index: 10; backdrop-filter: blur(2px);">
                    <div v-if="state.hasProgress" class="w-75">
                        <div class="mb-2 text-center">
                            <div class="fw-semibold">Uploading backup… @{{ state.progress }}%</div>
                            <div class="text-muted small">Don’t close this tab</div>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar"
                                 role="progressbar"
                                 :style="{ width: state.progress + '%' }"
                                 :aria-valuenow="state.progress"
                                 aria-valuemin="0"
                                 aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div v-else class="text-center">
                        <div class="spinner-border" role="status" aria-hidden="true"></div>
                        <div class="mt-3 fw-semibold">Processing…</div>
                        <div class="text-muted small">Starting the import on the server</div>
                    </div>
                </div>

                <div class="panel-heading">
                    <h3 class="mb-0 fs-5">Import settings</h3>
                </div>

                <div class="p-3 p-md-4">
                    {{-- flash / validation --}}
                    @if(session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <strong>Whoops!</strong> Please fix the following:
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div v-if="ui.formErrors.length" class="alert alert-danger">
                        <strong>Whoops!</strong> Please fix the following:
                        <ul class="mb-0">
                            <li v-for="(msg, i) in ui.formErrors" :key="i">@{{ msg }}</li>
                        </ul>
                    </div>

                    {{-- form ------------------------------------------------ --}}
                    <form id="SiteForm"
                          :action="routes.store"
                          method="POST"
                          enctype="multipart/form-data"
                          autocomplete="off"
                          @submit.prevent="onSubmit">
                        @csrf

                        {{-- Destination URL -------------------------------- --}}
                        <div class="mb-3">
                            <label for="url-field" class="form-label">Destination URL</label>

                            @php($template = $pete_options->get_meta_value('domain_template'))
                            @if($template && $template !== 'none')
                                <div class="input-group">
                                    <input  type="text"
                                            id="url-field"
                                            name="url"
                                            class="form-control"
                                            placeholder="subdomain"
                                            v-model.trim="form.url"
                                            :disabled="state.isSubmitting"
                                            required>
                                    <span class="input-group-text">.{{ $template }}</span>
                                </div>
                                <div class="form-text">
                                    Enter only the subdomain; Pete appends the template automatically.
                                </div>
                            @else
                                <input  type="text"
                                        id="url-field"
                                        name="url"
                                        class="form-control"
                                        placeholder="e.g. example.com"
                                        v-model.trim="form.url"
                                        :disabled="state.isSubmitting"
                                        required>
                                <div class="form-text">
                                    Full domain or subdomain that will host the imported site.
                                </div>
                            @endif
                        </div>

                        {{-- Backup source (Upload | Server path) ----------- --}}
                        <div class="mb-3">
                            <label class="form-label d-block">Backup source</label>

                            <ul class="nav nav-tabs" id="importTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link"
                                            :class="{active: form.source === 'upload'}"
                                            id="upload-tab"
                                            data-bs-toggle="tab"
                                            data-bs-target="#upload-pane"
                                            type="button"
                                            role="tab"
                                            aria-controls="upload-pane"
                                            :aria-selected="form.source === 'upload'"
                                            @click.prevent="form.source = 'upload'"
                                            :disabled="state.isSubmitting">
                                        <i class="bi bi-upload me-1"></i>Upload
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link"
                                            :class="{active: form.source === 'path'}"
                                            id="path-tab"
                                            data-bs-toggle="tab"
                                            data-bs-target="#path-pane"
                                            type="button"
                                            role="tab"
                                            aria-controls="path-pane"
                                            :aria-selected="form.source === 'path'"
                                            @click.prevent="form.source = 'path'"
                                            :disabled="state.isSubmitting">
                                        <i class="bi bi-folder2-open me-1"></i>Server path
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content border border-top-0 rounded-bottom p-3" id="importTabContent">
                                {{-- Upload option --}}
                                <div class="tab-pane fade"
                                     :class="{ 'show active': form.source === 'upload' }"
                                     id="upload-pane"
                                     role="tabpanel"
                                     aria-labelledby="upload-tab"
                                     tabindex="0">
                                    <input  ref="fileInput"
                                            type="file"
                                            name="backup_file"
                                            class="form-control"
                                            accept=".zip,.tar,.gz,.tar.gz,.tgz,application/x-gzip,application/gzip"
                                            @change="onFileChange"
                                            :disabled="state.isSubmitting || form.source !== 'upload'">
                                    <div class="form-text">
                                        Leave empty if you’ll specify a server path instead.
                                    </div>

                                    {{-- lightweight file preview --}}
                                    <div v-if="ui.fileName" class="mt-2 small text-muted">
                                        Selected: <span class="fw-semibold">@{{ ui.fileName }}</span>
                                        <span v-if="ui.fileSizePretty"> • @{{ ui.fileSizePretty }}</span>
                                    </div>
                                </div>

                                {{-- Server path option --}}
                                <div class="tab-pane fade"
                                     :class="{ 'show active': form.source === 'path' }"
                                     id="path-pane"
                                     role="tabpanel"
                                     aria-labelledby="path-tab"
                                     tabindex="0">
                                    <input  type="text"
                                            name="big_file_route"
                                            class="form-control"
                                            placeholder="/var/www/html/mysite.tar.gz"
                                            v-model.trim="form.serverPath"
                                            :disabled="state.isSubmitting || form.source !== 'path'">
                                    <div class="form-text">
                                        Full absolute path on the server (supports local filesystem or mounted volumes).
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Notes ------------------------------------------ --}}
                        <div class="mb-4">
                            <label class="form-label d-block">Upload size limits</label>
                            <ul class="mb-0 text-muted small">
                                <li>Production: 1&nbsp;GB &nbsp;/&nbsp; Development: 10&nbsp;GB</li>
                                <li>For larger archives, import from a server path or increase <code>upload_max_filesize</code> in <code>php_prod.ini</code> / <code>php_dev.ini</code>, then run: <code>docker compose build --no-cache php && docker compose up -d</code>.</li>
                            </ul>
                        </div>

                        {{-- Submit (secondary) — primary is in hero -------- --}}
                        <div class="d-grid">
                            <button id="import_wordpress" type="submit"
                                    class="btn btn-primary"
                                    :disabled="!canSubmit || state.isSubmitting"
                                    :class="{'btn-disabled': state.isSubmitting}">
                                <span v-if="!state.isSubmitting">
                                    <i class="bi bi-cloud-arrow-down me-1"></i>Import
                                </span>
                                <span v-else class="d-inline-flex align-items-center">
                                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                    Importing…
                                </span>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="panel-footer">
                    <span class="text-muted small">Need help? Check logs after import if anything fails.</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Hide Vue-bound content until hydrated */
    [v-cloak] { display: none; }
    /* Softer disabled button states (avoid harsh “error” look) */
    .btn.btn-disabled,
    .btn:disabled {
        opacity: .65 !important;
        cursor: not-allowed !important;
        box-shadow: none !important;
        filter: saturate(0.6);
    }
    .btn.btn-disabled:hover,
    .btn:disabled:hover,
    .btn.btn-disabled:focus,
    .btn:disabled:focus {
        opacity: .65 !important;
        box-shadow: none !important;
        transform: none !important;
    }
</style>
@endpush

@push('scripts')
{{-- Load Vue 3 from CDN if not already present (safe fallback) --}}
<script>(function(){
    if (!window.Vue) {
        var s = document.createElement('script');
        s.src = 'https://unpkg.com/vue@3/dist/vue.global.prod.js';
        s.defer = true;
        document.head.appendChild(s);
    }
})();</script>

<script>
(function bootstrapImporter(){
    const startApp = () => {
        const { createApp, reactive, computed, onMounted, ref } = window.Vue || {};

        // If Vue failed to load, fall back to minimal non-Vue behavior
        if (!createApp) {
            const form = document.getElementById('SiteForm');
            if (!form) return;

            form.addEventListener('submit', function(){
                document.body.style.cursor = 'wait';
            });
            return;
        }

        createApp({
            setup() {
                const routes = reactive({ store: @json(url('/wordpress-importer')) });

                const form = reactive({
                    url: @json(old('url', '')),
                    source: 'upload', // 'upload' | 'path'
                    serverPath: @json(old('big_file_route', '')),
                });

                const ui = reactive({
                    fileName: '',
                    fileSizePretty: '',
                    formErrors: [],
                });

                const state = reactive({
                    isSubmitting: false,
                    hasProgress: false,
                    progress: 0,
                });

                const fileInput = ref(null);

                const canSubmit = computed(() => {
                    if (!form.url) return false;
                    if (form.source === 'path') return !!form.serverPath;
                    // upload can be submitted without pre-selecting a file (server will validate XOR)
                    return true;
                });

                function onFileChange(e) {
                    const f = e.target.files && e.target.files[0] ? e.target.files[0] : null;
                    ui.fileName = f ? f.name : '';
                    ui.fileSizePretty = f ? humanFileSize(f.size) : '';
                }

                function humanFileSize(bytes) {
                    const thresh = 1024; if (Math.abs(bytes) < thresh) return bytes + ' B';
                    const units = ['KB','MB','GB','TB','PB','EB','ZB','YB']; let u = -1;
                    do { bytes /= thresh; ++u; } while (Math.abs(bytes) >= thresh && u < units.length - 1);
                    return bytes.toFixed(1) + ' ' + units[u];
                }

                // --- helpers ---------------------------------------------------
                function safeParseJSON(text) { try { return JSON.parse(text); } catch { return null; } }
                function flattenErrors(errObj) {
                    const out = [];
                    Object.values(errObj || {}).forEach(v => {
                        if (Array.isArray(v)) out.push(...v);
                        else if (typeof v === 'string') out.push(v);
                    });
                    return out.length ? out : ['Validation failed.'];
                }

                function setTerminalStatus(termId, kind /* success|error|warning|'' */) {
                    const statusEl = document.querySelector(`[data-toast-id="${termId}"] .terminal-status`);
                    if (statusEl) {
                        statusEl.classList.remove('terminal-status--success','terminal-status--error','terminal-status--warning');
                        if (kind) statusEl.classList.add(`terminal-status--${kind}`);
                        statusEl.textContent = (kind || '').toUpperCase();
                    }
                }

                // 🔒 Force a URL to same-origin (important for auth cookies)
                function forceSameOrigin(u) {
                    try {
                        const parsed = new URL(String(u || ''), window.location.origin);
                        if (parsed.origin === window.location.origin) return parsed.href;
                        return window.location.origin + parsed.pathname + parsed.search + parsed.hash;
                    } catch {
                        // If URL constructor fails, fallback to path-ish string
                        return String(u || '/');
                    }
                }

                onMounted(() => {
                    const urlField = document.getElementById('url-field');
                    if (urlField) setTimeout(() => urlField.focus(), 150);
                });

                function onSubmit() {
                    if (!canSubmit.value || state.isSubmitting) return;

                    ui.formErrors = [];
                    const formEl = document.getElementById('SiteForm');
                    const fd = new FormData(formEl);

                    const file = fileInput.value && fileInput.value.files ? fileInput.value.files[0] : null;
                    state.isSubmitting = true;
                    state.hasProgress  = !!(file && form.source === 'upload');
                    state.progress     = 0;

                    // Terminal toast — like submitClone()
                    const destLabel = form.url || '(new site)';
                    const termId = window.toastTerminal(
                        `▶ Starting import for ${destLabel}`,
                        { title: `Import • ${destLabel}`, delay: 0, theme: 'light', autoScroll: true }
                    );
                    const tAppend = (line='') => window.toastTerminalAppend?.(termId, line);

                    // Use XHR for upload progress (then switch to fetch for polling)
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', routes.store, true);
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                    if (state.hasProgress && xhr.upload) {
                        xhr.upload.onprogress = (e) => {
                            if (!e.lengthComputable) return;
                            const pct = Math.round((e.loaded / e.total) * 100);
                            state.progress = Math.min(100, Math.max(0, pct));
                        };
                        tAppend('→ Uploading backup…');
                    } else {
                        tAppend('→ Enqueuing import…');
                    }

                    xhr.onreadystatechange = async function() {
                        if (xhr.readyState !== XMLHttpRequest.DONE) return;

                        const status = xhr.status;
                        const respText = xhr.responseText || '';
                        const json = safeParseJSON(respText) || {};

                        // Success (202 Accepted from controller)
                        if (status >= 200 && status < 300) {
                            const jobId = json.job_id;
                            const statusUrl = json.status_url;

                            if (!jobId || !statusUrl) {
                                // Unexpected: we didn't get polling info
                                tAppend('✖ Server did not return a status URL.');
                                setTerminalStatus(termId, 'error');
                                window.toast?.('Import enqueue failed. Please try again.', 'error');
                                state.isSubmitting = false;
                                state.hasProgress = false;
                                state.progress = 0;
                                return;
                            }

                            // 🔒 Enforce same-origin URL for polling
                            const statusUrlSafe = forceSameOrigin(statusUrl);

                            // Begin polling (like clone)
                            tAppend(`▶ Import enqueued (job ${jobId})`);
                            setTerminalStatus(termId, ''); // clear

                            try {
                                let lastProgress = null;
                                let lastMessage  = null;
                                let heartbeat    = 0;

                                while (true) {
                                    await new Promise(r => setTimeout(r, 700));

                                    const r = await fetch(statusUrlSafe, {
                                        method: 'GET',
                                        credentials: 'same-origin',
                                        headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }
                                    });

                                    if (!r.ok) {
                                        heartbeat++;
                                        if (heartbeat % 5 === 0) tAppend('…'); // heartbeat every ~3.5s
                                        // 401/419 could happen if session expired; bubble visually but keep trying a bit
                                        if (r.status === 401 || r.status === 419) {
                                            tAppend(`Auth check failed (HTTP ${r.status}). Make sure you are logged in on this host.`);
                                        }
                                        continue;
                                    }

                                    const st = await r.json().catch(() => ({}));
                                    const p  = (typeof st.progress === 'number') ? st.progress : null;
                                    const m  = st.message || '';

                                    if (p !== lastProgress && p != null) {
                                        tAppend(`Progress: ${p}%`);
                                        lastProgress = p;
                                        heartbeat = 0;
                                    }
                                    if (m && m !== lastMessage) {
                                        tAppend(m);
                                        lastMessage = m;
                                        heartbeat = 0;
                                    }
                                    if (!m && p == null) {
                                        heartbeat++;
                                        if (heartbeat % 5 === 0) tAppend('…');
                                    }

                                    if (st.status === 'succeeded') {
                                        tAppend('✔ Import completed.');
                                        setTerminalStatus(termId, 'success');
                                        window.toast?.('Import completed.', 'success');

                                        // If the runner provides dest_site_id, go to its logs
                                        if (st.dest_site_id) {
                                            const logsBase = @json(rtrim(route('sites.logs', 0), '/0'));
                                            window.location.assign(`${logsBase}/${st.dest_site_id}`);
                                        } else if (st.redirect) {
                                            window.location.assign(String(st.redirect));
                                        } else {
                                            // Fallback: go to sites index or generic logs
                                            window.location.assign(@json(route('sites.index')));
                                        }
                                        return;
                                    }

                                    if (st.status === 'failed') {
                                        const msg = st.message || 'Import failed.';
                                        tAppend(`✖ ${msg}`);
                                        setTerminalStatus(termId, 'error');
                                        window.toast?.(msg, 'error');
                                        state.isSubmitting = false;
                                        state.hasProgress = false;
                                        state.progress = 0;
                                        return;
                                    }
                                }
                            } catch (pollErr) {
                                tAppend('✖ Network error while polling import status.');
                                setTerminalStatus(termId, 'error');
                                window.toast?.('Network error. Please try again.', 'error');
                                state.isSubmitting = false;
                                state.hasProgress = false;
                                state.progress = 0;
                            }

                            return; // ensure we don’t fall through
                        }

                        // Validation error → show inline list and terminal toast
                        if (status === 422) {
                            const errs = flattenErrors(json.errors);
                            ui.formErrors = errs;
                            tAppend(`✖ ${json.message || 'Validation failed.'}`);
                            errs.forEach(msg => tAppend(`• ${msg}`));
                            setTerminalStatus(termId, 'error');

                            state.isSubmitting = false;
                            state.hasProgress  = false;
                            state.progress     = 0;
                            return;
                        }

                        // Unauthorized/Forbidden
                        if (status === 401 || status === 403) {
                            const msg = json.message || 'You are not allowed to import a site.';
                            tAppend(`✖ ${msg}`);
                            setTerminalStatus(termId, 'error');
                            window.toast?.(msg, 'error');

                            state.isSubmitting = false;
                            state.hasProgress  = false;
                            state.progress     = 0;
                            return;
                        }

                        // Other server error
                        const fallbackMsg = (json && json.message) || 'The import could not be started. Please try again.';
                        tAppend(`✖ ${fallbackMsg}`);
                        setTerminalStatus(termId, 'error');
                        window.toast?.(fallbackMsg, 'error');

                        state.isSubmitting = false;
                        state.hasProgress  = false;
                        state.progress     = 0;
                    };

                    xhr.onerror = function() {
                        tAppend('✖ Network error while enqueuing the import.');
                        setTerminalStatus(termId, 'error');
                        window.toast?.('Network error. Please try again.', 'error');
                        state.isSubmitting = false;
                        state.hasProgress  = false;
                        state.progress     = 0;
                    };

                    xhr.send(fd);
                }

                return {
                    routes, form, ui, state, canSubmit,
                    fileInput, onFileChange, onSubmit,
                };
            }
        }).mount('#wpi-app');

    };

    // If Vue was injected via CDN with defer, wait until it's parsed
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startApp);
    } else {
        startApp();
    }
})();
</script>
@endpush

