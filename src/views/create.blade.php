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

{{-- Resumable.js for chunked uploads --}}
<script src="https://cdn.jsdelivr.net/npm/resumablejs@1/resumable.min.js"></script>

{{-- Ensure Vue 3 (fallback if not already loaded) --}}
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
    const { createApp, reactive, computed, onMounted, ref, watch } = window.Vue || {};

    if (!createApp) return;

    createApp({
      setup() {
        const routes = reactive({
          store: @json(url('/wordpress-importer')),
          chunk: @json(route('wpimport.chunk.upload')),
          statusBaseLogs: @json(rtrim(route('sites.logs', 0), '/0')),
          sitesIndex: @json(route('sites.index')),
        });

        const form = reactive({
          url: @json(old('url', '')),
          source: 'upload',
          serverPath: @json(old('big_file_route', '')),
        });

        const ui = reactive({
          fileName: '',
          fileSizePretty: '',
          formErrors: [],
          hasFile: false,
        });

        const state = reactive({
          isSubmitting: false,
          hasProgress: false,
          progress: 0,
          usingChunks: false,
        });

        const fileInput = ref(null);

        const canSubmit = computed(() => {
          if (!form.url) return false;
          if (form.source === 'path') return !!form.serverPath?.trim();
          return ui.hasFile; // upload tab
        });

        watch(() => form.source, (val) => {
          ui.formErrors = [];
          if (val === 'upload') {
            form.serverPath = '';
          } else {
            if (fileInput.value) fileInput.value.value = '';
            ui.fileName = '';
            ui.fileSizePretty = '';
            ui.hasFile = false;
          }
        });

        function humanFileSize(bytes) {
          const thresh = 1024; if (Math.abs(bytes) < thresh) return bytes + ' B';
          const units = ['KB','MB','GB','TB']; let u = -1;
          do { bytes /= thresh; ++u; } while (Math.abs(bytes) >= thresh && u < units.length - 1);
          return bytes.toFixed(1) + ' ' + units[u];
        }
        function safeParseJSON(t){ try { return JSON.parse(t); } catch { return null; } }
        function flattenErrors(e){
          const out = [];
          Object.values(e||{}).forEach(v => Array.isArray(v) ? out.push(...v) : (typeof v === 'string' && out.push(v)));
          return out.length ? out : ['Validation failed.'];
        }
        function setTerminalStatus(id, kind){
          const s = document.querySelector(`[data-toast-id="${id}"] .terminal-status`);
          if (!s) return;
          s.classList.remove('terminal-status--success','terminal-status--error','terminal-status--warning');
          if (kind) s.classList.add(`terminal-status--${kind}`);
          s.textContent = (kind||'').toUpperCase();
        }
        function forceSameOrigin(u){
          try{
            const p = new URL(String(u||''), window.location.origin);
            return p.origin === window.location.origin ? p.href : (window.location.origin + p.pathname + p.search + p.hash);
          }catch{ return String(u||'/'); }
        }

        function onFileChange(e){
          const f = e.target.files?.[0] || null;
          ui.fileName = f ? f.name : '';
          ui.fileSizePretty = f ? humanFileSize(f.size) : '';
          ui.hasFile = !!f;
          if (f && f.size > (2*1024*1024*1024)) window.toast?.('Large file detected. Upload will resume if interrupted ✨','warning',5000);
        }

        onMounted(() => {
          setTimeout(() => document.getElementById('url-field')?.focus(), 150);
          initChunkUploader();
        });

        // ============ CHUNK UPLOADER ============
        let resumable = null;
        let resumableFile = null;
        let termIdUpload = null;

        function initChunkUploader(){
          if (!window.Resumable || !fileInput.value) return;
          const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

          resumable = new Resumable({
            target: routes.chunk,
            chunkSize: 8 * 1024 * 1024,
            simultaneousUploads: 1,     // ← prevent mkdir() races in Pion
            testChunks: false,          // ← our route doesn't implement the probe
            throttleProgressCallbacks: 1,
            maxChunkRetries: 5,
            permanentErrors: [400,401,403,404,409,413,415,422,500,501],
            headers: { 'X-CSRF-TOKEN': csrf },
            query: {}
            });


          resumable.assignBrowse(fileInput.value);

          resumable.on('fileAdded', (file) => {
            resumableFile = file;
            ui.fileName = file.fileName || file.relativePath || file.uniqueIdentifier;
            ui.fileSizePretty = humanFileSize(file.size);
            ui.hasFile = true;
          });

          resumable.on('fileProgress', (file) => {
            if (!state.usingChunks) return;
            state.progress = Math.round(file.progress() * 100);
          });

          resumable.on('fileError', (file, message) => {
            state.isSubmitting = false;
            state.hasProgress  = false;
            state.usingChunks  = false;
            state.progress     = 0;

            let msg = 'Upload failed.';
            try { const j = JSON.parse(message); if (j?.error) msg = j.error; } catch {}
            if (String(message).match(/\b413\b/)) msg = 'Upload rejected (HTTP 413). Check upstream body limits.';

            if (termIdUpload) {
              window.toastTerminalAppend?.(termIdUpload, `✖ ${msg}`);
              setTerminalStatus(termIdUpload, 'error');
            }
            window.toast?.(msg, 'error', 6000);
          });

          resumable.on('fileSuccess', (file, message) => {
            let data = {};
            try { data = JSON.parse(message || '{}'); } catch {}
            if (!data.path || !data.done) {
              if (termIdUpload) {
                window.toastTerminalAppend?.(termIdUpload, '✖ Server did not return the assembled file path.');
                setTerminalStatus(termIdUpload, 'error');
              }
              window.toast?.('Upload finished but file path missing. Try “Server path”.', 'error');
              state.isSubmitting = false; state.hasProgress = false; state.usingChunks = false; state.progress = 0;
              return;
            }

            form.source    = 'path';
            form.serverPath = data.path;

            if (termIdUpload) {
              window.toastTerminalAppend?.(termIdUpload, '→ Upload finished. Starting server-side import…');
              setTerminalStatus(termIdUpload, '');
            }

            enqueueImportWithServerPath(data.path, termIdUpload);
          });
        }

        // ============ ENQUEUE + POLL ============
        async function pollJob(statusUrlSafe, termId){
          try {
            let lastProgress = null, lastMessage = null, heartbeat = 0;
            for(;;){
              await new Promise(r => setTimeout(r, 700));
              const r = await fetch(statusUrlSafe, { method:'GET', credentials:'same-origin',
                headers:{ 'Accept':'application/json','X-Requested-With':'XMLHttpRequest' } });
              if (!r.ok) {
                heartbeat++; if (heartbeat % 5 === 0) window.toastTerminalAppend?.(termId,'…');
                if (r.status === 401 || r.status === 419) window.toastTerminalAppend?.(termId, `Auth check failed (HTTP ${r.status}).`);
                continue;
              }
              const st = await r.json().catch(() => ({}));
              const p = (typeof st.progress === 'number') ? st.progress : null;
              const m = st.message || '';

              if (p !== lastProgress && p != null) { window.toastTerminalAppend?.(termId, `Progress: ${p}%`); lastProgress = p; heartbeat = 0; }
              if (m && m !== lastMessage){ window.toastTerminalAppend?.(termId, m); lastMessage = m; heartbeat = 0; }
              if (!m && p == null){ heartbeat++; if (heartbeat % 5 === 0) window.toastTerminalAppend?.(termId,'…'); }

              if (st.status === 'succeeded') {
                window.toastTerminalAppend?.(termId, '✔ Import completed.');
                setTerminalStatus(termId, 'success');
                window.toast?.('Import completed.', 'success');
                if (st.site_id)      window.location.assign(`${routes.statusBaseLogs}/${st.site_id}`);
                else if (st.redirect) window.location.assign(String(st.redirect));
                else                  window.location.assign(routes.sitesIndex);
                return;
              }
              if (st.status === 'failed') {
                const msg = st.message || 'Import failed.';
                window.toastTerminalAppend?.(termId, `✖ ${msg}`);
                setTerminalStatus(termId, 'error');
                window.toast?.(msg, 'error');
                state.isSubmitting = false; state.hasProgress=false; state.usingChunks=false; state.progress=0;
                return;
              }
            }
          } catch {
            window.toastTerminalAppend?.(termId, '✖ Network error while polling import status.');
            setTerminalStatus(termId, 'error');
            window.toast?.('Network error. Please try again.', 'error');
            state.isSubmitting=false; state.hasProgress=false; state.usingChunks=false; state.progress=0;
          }
        }

        function enqueueImportWithServerPath(path, termId){
          const fd = new FormData();
          fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
          fd.append('url', form.url || '');
          fd.append('big_file_route', path);

          const xhr = new XMLHttpRequest();
          xhr.open('POST', routes.store, true);
          xhr.setRequestHeader('Accept', 'application/json');
          xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

          xhr.onreadystatechange = function(){
            if (xhr.readyState !== XMLHttpRequest.DONE) return;
            const status = xhr.status;
            const json = safeParseJSON(xhr.responseText || '') || {};
            if (status >= 200 && status < 300) {
              const jobId = json.job_id, statusUrl = json.status_url;
              if (!jobId || !statusUrl) {
                window.toastTerminalAppend?.(termId, '✖ Server did not return a status URL.');
                setTerminalStatus(termId, 'error');
                window.toast?.('Import enqueue failed. Please try again.','error');
                state.isSubmitting=false; state.hasProgress=false; state.usingChunks=false; state.progress=0;
                return;
              }
              window.toastTerminalAppend?.(termId, `▶ Import enqueued (job ${jobId})`);
              pollJob(forceSameOrigin(statusUrl), termId);
              return;
            }
            if (status === 422) {
              const errs = flattenErrors(json.errors); ui.formErrors = errs;
              window.toastTerminalAppend?.(termId, `✖ ${json.message || 'Validation failed.'}`);
              errs.forEach(m => window.toastTerminalAppend?.(termId, `• ${m}`));
              setTerminalStatus(termId, 'error');
            } else {
              window.toastTerminalAppend?.(termId, `✖ ${(json && json.message) || 'The import could not be started.'}`);
              setTerminalStatus(termId,'error'); window.toast?.('Import failed to start.','error');
            }
            state.isSubmitting=false; state.hasProgress=false; state.usingChunks=false; state.progress=0;
          };

          xhr.onerror = function(){
            window.toastTerminalAppend?.(termId, '✖ Network error while enqueuing the import.');
            setTerminalStatus(termId, 'error'); window.toast?.('Network error. Please try again.','error');
            state.isSubmitting=false; state.hasProgress=false; state.usingChunks=false; state.progress=0;
          };

          xhr.send(fd);
        }

        // ============ SUBMIT ============
        function onSubmit(){
          if (!canSubmit.value || state.isSubmitting) return;
          ui.formErrors = [];

          // Require a file on Upload tab
          if (form.source === 'upload' && !ui.hasFile) {
            ui.formErrors = ['Select a backup file or switch to “Server path”.'];
            window.toast?.('Select a backup file or switch to “Server path”.','warning',4000);
            return;
          }

          // Prefer CHUNKED flow when possible (don’t rely on DOM file list)
          if (form.source === 'upload' && ui.hasFile && resumable) {
            const nativeFile = fileInput.value?.files?.[0];
            if (!resumableFile && nativeFile) {
              try { resumable.addFile(nativeFile); } catch {}
            }
            state.isSubmitting = true;
            state.hasProgress  = true;
            state.usingChunks  = true;
            state.progress     = 0;

            const destLabel = form.url || '(new site)';
            termIdUpload = window.toastTerminal(
              `▶ Uploading ${ui.fileName || nativeFile?.name || 'backup'}`,
              { title:`Import • ${destLabel}`, delay:0, theme:'light', autoScroll:true }
            );
            window.toastTerminalAppend?.(termIdUpload, '→ Streaming in chunks (resume enabled)…');

            resumable.upload();
            return; // stop here; enqueueImportWithServerPath will run after chunk finish
          }

          // Fallback: DIRECT upload (explicitly append file)
          const formEl = document.getElementById('SiteForm');
          const fd = new FormData(formEl);

          if (form.source === 'upload') {
            const f = fileInput.value?.files?.[0] || null;
            if (!f) {
              ui.formErrors = ['Could not read the selected file. Try re-selecting it.'];
              window.toast?.('Could not read the selected file.','error');
              return;
            }
            fd.set('backup_file', f, f.name); // <- make sure the server gets the file
            fd.delete('big_file_route');       // <- avoid sending an empty string field
          }

          state.isSubmitting = true;
          state.hasProgress  = false;
          state.progress     = 0;

          const destLabel = form.url || '(new site)';
          const termId = window.toastTerminal(
            `▶ Starting import for ${destLabel}`,
            { title:`Import • ${destLabel}`, delay:0, theme:'light', autoScroll:true }
          );
          const tAppend = (line='') => window.toastTerminalAppend?.(termId, line);

          const xhr = new XMLHttpRequest();
          xhr.open('POST', routes.store, true);
          xhr.setRequestHeader('Accept','application/json');
          xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');

          tAppend('→ Enqueuing import…');

          xhr.onreadystatechange = function(){
            if (xhr.readyState !== XMLHttpRequest.DONE) return;
            const status = xhr.status;
            const json = safeParseJSON(xhr.responseText || '') || {};

            if (status >= 200 && status < 300) {
              const { job_id, status_url } = json;
              if (!job_id || !status_url) {
                tAppend('✖ Server did not return a status URL.');
                setTerminalStatus(termId,'error'); window.toast?.('Import enqueue failed.','error');
                state.isSubmitting=false; return;
              }
              tAppend(`▶ Import enqueued (job ${job_id})`);
              pollJob(forceSameOrigin(status_url), termId);
              return;
            }

            if (status === 413) {
              tAppend('✖ Upload rejected: file too large for server limits (HTTP 413).');
              ui.formErrors = [
                'The file exceeds the server’s current upload limit.',
                'Recommended: Use “Server path” or chunked upload.'
              ];
              setTerminalStatus(termId,'error'); window.toast?.('Upload too large.','error');
            } else if (status === 422) {
              const errs = flattenErrors(json.errors); ui.formErrors = errs;
              tAppend(`✖ ${json.message || 'Validation failed.'}`); errs.forEach(m => tAppend(`• ${m}`));
              setTerminalStatus(termId,'error');
            } else if (status === 401 || status === 403) {
              const msg = json.message || 'You are not allowed to import a site.';
              tAppend(`✖ ${msg}`); setTerminalStatus(termId,'error'); window.toast?.(msg,'error');
            } else {
              const msg = (json && json.message) || 'The import could not be started. Please try again.';
              tAppend(`✖ ${msg}`); setTerminalStatus(termId,'error'); window.toast?.(msg,'error');
            }

            state.isSubmitting=false; state.hasProgress=false; state.progress=0;
          };

          xhr.onerror = function(){
            tAppend('✖ Network error while enqueuing the import.');
            setTerminalStatus(termId,'error'); window.toast?.('Network error. Please try again.','error');
            state.isSubmitting=false; state.hasProgress=false; state.progress=0;
          };

          xhr.send(fd);
        }

        return { routes, form, ui, state, canSubmit, fileInput, onFileChange, onSubmit };
      }
    }).mount('#wpi-app');
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startApp);
  else startApp();
})();
</script>


@endpush

