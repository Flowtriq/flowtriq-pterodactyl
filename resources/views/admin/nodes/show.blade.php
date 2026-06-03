@extends('layouts.admin')

@section('title', 'Flowtriq - ' . $pteroNode->name)

@section('content-header')
    <h1>{{ $pteroNode->name }}<small>DDoS protection details</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.flowtriq.settings') }}">Flowtriq</a></li>
        <li><a href="{{ route('admin.flowtriq.nodes') }}">Nodes</a></li>
        <li class="active">{{ $pteroNode->name }}</li>
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

    {{-- Protection Status --}}
    <div class="row">
        <div class="col-md-4">
            <div class="box box-{{ $map ? ($map->isUnderAttack() ? 'danger' : ($map->isOnline() ? 'success' : 'default')) : 'warning' }}">
                <div class="box-header with-border">
                    <h3 class="box-title">Protection Status</h3>
                </div>
                <div class="box-body">
                    @if($map)
                        <div class="text-center" style="padding: 20px 0;">
                            <span class="label label-{{ $map->statusColor() }}" style="font-size: 18px; padding: 8px 20px;">
                                {{ strtoupper($map->status) }}
                            </span>
                        </div>
                        <table class="table table-condensed">
                            <tr>
                                <td>Flowtriq Node</td>
                                <td><code style="font-size: 11px;">{{ $map->flowtriq_node_uuid }}</code></td>
                            </tr>
                            <tr>
                                <td>Current PPS</td>
                                <td>{{ number_format($map->last_pps) }}</td>
                            </tr>
                            <tr>
                                <td>Current BPS</td>
                                <td>{{ number_format($map->last_bps) }}</td>
                            </tr>
                            <tr>
                                <td>Last Synced</td>
                                <td>{{ $map->last_synced_at ? $map->last_synced_at->diffForHumans() : 'Never' }}</td>
                            </tr>
                            <tr>
                                <td>Last Status Check</td>
                                <td>{{ $map->last_status_at ? $map->last_status_at->diffForHumans() : 'Never' }}</td>
                            </tr>
                        </table>

                        <div style="margin-top: 10px;">
                            <form method="POST" action="{{ route('admin.flowtriq.nodes.sync', $pteroNode->id) }}" style="display: inline;">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-info">
                                    <i class="fa fa-refresh"></i> Force Sync
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.flowtriq.nodes.unlink', $pteroNode->id) }}" style="display: inline; margin-left: 5px;"
                                  onsubmit="return confirm('Unlink this node from Flowtriq?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fa fa-chain-broken"></i> Unlink
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="text-center" style="padding: 20px 0;">
                            <span class="label label-default" style="font-size: 18px; padding: 8px 20px;">NOT LINKED</span>
                        </div>
                        <p class="text-muted text-center">This node is not connected to Flowtriq.</p>

                        <form method="POST" action="{{ route('admin.flowtriq.nodes.link', $pteroNode->id) }}">
                            @csrf
                            <div class="nav-tabs-custom" style="margin-top: 15px;">
                                <ul class="nav nav-tabs">
                                    <li class="active"><a href="#link-create" data-toggle="tab">Create New</a></li>
                                    <li><a href="#link-existing" data-toggle="tab">Link Existing</a></li>
                                </ul>
                                <div class="tab-content">
                                    <div class="tab-pane active" id="link-create">
                                        <input type="hidden" name="action" value="create" id="link-action">
                                        <p class="text-muted small">Creates a new Flowtriq node using this Wings node's FQDN ({{ $pteroNode->fqdn }}).</p>
                                        <button type="submit" class="btn btn-success" onclick="document.getElementById('link-action').value='create'">
                                            <i class="fa fa-plus"></i> Create & Link
                                        </button>
                                    </div>
                                    <div class="tab-pane" id="link-existing">
                                        <div class="form-group">
                                            <label>Flowtriq Node UUID</label>
                                            <input type="text" name="flowtriq_node_uuid" class="form-control"
                                                   placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                                        </div>
                                        <div class="form-group">
                                            <label>API Key</label>
                                            <input type="text" name="flowtriq_api_key" class="form-control"
                                                   placeholder="64-character node API key">
                                        </div>
                                        <button type="submit" class="btn btn-primary" onclick="document.getElementById('link-action').value='existing'">
                                            <i class="fa fa-link"></i> Link
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Service Ports --}}
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Service Ports ({{ $allocations->count() }})</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Port</th>
                                <th>IP</th>
                                <th>Server</th>
                                <th>Game</th>
                                <th>Protocol</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($allocations as $alloc)
                                @php
                                    $eggLower = strtolower($alloc->egg_name ?? '');
                                    $protocol = $defaultProtocol;
                                    foreach ($protocolMap as $pattern => $proto) {
                                        if (str_contains($eggLower, strtolower($pattern))) {
                                            $protocol = $proto;
                                            break;
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td><code>{{ $alloc->port }}</code></td>
                                    <td>{{ $alloc->ip }}</td>
                                    <td>{{ $alloc->server_name ?? '-' }}</td>
                                    <td>{{ $alloc->egg_name ?? 'Unknown' }}</td>
                                    <td>
                                        <span class="label label-{{ $protocol === 'tcp' ? 'primary' : ($protocol === 'udp' ? 'warning' : 'info') }}">
                                            {{ strtoupper($protocol) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No game servers on this node.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ftagent Install Instructions --}}
    @if($map)
        <div class="row">
            <div class="col-md-6">
                <div class="box box-default collapsed-box">
                    <div class="box-header with-border">
                        <h3 class="box-title">ftagent Install Instructions</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse">
                                <i class="fa fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="box-body">
                        <p class="text-muted small">Run these commands on the Wings host (<code>{{ $pteroNode->fqdn }}</code>) to install the Flowtriq agent:</p>
                        <pre style="background: #1a1a2e; color: #16c784; padding: 15px; border-radius: 4px;">pip install ftagent
sudo ftagent --setup \
  --node-uuid {{ $map->flowtriq_node_uuid }} \
  --api-key {{ $map->flowtriq_api_key }}
sudo ftagent --install-service</pre>
                        <p class="text-muted small" style="margin-top: 10px;">
                            Service ports are automatically synced from Pterodactyl. No manual port configuration needed.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Recent Incidents --}}
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Recent Incidents</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Attack Type</th>
                                <th>Severity</th>
                                <th>Peak PPS</th>
                                <th>Peak BPS</th>
                                <th>Status</th>
                                <th>Duration</th>
                                <th>Target Ports</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($incidents as $incident)
                                <tr class="{{ $incident->isActive() ? 'danger' : '' }}">
                                    <td>{{ $incident->started_at ? $incident->started_at->format('M j, H:i:s') : '-' }}</td>
                                    <td>{{ $incident->attack_family ?? 'Unknown' }}</td>
                                    <td>
                                        <span class="label label-{{ $incident->severity === 'critical' ? 'danger' : ($incident->severity === 'high' ? 'warning' : 'info') }}">
                                            {{ strtoupper($incident->severity ?? 'unknown') }}
                                        </span>
                                    </td>
                                    <td>{{ $incident->formattedPps() }}</td>
                                    <td>{{ $incident->formattedBps() }}</td>
                                    <td>
                                        <span class="label label-{{ $incident->isActive() ? 'danger' : 'success' }}">
                                            {{ strtoupper($incident->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $incident->duration() }}</td>
                                    <td>
                                        @if($incident->target_ports)
                                            {{ implode(', ', array_slice($incident->target_ports, 0, 5)) }}
                                            @if(count($incident->target_ports) > 5)
                                                <span class="text-muted">+{{ count($incident->target_ports) - 5 }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">All (IP-level)</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No incidents recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
