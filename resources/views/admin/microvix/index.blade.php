@extends('layouts.admin')

@section('admin_title', 'Microvix — Painel')

@section('content')
    <div class="content-head">
        <h1>Microvix (ERP)</h1>
        <p>
            Fonte da verdade de preço/estoque:
            @if ($fonte === 'erp')
                <strong style="color:var(--color-primary);">ERP</strong> — a conciliação grava no catálogo e o admin trava preço/estoque de produto vinculado.
            @else
                <strong>site</strong> — conciliação roda só em dry-run; nada do ERP é gravado. A virada é <code>MICROVIX_FONTE_VERDADE=erp</code> no .env.
            @endif
        </p>
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert-ok" style="color:var(--color-danger); border-left-color:var(--color-danger);">{{ $errors->first() }}</div>
    @endif

    <div class="metric-grid" style="margin-bottom:22px;">
        <div class="metric-card"><div class="m-top"><span class="m-label">Sem vínculo</span></div><div class="m-value" style="color:{{ $stats['semVinculo'] > 0 ? 'var(--color-danger)' : 'inherit' }};">{{ $stats['semVinculo'] }}</div></div>
        <div class="metric-card"><div class="m-top"><span class="m-label">Vinculados</span></div><div class="m-value">{{ $stats['vinculados'] }}</div></div>
        <div class="metric-card"><div class="m-top"><span class="m-label">Registros do ERP</span></div><div class="m-value">{{ $stats['total'] }}</div></div>
    </div>

    <div class="filter-row" style="gap:10px; flex-wrap:wrap;">
        <a href="{{ route('admin.microvix.products.index') }}" class="admin-btn admin-btn--ghost">Tela de vínculos</a>
        <form action="{{ route('admin.microvix.sync') }}" method="POST" style="display:inline;">
            @csrf
            <button type="submit" class="admin-btn admin-btn--ghost">Sincronizar espelho agora</button>
        </form>
        <form action="{{ route('admin.microvix.conciliar') }}" method="POST" style="display:inline;">
            @csrf
            <button type="submit" class="admin-btn admin-btn--ghost">
                {{ $fonte === 'erp' ? 'Conciliar agora (grava)' : 'Conciliar (dry-run — nada grava)' }}
            </button>
        </form>
    </div>

    <div class="data-card" style="margin-bottom:22px;">
        <div style="padding:14px 16px; font-family:var(--font-mono); font-size:.8em; letter-spacing:.06em; text-transform:uppercase; color:var(--color-text-muted); border-bottom:1px solid var(--color-border);">Sincronização por método</div>
        <table class="data-table">
            <thead><tr><th>Método</th><th>Último timestamp</th><th>Última execução</th><th>Registros</th><th>Último erro</th></tr></thead>
            <tbody>
                @forelse ($syncStates as $s)
                    <tr>
                        <td data-label="Método">{{ $s->method }}</td>
                        <td class="td-num" data-label="Timestamp">{{ $s->last_timestamp }}</td>
                        <td class="td-muted" data-label="Execução">{{ $s->last_run_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
                        <td class="td-num" data-label="Registros">{{ $s->records_synced ?? '—' }}</td>
                        <td data-label="Erro">
                            @if ($s->last_error)
                                <span class="status status--cancelado" title="{{ $s->last_error }}">{{ \Illuminate\Support\Str::limit($s->last_error, 60) }}</span>
                            @else
                                <span class="td-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="td-muted" style="text-align:center; padding:32px;">Nenhum sync rodou ainda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="data-card" style="margin-bottom:22px;">
        <div style="padding:14px 16px; font-family:var(--font-mono); font-size:.8em; letter-spacing:.06em; text-transform:uppercase; color:var(--color-text-muted); border-bottom:1px solid var(--color-border);">Últimas conciliações (de → para)</div>
        <table class="data-table">
            <thead><tr><th>Quando</th><th>Produto</th><th>Volume</th><th>Campo</th><th>De</th><th>Para</th><th>Cód. ERP</th></tr></thead>
            <tbody>
                @forelse ($conciliacoes as $c)
                    <tr>
                        <td class="td-muted" data-label="Quando">{{ $c->created_at->format('d/m H:i') }}</td>
                        <td data-label="Produto">{{ $c->product?->name ?? "produto #{$c->product_id}" }}</td>
                        <td class="td-muted" data-label="Volume">{{ $c->size ?? '—' }}</td>
                        <td data-label="Campo">{{ $c->campo }}</td>
                        <td class="td-num" data-label="De">{{ $c->de !== null ? rtrim(rtrim(number_format((float) $c->de, 3, ',', '.'), '0'), ',') : '(sem valor)' }}</td>
                        <td class="td-num" data-label="Para">{{ rtrim(rtrim(number_format((float) $c->para, 3, ',', '.'), '0'), ',') }}</td>
                        <td class="td-muted td-num" data-label="Cód. ERP">{{ $c->codigoproduto }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="td-muted" style="text-align:center; padding:32px;">Nenhuma conciliação gravada ainda (dry-run não loga).</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="data-card">
        <div style="padding:14px 16px; display:flex; align-items:center; gap:12px; border-bottom:1px solid var(--color-border);">
            <span style="font-family:var(--font-mono); font-size:.8em; letter-spacing:.06em; text-transform:uppercase; color:var(--color-text-muted);">Últimas chamadas ao WS</span>
            <span style="margin-left:auto; display:inline-flex; gap:8px;">
                <a href="{{ route('admin.microvix.index') }}" class="filter-pill {{ ! $filtroLog ? 'is-active' : '' }}">Todas</a>
                <a href="{{ route('admin.microvix.index', ['log' => 'sucesso']) }}" class="filter-pill {{ $filtroLog === 'sucesso' ? 'is-active' : '' }}">Sucesso</a>
                <a href="{{ route('admin.microvix.index', ['log' => 'erro']) }}" class="filter-pill {{ $filtroLog === 'erro' ? 'is-active' : '' }}">Erro</a>
            </span>
        </div>
        <table class="data-table">
            <thead><tr><th>Quando</th><th>Dir.</th><th>Método</th><th>HTTP</th><th>ms</th><th>Resultado</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="td-muted" data-label="Quando">{{ $log->created_at->format('d/m H:i:s') }}</td>
                        <td class="td-muted" data-label="Dir.">{{ $log->direction === 'out' ? 'consulta' : 'importa' }}</td>
                        <td data-label="Método">{{ $log->method }}</td>
                        <td class="td-num td-muted" data-label="HTTP">{{ $log->http_status ?? '—' }}</td>
                        <td class="td-num td-muted" data-label="ms">{{ $log->duration_ms }}</td>
                        <td data-label="Resultado">
                            @if ($log->success)
                                <span class="status status--pago">ok</span>
                            @else
                                <span class="status status--cancelado" title="{{ $log->error }}">{{ \Illuminate\Support\Str::limit($log->error ?? 'erro', 60) }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="td-muted" style="text-align:center; padding:32px;">Nenhuma chamada {{ $filtroLog ? 'nesse filtro' : 'registrada' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
