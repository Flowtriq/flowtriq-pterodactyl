@extends('layouts.admin')

@section('title', 'Flowtriq DDoS Protection')

@section('content-header')
    <h1>Flowtriq DDoS Protection<small>Configure your Flowtriq integration</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Flowtriq</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    {{ session('error') }}
                </div>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('admin.flowtriq.settings') }}">
        @csrf

        {{-- API Connection --}}
        <div class="row">
            <div class="col-md-8">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">API Connection</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label for="api_url">API URL</label>
                            <input type="text" name="api_url" id="api_url" class="form-control"
                                   value="{{ $settings['api_url'] ?? $defaults['api_url'] }}"
                                   placeholder="https://flowtriq.com">
                            <p class="text-muted small">Flowtriq API base URL. Leave default unless using white-label domain.</p>
                        </div>

                        <div class="form-group">
                            <label for="deploy_token">Deploy Token</label>
                            <input type="password" name="deploy_token" id="deploy_token" class="form-control"
                                   value="{{ $settings['deploy_token'] ?? $defaults['deploy_token'] }}"
                                   placeholder="Your 64-character deploy token">
                            <p class="text-muted small">Found in Flowtriq Dashboard > Settings > API Keys.</p>
                        </div>

                        <button type="button" id="btn-test" class="btn btn-sm btn-default">
                            <i class="fa fa-plug"></i> Test Connection
                        </button>
                        <span id="test-result" class="text-sm" style="margin-left: 10px;"></span>
                    </div>
                </div>
            </div>

            {{-- Deployment Mode --}}
            <div class="col-md-4">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Deployment Mode</h3>
                    </div>
                    <div class="box-body">
                        @php $mode = $settings['deployment_mode'] ?? $defaults['deployment_mode']; @endphp

                        <div class="radio">
                            <label>
                                <input type="radio" name="deployment_mode" value="central"
                                       {{ $mode === 'central' ? 'checked' : '' }}>
                                <strong>Central</strong>
                                <p class="text-muted small" style="margin-left: 20px;">
                                    One ftagent on the panel server. All game server ports aggregated into a single Flowtriq node.
                                    Best for single-machine setups.
                                </p>
                            </label>
                        </div>

                        <div class="radio">
                            <label>
                                <input type="radio" name="deployment_mode" value="per_wings"
                                       {{ $mode === 'per_wings' ? 'checked' : '' }}>
                                <strong>Per-Wings</strong>
                                <p class="text-muted small" style="margin-left: 20px;">
                                    One ftagent per Wings node. Each node gets its own Flowtriq node with independent protection.
                                    Supports white-label sub-workspaces.
                                </p>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Service Port Settings --}}
        <div class="row">
            <div class="col-md-6">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Service Port Detection</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label for="sp_sensitivity">Sensitivity</label>
                            <select name="sp_sensitivity" id="sp_sensitivity" class="form-control">
                                @php $sens = $settings['sp_sensitivity'] ?? $defaults['sp_sensitivity']; @endphp
                                <option value="relaxed" {{ $sens === 'relaxed' ? 'selected' : '' }}>Relaxed</option>
                                <option value="standard" {{ $sens === 'standard' ? 'selected' : '' }}>Standard</option>
                                <option value="aggressive" {{ $sens === 'aggressive' ? 'selected' : '' }}>Aggressive</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="sp_response_mode">Response Mode</label>
                            <select name="sp_response_mode" id="sp_response_mode" class="form-control">
                                @php $rm = $settings['sp_response_mode'] ?? $defaults['sp_response_mode']; @endphp
                                <option value="full" {{ $rm === 'full' ? 'selected' : '' }}>Full (on-node blocking)</option>
                                <option value="pipeline" {{ $rm === 'pipeline' ? 'selected' : '' }}>Pipeline (cloud-only)</option>
                                <option value="onnode" {{ $rm === 'onnode' ? 'selected' : '' }}>On-Node (hybrid)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xs-12">
                <button type="submit" class="btn btn-success">
                    <i class="fa fa-save"></i> Save Settings
                </button>
                <a href="{{ route('admin.flowtriq.nodes') }}" class="btn btn-default" style="margin-left: 10px;">
                    <i class="fa fa-server"></i> Manage Nodes
                </a>
            </div>
        </div>
    </form>
@endsection

@section('footer-scripts')
    @parent
    <script>
        document.getElementById('btn-test').addEventListener('click', function() {
            var btn = this;
            var result = document.getElementById('test-result');
            btn.disabled = true;
            result.textContent = 'Testing...';
            result.className = 'text-sm text-muted';

            fetch('{{ route("admin.flowtriq.settings.test") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                if (data.ok) {
                    result.textContent = data.message;
                    result.className = 'text-sm text-success';
                } else {
                    result.textContent = data.message;
                    result.className = 'text-sm text-danger';
                }
            })
            .catch(function() {
                btn.disabled = false;
                result.textContent = 'Connection failed';
                result.className = 'text-sm text-danger';
            });
        });
    </script>
@endsection
