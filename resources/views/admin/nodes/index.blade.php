@extends('layouts.admin')

@section('title', 'Flowtriq - Nodes')

@section('content-header')
    <h1>Flowtriq Nodes<small>DDoS protection status for your Wings nodes</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.flowtriq.settings') }}">Flowtriq</a></li>
        <li class="active">Nodes</li>
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

    {{-- Central Mode: Single Node Config --}}
    @if($mode === 'central')
        <div class="row">
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Central Flowtriq Node</h3>
                        <span class="label label-info pull-right">Central Mode</span>
                    </div>
                    <div class="box-body">
                        @if($centralMap)
                            <table class="table table-condensed">
                                <tr>
                                    <td><strong>Node UUID</strong></td>
                                    <td><code>{{ $centralMap->flowtriq_node_uuid }}</code></td>
                                </tr>
                                <tr>
                                    <td><strong>Status</strong></td>
                                    <td>
                                        <span class="label label-{{ $centralMap->statusColor() }}">
                                            {{ strtoupper($centralMap->status) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Current PPS</strong></td>
                                    <td>{{ number_format($centralMap->last_pps) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Last Synced</strong></td>
                                    <td>{{ $centralMap->last_synced_at ? $centralMap->last_synced_at->diffForHumans() : 'Never' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Service Ports</strong></td>
                                    <td>{{ array_sum($portCounts) }}</td>
                                </tr>
                            </table>

                            <div class="box-footer">
                                <form method="POST" action="{{ route('admin.flowtriq.nodes.central.link') }}" style="display: inline;">
                                    @csrf
                                    <input type="hidden" name="action" value="create">
                                </form>
                                <p class="text-muted small">
                                    All {{ count($pteroNodes) }} Wings nodes' game server ports are aggregated into this single Flowtriq node.
                                </p>
                            </div>
                        @else
                            <p>No central node configured. Link a Flowtriq node to start protecting all game servers.</p>

                            <form method="POST" action="{{ route('admin.flowtriq.nodes.central.link') }}">
                                @csrf
                                <div class="nav-tabs-custom">
                                    <ul class="nav nav-tabs">
                                        <li class="active"><a href="#central-create" data-toggle="tab">Create New</a></li>
                                        <li><a href="#central-existing" data-toggle="tab">Link Existing</a></li>
                                    </ul>
                                    <div class="tab-content">
                                        <div class="tab-pane active" id="central-create">
                                            <input type="hidden" name="action" value="create" id="central-action">
                                            <div class="form-group">
                                                <label>Panel Server IP</label>
                                                <input type="text" name="central_ip" class="form-control"
                                                       placeholder="e.g. 203.0.113.1">
                                            </div>
                                            <button type="submit" class="btn btn-success" onclick="document.getElementById('central-action').value='create'">
                                                <i class="fa fa-plus"></i> Create & Link
                                            </button>
                                        </div>
                                        <div class="tab-pane" id="central-existing">
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
                                            <button type="submit" class="btn btn-primary" onclick="document.getElementById('central-action').value='existing'">
                                                <i class="fa fa-link"></i> Link Existing
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Per-Wings Mode: Node List --}}
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Wings Nodes</h3>
                    @if($mode === 'per_wings')
                        <span class="label label-primary pull-right">Per-Wings Mode</span>
                    @endif
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Node</th>
                                <th>FQDN</th>
                                <th>Protection</th>
                                <th>PPS</th>
                                <th>Service Ports</th>
                                <th>Last Sync</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pteroNodes as $node)
                                @php
                                    $map = $mappings->get($node->id);
                                    $linked = $map !== null;
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.flowtriq.nodes.show', $node->id) }}">
                                            {{ $node->name }}
                                        </a>
                                    </td>
                                    <td><code>{{ $node->fqdn }}</code></td>
                                    <td>
                                        @if($mode === 'central' && $centralMap)
                                            <span class="label label-{{ $centralMap->statusColor() }}">
                                                {{ strtoupper($centralMap->status) }}
                                            </span>
                                        @elseif($linked)
                                            <span class="label label-{{ $map->statusColor() }}">
                                                {{ strtoupper($map->status) }}
                                            </span>
                                        @else
                                            <span class="label label-default">UNLINKED</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($linked)
                                            {{ number_format($map->last_pps) }}
                                        @elseif($mode === 'central' && $centralMap)
                                            -
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $portCounts[$node->id] ?? 0 }}</td>
                                    <td>
                                        @if($linked)
                                            {{ $map->last_synced_at ? $map->last_synced_at->diffForHumans() : 'Never' }}
                                        @elseif($mode === 'central' && $centralMap)
                                            {{ $centralMap->last_synced_at ? $centralMap->last_synced_at->diffForHumans() : 'Never' }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.flowtriq.nodes.show', $node->id) }}" class="btn btn-xs btn-default">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                        @if($mode === 'per_wings' && $linked)
                                            <form method="POST" action="{{ route('admin.flowtriq.nodes.sync', $node->id) }}" style="display: inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-info" title="Sync Ports">
                                                    <i class="fa fa-refresh"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No Wings nodes found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
