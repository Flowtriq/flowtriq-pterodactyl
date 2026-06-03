@extends('layouts.master')

@section('title', 'DDoS Protection')

@section('content-header')
    <h1>DDoS Protection</h1>
@endsection

@section('content')
    {{-- Protection Status Banner --}}
    <div class="row">
        <div class="col-md-12">
            @if(!$map)
                <div class="callout callout-default">
                    <h4><i class="fa fa-shield"></i> Protection Status: Inactive</h4>
                    <p>DDoS protection is not configured for this server's node. Contact your hosting provider for assistance.</p>
                </div>
            @elseif($map->isUnderAttack())
                <div class="callout callout-danger">
                    <h4><i class="fa fa-exclamation-triangle"></i> Active Attack Detected</h4>
                    <p>An attack is currently being mitigated. Flowtriq is actively blocking malicious traffic while allowing legitimate game connections through.</p>
                </div>
            @elseif($map->status === 'elevated')
                <div class="callout callout-warning">
                    <h4><i class="fa fa-exclamation-circle"></i> Elevated Traffic</h4>
                    <p>Traffic levels are above normal. Flowtriq is monitoring closely and will auto-mitigate if an attack is detected.</p>
                </div>
            @elseif($map->isOnline())
                <div class="callout callout-success">
                    <h4><i class="fa fa-check-circle"></i> Protected</h4>
                    <p>Your server is actively protected by Flowtriq DDoS detection. Attacks are detected in under 1 second and mitigated automatically.</p>
                </div>
            @else
                <div class="callout callout-default">
                    <h4><i class="fa fa-question-circle"></i> Agent Offline</h4>
                    <p>The protection agent is not reporting. Contact your hosting provider if this persists.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Server Details --}}
    <div class="row">
        <div class="col-md-4">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Server Details</h3>
                </div>
                <div class="box-body">
                    <table class="table table-condensed no-border">
                        <tr>
                            <td><strong>IP Address</strong></td>
                            <td><code>{{ $primaryIp }}</code></td>
                        </tr>
                        <tr>
                            <td><strong>Protected Ports</strong></td>
                            <td>
                                @foreach($allocations as $alloc)
                                    <code>{{ $alloc->port }}</code>{{ !$loop->last ? ', ' : '' }}
                                @endforeach
                            </td>
                        </tr>
                        @if($map)
                            <tr>
                                <td><strong>Detection Speed</strong></td>
                                <td>&lt; 1 second</td>
                            </tr>
                            <tr>
                                <td><strong>Mitigation</strong></td>
                                <td>Automatic</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        @if($map)
            <div class="col-md-8">
                <div class="box box-solid">
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
                                    <th>Status</th>
                                    <th>Duration</th>
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
                                        <td>
                                            @if($incident->isActive())
                                                <span class="label label-danger">ACTIVE</span>
                                            @else
                                                <span class="label label-success">RESOLVED</span>
                                            @endif
                                        </td>
                                        <td>{{ $incident->duration() }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted" style="padding: 30px;">
                                            No attacks detected. Your server is running clean.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Auto-refresh status --}}
    @if($map)
        <div id="ddos-status-refresh" style="display:none;" data-url="{{ route('api.flowtriq.server.ddos', $server->uuidShort ?? $server->uuid) }}"></div>
        <script>
            (function() {
                var el = document.getElementById('ddos-status-refresh');
                if (!el) return;
                setInterval(function() {
                    fetch(el.dataset.url)
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.under_attack) {
                                document.title = '!! ATTACK - DDoS Protection';
                            }
                        })
                        .catch(function() {});
                }, 30000);
            })();
        </script>
    @endif
@endsection
