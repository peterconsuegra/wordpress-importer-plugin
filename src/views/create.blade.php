@extends('layout')

@section('content')
<div class="container-fluid">
    {{-- hero ------------------------------------------------------------- --}}
    <div class="row align-items-center mb-5">
        <div class="col-md-6 text-center text-md-left mb-4 mb-md-0">
            <img src="/pete.png" alt="WordPress Pete" class="img-responsive" style="max-height:200px">
        </div>
        <div class="col-md-6 d-flex flex-column justify-content-center">
            <h2 class="mb-1">Import an existing WordPress site</h2>
            <p class="text-muted mb-0">Import WordPress sites in one click — speed up migrations, simplify setup, and get to work instantly with a clean, ready-to-use environment.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 col-xl-6">
            {{-- flash / validation ----------------------------------------- --}}
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

            {{-- form -------------------------------------------------------- --}}
            <form id="SiteForm"
                  action="{{ url('/import_wordpress/store') }}"
                  method="POST"
                  enctype="multipart/form-data"
                  autocomplete="off">
                @csrf

                {{-- URL ---------------------------------------------------- --}}
                <div class="form-group">
                    <label for="url-field" class="control-label">Destination URL</label>

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
                            <span class="input-group-addon">.{{ $template }}</span>
                        </div>
                        <small class="help-block text-muted">
                            Enter only the sub-domain; Pete appends the template automatically.
                        </small>
                    @else
                        <input  type="text"
                                id="url-field"
                                name="url"
                                class="form-control"
                                placeholder="e.g. example.com"
                                value="{{ old('url') }}"
                                required>
                    @endif
                </div>

                {{-- Backup source (upload | server path) ------------------- --}}
                <div class="form-group">
                    <label class="control-label d-block">Backup file</label>

                    <ul class="nav nav-tabs" role="tablist">
                        <li role="presentation" class="active">
                            <a href="#upload" aria-controls="upload" role="tab" data-toggle="tab">Upload</a>
                        </li>
                        <li role="presentation">
                            <a href="#path" aria-controls="path" role="tab" data-toggle="tab">Server path</a>
                        </li>
                    </ul>

                    <div class="tab-content pt-3">
                        {{-- Upload option --}}
                        <div role="tabpanel" class="tab-pane active" id="upload">
                            <input  type="file"
							name="backup_file"
							class="form-control"
							accept=".zip,.tar,.gz,.tar.gz,.tgz,application/x-gzip,application/gzip">
                            <small class="help-block text-muted">
                                Max&nbsp;1 GB. Leave empty if you’ll specify a server path instead.
                            </small>
                        </div>

                        {{-- Path option --}}
                        <div role="tabpanel" class="tab-pane" id="path">
                            <input  type="text"
                                    name="big_file_route"
                                    class="form-control"
                                    placeholder="/var/www/html/mysite.tar.gz"
                                    value="{{ old('big_file_route') }}">
                            <small class="help-block text-muted">
                                Full absolute path on the server.
                            </small>
                        </div>
                    </div>
                </div>

                <button type="submit" id="create_button" class="btn btn-primary btn-lg">
                    <span class="glyphicon glyphicon-import"></span> Import
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
