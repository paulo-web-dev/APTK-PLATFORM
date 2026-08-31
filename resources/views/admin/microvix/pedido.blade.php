@extends('layouts.admin')

@section('admin_title', "Microvix — Pedido #{$order->id}")

@push('styles')
<style>
  .selo-envio { display:inline-block; font-family:var(--font-mono); font-size:10px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; padding:3px 8px; border-radius:var(--radius-sm); border:1.5px solid; white-space:nowrap; }
  .selo-envio--simulado { color:var(--color-warning, #c9a227); border-color:currentColor; background:color-mix(in srgb, currentColor 12%, transparent); }
  .selo-envio--real { color:var(--color-success, #3f9d63); border-color:currentColor; background:color-mix(in srgb, currentColor 12%, transparent); }
  .selo-envio--falha { color:var(--color-danger); border-color:currentColor; background:color-mix(in srgb, currentColor 12%, transparent); }

  .xml-item { border-bottom:1px solid var(--color-border); }
  .xml-item summary { display:flex; align-items:center; gap:12px; padding:12px 16px; cursor:pointer; list-style:none; flex-wrap:wrap; }
  .xml-item summary::-webkit-details-marker { display:none; }
  .xml-item summary:hover { background:var(--color-bg-card); }
  .xml-item .quando { color:var(--color-text-muted); font-size:var(--text-xs); font-family:var(--font-mono); }
  .xml-item pre { margin:0 16px 14px; padding:12px; background:var(--color-bg); border:1px solid var(--color-border); border-radius:var(--radius-sm); overflow-x:auto; font-size:11px; line-height:1.5; white-space:pre-wrap; word-break:break-all; }
  .xml-item .pre-label { margin:10px 16px 4px; font-family:var(--font-mono); font-size:10px; letter-spacing:.08em; text-transform:uppercase; color:var(--color-text-muted); }
</style>
@endpush

@section('content')
    <div class="content-head">
        <h1>Pedido #{{ $order->id }} → ERP</h1>
        <p>
            {{ $order->user?->name ?? 'cliente removido' }} · {{ $order->payment_method }} ·
            R$ {{ number_format((float) $order->total, 2, ',', '.') }} ·
            pagamento <strong>{{ $order->payment_status }}</strong> · pedido <strong>{{ $order->status }}</strong>
        </p>
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-ok" style="color:var(--color-danger); border-left-color:var(--color-danger);">{{ $errors->first() }}</div>
    @endif

    <div class="metric-grid" style="margin-bottom:22px;">
        <div class="metric-card">
            <div class="m-top"><span class="m-label">Estado do envio</span></div>
            <div class="m-value" style="font-size:1.1em; color:{{ $order->microvix_envio_estado === 'erro' ? 'var(--color-danger)' : 'inherit' }};">
                {{ $order->microvix_envio_estado ?? 'fora da integração' }}
            </div>
        </div>
        <div class="metric-card"><div class="m-top"><span class="m-label">Tentativas</span></div><div class="m-value">{{ $order->microvix_envio_tentativas }}</div></div>
        <div class="metric-card">
            <div class="m-top"><span class="m-label">Última tentativa</span></div>
            <div class="m-value" style="font-size:1.1em;">{{ $order->microvix_envio_em?->format('d/m H:i') ?? '—' }}</div>
        </div>
    </div>

    @if ($order->microvix_envio_erro)
        <div class="alert-ok" style="color:var(--color-danger); border-left-color:var(--color-danger);">
            <strong>Último erro:</strong> {{ $order->microvix_envio_erro }}
        </div>
    @endif

    <div class="filter-row" style="gap:10px;">
        <a href="{{ route('admin.microvix.index') }}" class="admin-btn admin-btn--ghost">← Painel Microvix</a>
        <form action="{{ route('admin.microvix.pedido.reenviar', $order) }}" method="POST" style="display:inline;">
            @csrf
            <button type="submit" class="admin-btn">Reenviar ao ERP</button>
        </form>
    </div>

    <div class="data-card">
        <div style="padding:14px 16px; font-family:var(--font-mono); font-size:.8em; letter-spacing:.06em; text-transform:uppercase; color:var(--color-text-muted); border-bottom:1px solid var(--color-border);">
            Trilha de envio ({{ $logs->count() }} chamada{{ $logs->count() === 1 ? '' : 's' }}, mais recente primeiro)
        </div>

        @forelse ($logs as $log)
            <details class="xml-item">
                <summary>
                    {{-- O selo é a primeira coisa que o olho pega: simulado × real. --}}
                    @if ($log->simulated)
                        <span class="selo-envio selo-envio--simulado">simulado — não enviado</span>
                    @elseif ($log->success)
                        <span class="selo-envio selo-envio--real">enviado ao ERP</span>
                    @else
                        <span class="selo-envio selo-envio--falha">falhou</span>
                    @endif
                    <strong>{{ $log->method }}</strong>
                    <span class="td-muted">{{ $log->direction === 'out' ? 'consulta' : 'importa' }}</span>
                    <span class="quando" style="margin-left:auto;">{{ $log->created_at->format('d/m/Y H:i:s') }} · HTTP {{ $log->http_status ?? '—' }} · {{ $log->duration_ms }} ms</span>
                </summary>

                @if ($log->error)
                    <p style="margin:0 16px 8px; color:var(--color-danger); font-size:var(--text-sm);">{{ $log->error }}</p>
                @endif

                <p class="pre-label">{{ $log->simulated ? 'XML que TERIA sido enviado' : 'XML enviado' }} (credenciais mascaradas)</p>
                <pre>{{ $log->request }}</pre>

                @if ($log->response)
                    <p class="pre-label">Resposta do ERP</p>
                    <pre>{{ $log->response }}</pre>
                @endif
            </details>
        @empty
            <p class="td-muted" style="text-align:center; padding:32px;">Nenhuma chamada registrada pra este pedido ainda.</p>
        @endforelse
    </div>
@endsection
