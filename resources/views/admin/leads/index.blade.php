@extends('layouts.admin')

@section('admin_title', 'Leads')

@php
    $labels = ['new' => 'Novo', 'contacted' => 'Contatado', 'converted' => 'Convertido', 'archived' => 'Arquivado'];
@endphp

@push('styles')
<style>
    .lead-status { background:var(--color-bg-elevated); border:1px solid var(--color-border); color:var(--color-text); border-radius:var(--radius-sm); padding:6px 10px; font-family:var(--font-body); font-size:var(--text-xs); }
    .lead-status:focus { outline:none; border-color:var(--color-primary-muted); }
    .lead-msg { color:var(--color-text-muted); font-size:var(--text-xs); max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
</style>
@endpush

@section('content')
    <div class="content-head">
        <h1>Leads</h1>
        <p>{{ $leads->total() }} {{ $leads->total() === 1 ? 'contato capturado' : 'contatos capturados' }}{{ $status ? ' · filtro aplicado' : '' }}</p>
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

    <div class="filter-row">
        <a href="{{ route('admin.leads.index') }}" class="filter-pill {{ ! $status ? 'is-active' : '' }}">Todos ({{ $counts['all'] }})</a>
        <a href="{{ route('admin.leads.index', ['status' => 'new']) }}" class="filter-pill {{ $status === 'new' ? 'is-active' : '' }}">Novos ({{ $counts['new'] }})</a>
        <a href="{{ route('admin.leads.index', ['status' => 'contacted']) }}" class="filter-pill {{ $status === 'contacted' ? 'is-active' : '' }}">Contatados ({{ $counts['contacted'] }})</a>
        <a href="{{ route('admin.leads.index', ['status' => 'converted']) }}" class="filter-pill {{ $status === 'converted' ? 'is-active' : '' }}">Convertidos ({{ $counts['converted'] }})</a>
        <a href="{{ route('admin.leads.index', ['status' => 'archived']) }}" class="filter-pill {{ $status === 'archived' ? 'is-active' : '' }}">Arquivados ({{ $counts['archived'] }})</a>
    </div>

    <div class="data-card">
        <table class="data-table">
            <thead>
                <tr><th>Contato</th><th>Tipo</th><th>Mensagem</th><th>Data</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($leads as $lead)
                    <tr>
                        <td data-label="Contato">
                            {{ $lead->name ?: '—' }}<br>
                            <a href="mailto:{{ $lead->email }}" class="td-muted" style="font-size:var(--text-xs);">{{ $lead->email }}</a>
                            @if ($lead->phone)<br><span class="td-muted" style="font-size:var(--text-xs);">{{ $lead->phone }}</span>@endif
                        </td>
                        <td class="td-muted" data-label="Tipo" style="text-transform:capitalize;">{{ $lead->type ?? '—' }}</td>
                        <td data-label="Mensagem"><div class="lead-msg" title="{{ $lead->message }}">{{ $lead->message ?: '—' }}</div></td>
                        <td class="td-muted" data-label="Data">{{ $lead->created_at->format('d/m/Y') }}</td>
                        <td data-label="Status">
                            <form method="POST" action="{{ route('admin.leads.update', $lead) }}">
                                @csrf @method('PATCH')
                                <select name="status" class="lead-status" onchange="this.form.submit()">
                                    @foreach ($labels as $val => $lbl)
                                        <option value="{{ $val }}" {{ ($lead->status ?? 'new') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td data-label="" style="text-align:right;">
                            <form action="{{ route('admin.leads.destroy', $lead) }}" method="POST" style="display:inline;" onsubmit="return confirm('Remover este lead?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="admin-btn admin-btn--ghost" style="padding:6px 12px; color:var(--color-danger);">Excluir</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="td-muted" style="text-align:center; padding:48px;">Nenhum lead {{ $status ? 'com esse status' : 'ainda' }}. Conforme os formulários da loja recebem contatos, eles aparecem aqui.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['paginator' => $leads])
@endsection
