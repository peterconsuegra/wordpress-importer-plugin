@extends('layout')

@section('content')
<div class="container-fluid">

   

    {{-- form card --------------------------------------------------------- --}}
    <div class="row">
        <div class="col-lg-8 col-xl-6">
            <div class="panel">
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

                    {{-- form ------------------------------------------------ --}}
                    <form id="SiteForm"
                          action="{{ url('/import_wordpress/store') }}"
                          method="POST"
                          enctype="multipart/form-data"
                          autocomplete="off">
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
                                            value="{{ old('url') }}"
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
                                        value="{{ old('url') }}"
                                        required>
                                <div class="form-text">
                                    Full domain or subdomain that will host the imported site.
                                </div>
                            @endif
                        </div>

                        {{-- Backup source (Upload | Server path) ----------- --}}
                        <div class="mb-3">
                            <label class="form-label d-block">Backup file</label>

                            <ul class="nav nav-tabs" id="importTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-pane" type="button" role="tab" aria-controls="upload-pane" aria-selected="true">
                                        <i class="bi bi-upload me-1"></i>Upload
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="path-tab" data-bs-toggle="tab" data-bs-target="#path-pane" type="button" role="tab" aria-controls="path-pane" aria-selected="false">
                                        <i class="bi bi-folder2-open me-1"></i>Server path
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content border border-top-0 rounded-bottom p-3" id="importTabContent">
                                {{-- Upload option --}}
                                <div class="tab-pane fade show active" id="upload-pane" role="tabpanel" aria-labelledby="upload-tab" tabindex="0">
                                    <input  type="file"
                                            name="backup_file"
                                            class="form-control"
                                            accept=".zip,.tar,.gz,.tar.gz,.tgz,application/x-gzip,application/gzip">
                                    <div class="form-text">
                                        Leave empty if you’ll specify a server path instead.
                                    </div>
                                </div>

                                {{-- Server path option --}}
                                <div class="tab-pane fade" id="path-pane" role="tabpanel" aria-labelledby="path-tab" tabindex="0">
                                    <input  type="text"
                                            name="big_file_route"
                                            class="form-control"
                                            placeholder="/var/www/html/mysite.tar.gz"
                                            value="{{ old('big_file_route') }}">
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
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-cloud-arrow-down me-1"></i>Import
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

@push('scripts')
<script>
(function(){
    // Focus URL on load for faster input
    const urlField = document.getElementById('url-field');
    if (urlField) setTimeout(() => urlField.focus(), 150);
})();
</script>
@endpush
