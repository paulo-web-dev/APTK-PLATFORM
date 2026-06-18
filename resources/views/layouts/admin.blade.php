<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('admin_title', 'Painel') · APTK Admin</title>
  {{-- Tipografia oficial APTK (PP Rader, auto-hospedada em aptk-tokens.css) --}}
  <link rel="preload" href="{{ asset('fonts/PPRader-Bold.woff2') }}" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="{{ asset('fonts/PPRader-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>

  {{-- Fonte única de tokens (paleta + tipografia + escala + tema). O admin usa o tema escuro da marca via data-theme="dark" no <html>. --}}
  <link href="{{ asset('css/aptk-tokens.css') }}" rel="stylesheet">
  <style>
  /* ===================================================================
     APTK ADMIN — IDENTIDADE
     BLOCO A (tokens :root + base) → já existe no aptk.css, não duplicar
     BLOCO B (admin)               → vira o CSS do layout admin
     =================================================================== */

  /* ---------- TOKENS — agora vêm de aptk-tokens.css (fonte única de cor,
     tipografia e escala). O admin herda a paleta da marca e usa o tema escuro
     (Cuba Libre + Scotch) via data-theme="dark" no <html>. ---------- */

  *, *::before, *::after { box-sizing: border-box; }
  body {
    margin: 0;
    background: var(--color-bg);
    color: var(--color-text);
    font-family: var(--font-body);
    font-size: var(--text-base);
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
  }
  a { color: inherit; text-decoration: none; }
  ul { margin: 0; padding: 0; list-style: none; }
  button { font-family: inherit; cursor: pointer; }
  :focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px; border-radius: var(--radius-sm); }

  /* ===================================================================
     BLOCO B — PAINEL ADMIN
     =================================================================== */

  /* ---------- Sidebar ---------- */
  .admin-sidebar {
    position: fixed;
    top: 0; left: 0;
    width: var(--sidebar-w);
    height: 100vh;
    background: var(--color-bg-elevated);
    border-right: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    z-index: 50;
    transition: transform .25s ease;
  }
  .admin-brand {
    height: var(--topbar-h);
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 24px;
    border-bottom: 1px solid var(--color-border);
    font-family: var(--font-display);
    font-weight: 700;
    font-size: var(--text-xl);
    letter-spacing: 0.18em;
    color: var(--color-text);
    flex-shrink: 0;
  }
  .admin-brand .brand-mark { width: 10px; height: 10px; background: var(--color-primary); border-radius: 2px; transform: rotate(45deg); }
  .admin-brand small {
    font-family: var(--font-mono); font-size: 9px; letter-spacing: 0.18em;
    color: var(--color-text-muted); font-weight: 400; margin-left: 2px; align-self: flex-end; margin-bottom: 22px;
  }

  .admin-nav { padding: 16px 12px; flex: 1; overflow-y: auto; }
  .admin-nav .nav-label {
    font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.16em;
    text-transform: uppercase; color: var(--color-text-muted);
    padding: 12px 12px 8px;
  }
  .admin-nav a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 12px;
    border-radius: var(--radius-md);
    color: var(--color-text-muted);
    font-size: var(--text-sm);
    font-weight: 500;
    margin-bottom: 2px;
    position: relative;
    transition: color .18s ease, background-color .18s ease;
  }
  .admin-nav a svg { width: 18px; height: 18px; flex-shrink: 0; }
  .admin-nav a:hover { color: var(--color-text); background: var(--color-bg-card); }
  .admin-nav a.is-active {
    color: var(--color-primary);
    background: var(--gold-faint);
  }
  .admin-nav a.is-active::before {
    content: "";
    position: absolute; left: 0; top: 8px; bottom: 8px;
    width: 3px; border-radius: 0 2px 2px 0;
    background: var(--color-primary);
  }

  .admin-sidebar-foot {
    padding: 16px;
    border-top: 1px solid var(--color-border);
    font-size: var(--text-xs);
    color: var(--color-text-muted);
  }

  /* ---------- Main ---------- */
  .admin-main { margin-left: var(--sidebar-w); min-height: 100vh; }

  .admin-topbar {
    position: sticky; top: 0; z-index: 40;
    height: var(--topbar-h);
    background: color-mix(in srgb, var(--color-bg) 90%, transparent);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid var(--color-border);
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 0 32px;
  }
  .topbar-title { font-size: var(--text-xl); font-weight: 600; color: var(--color-text); }
  .topbar-spacer { flex: 1; }
  .topbar-search {
    display: flex; align-items: center; gap: 8px;
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: 8px 12px;
    width: 260px;
  }
  .topbar-search svg { width: 16px; height: 16px; color: var(--color-text-muted); flex-shrink: 0; }
  .topbar-search input {
    background: transparent; border: none; outline: none;
    color: var(--color-text); font-family: var(--font-body); font-size: var(--text-sm); width: 100%;
  }
  .topbar-search input::placeholder { color: var(--color-text-muted); }
  .icon-btn {
    background: transparent; border: none; color: var(--color-text-muted);
    display: grid; place-items: center; width: 38px; height: 38px;
    border-radius: var(--radius-md); position: relative;
    transition: color .18s ease, background-color .18s ease;
  }
  .icon-btn:hover { color: var(--color-primary); background: var(--gold-faint); }
  .icon-btn svg { width: 19px; height: 19px; }
  .icon-btn .dot { position: absolute; top: 8px; right: 9px; width: 7px; height: 7px; border-radius: 50%; background: var(--color-primary); border: 2px solid var(--color-bg); }

  .topbar-user { display: flex; align-items: center; gap: 10px; padding-left: 6px; }
  .avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--gold-faint); border: 1px solid var(--color-primary-muted);
    color: var(--color-primary); display: grid; place-items: center;
    font-family: var(--font-mono); font-size: var(--text-sm); font-weight: 600;
  }
  .topbar-user .who { display: flex; flex-direction: column; line-height: 1.25; }
  .topbar-user .who b { font-size: var(--text-sm); font-weight: 600; color: var(--color-text); }
  .topbar-user .who span { font-size: var(--text-xs); color: var(--color-text-muted); }

  .nav-toggle { display: none; background: transparent; border: 1px solid var(--color-border); color: var(--color-text); border-radius: var(--radius-md); width: 38px; height: 38px; place-items: center; }
  .nav-toggle svg { width: 19px; height: 19px; }

  /* ---------- Conteúdo ---------- */
  .admin-content { padding: 32px; max-width: 1280px; }
  .content-head { margin-bottom: 28px; }
  .content-head h1 { font-family: var(--font-body); font-weight: 700; font-size: var(--text-2xl); margin: 0 0 4px; }
  .content-head p { color: var(--color-text-muted); font-size: var(--text-sm); margin: 0; }

  /* Cards de métrica */
  .metric-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 28px;
  }
  .metric-card {
    background: var(--color-bg-card);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: 22px;
    transition: border-color .2s ease;
  }
  .metric-card:hover { border-color: var(--color-primary-muted); }
  .metric-card .m-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
  .metric-card .m-label { font-size: var(--text-xs); letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-text-muted); }
  .metric-card .m-icon { color: var(--color-text-muted); }
  .metric-card .m-icon svg { width: 18px; height: 18px; }
  .metric-card .m-value { font-family: var(--font-mono); font-size: var(--text-3xl); font-weight: 600; color: var(--color-text); line-height: 1; }
  .metric-card .m-delta { font-size: var(--text-xs); margin-top: 10px; display: inline-flex; align-items: center; gap: 5px; }
  .m-delta.up { color: var(--color-success); }
  .m-delta.flat { color: var(--color-text-muted); }

  /* Card de tabela */
  .data-card {
    background: var(--color-bg-card);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
  }
  .data-card-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 20px 22px;
    border-bottom: 1px solid var(--color-border);
  }
  .data-card-head h2 { font-family: var(--font-body); font-size: var(--text-lg); font-weight: 600; margin: 0; }
  .data-card-head a { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-primary); }

  table.data-table { width: 100%; border-collapse: collapse; }
  .data-table thead th {
    text-align: left;
    font-size: var(--text-xs); letter-spacing: 0.06em; text-transform: uppercase;
    color: var(--color-text-muted); font-weight: 500;
    padding: 14px 22px;
    border-bottom: 1px solid var(--color-border);
  }
  .data-table tbody td { padding: 16px 22px; border-bottom: 1px solid var(--color-border); font-size: var(--text-sm); }
  .data-table tbody tr:last-child td { border-bottom: none; }
  .data-table tbody tr { transition: background-color .15s ease; }
  .data-table tbody tr:hover { background: var(--color-bg-elevated); }
  .data-table .td-id { font-family: var(--font-mono); color: var(--color-primary); }
  .data-table .td-num { font-family: var(--font-mono); color: var(--color-text); }
  .data-table .td-muted { color: var(--color-text-muted); }
  .cust { display: flex; align-items: center; gap: 10px; }
  .cust .cust-av { width: 28px; height: 28px; border-radius: 50%; background: var(--color-bg-elevated); border: 1px solid var(--color-border); color: var(--color-text-muted); display: grid; place-items: center; font-family: var(--font-mono); font-size: 11px; }

  /* Status badges */
  .status {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: var(--text-xs); font-weight: 500;
    padding: 4px 10px; border-radius: 100px;
    border: 1px solid transparent;
  }
  .status::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
  .status--pago      { color: var(--color-success); background: color-mix(in srgb, var(--color-success) 13%, transparent); border-color: color-mix(in srgb, var(--color-success) 32%, transparent); }
  .status--pendente  { color: var(--color-warning); background: color-mix(in srgb, var(--color-warning) 13%, transparent); border-color: color-mix(in srgb, var(--color-warning) 32%, transparent); }
  .status--enviado   { color: var(--color-info);    background: color-mix(in srgb, var(--color-info) 13%, transparent);    border-color: color-mix(in srgb, var(--color-info) 32%, transparent); }
  .status--cancelado { color: var(--color-danger);  background: color-mix(in srgb, var(--color-danger) 13%, transparent);  border-color: color-mix(in srgb, var(--color-danger) 32%, transparent); }

  /* ---------- Responsivo ---------- */
  @media (max-width: 1040px) {
    .metric-grid { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 860px) {
    .admin-sidebar { transform: translateX(-100%); }
    .admin-sidebar.is-open { transform: translateX(0); box-shadow: var(--shadow-card); }
    .admin-main { margin-left: 0; }
    .nav-toggle { display: grid; }
    .topbar-search { display: none; }
    .admin-content { padding: 22px; }
  }
  @media (max-width: 560px) {
    .metric-grid { grid-template-columns: 1fr; }
    .topbar-user .who { display: none; }
    .data-table thead { display: none; }
    .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; }
    .data-table tbody tr { padding: 8px 0; border-bottom: 1px solid var(--color-border); }
    .data-table tbody td { padding: 6px 22px; border: none; display: flex; justify-content: space-between; gap: 16px; }
    .data-table tbody td::before { content: attr(data-label); color: var(--color-text-muted); font-size: var(--text-xs); text-transform: uppercase; letter-spacing: 0.06em; }
  }

  @media (prefers-reduced-motion: reduce) {
    * { transition: none !important; }
  }

  /* ============ ADIÇÕES (formulários, filtros, detalhe de pedido) ============ */
  .admin-btn { display:inline-flex; align-items:center; gap:8px; background:var(--color-primary); color:var(--color-text-inverse); border:none; border-radius:var(--radius-md); padding:10px 18px; font-size:var(--text-sm); font-weight:600; text-decoration:none; }
  .admin-btn:hover { background:var(--color-primary-hover); }
  .admin-btn--ghost { background:transparent; color:var(--color-text-muted); border:1px solid var(--color-border); }
  .admin-btn--ghost:hover { color:var(--color-text); border-color:var(--color-primary-muted); background:transparent; }
  .admin-select, .admin-input { background:var(--color-bg-elevated); border:1px solid var(--color-border); color:var(--color-text); border-radius:var(--radius-md); padding:10px 12px; font-family:var(--font-body); font-size:var(--text-sm); }
  .admin-select:focus, .admin-input:focus { outline:none; border-color:var(--color-primary-muted); }
  .status--entregue { color:var(--color-success); background:color-mix(in srgb, var(--color-success) 13%, transparent); border-color:color-mix(in srgb, var(--color-success) 32%, transparent); }
  .alert-ok { font-family:var(--font-mono); font-size:var(--text-sm); color:var(--color-success); border:1px solid var(--color-border); border-left:3px solid var(--color-success); border-radius:var(--radius-sm); padding:12px 16px; margin-bottom:22px; }
  .filter-row { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:22px; }
  .filter-pill { font-family:var(--font-mono); font-size:var(--text-xs); letter-spacing:0.06em; text-transform:uppercase; padding:7px 14px; border-radius:100px; border:1px solid var(--color-border); color:var(--color-text-muted); text-decoration:none; }
  .filter-pill:hover { color:var(--color-text); border-color:var(--color-primary-muted); }
  .filter-pill.is-active { background:var(--color-primary); color:var(--color-text-inverse); border-color:var(--color-primary); }
  .order-grid { display:grid; grid-template-columns:1fr 320px; gap:24px; align-items:start; }
  .panel { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:var(--radius-lg); overflow:hidden; margin-bottom:24px; }
  .panel-head { padding:18px 22px; border-bottom:1px solid var(--color-border); font-weight:600; }
  .panel-body { padding:22px; }
  .kv { display:flex; justify-content:space-between; gap:16px; padding:9px 0; font-size:var(--text-sm); border-bottom:1px solid var(--color-border); }
  .kv:last-child { border-bottom:none; }
  .kv .k { color:var(--color-text-muted); }
  .kv .v { font-family:var(--font-mono); color:var(--color-text); text-align:right; }
  .addr-note { white-space:pre-line; color:var(--color-text-muted); font-size:var(--text-sm); line-height:1.7; }
  .back-link { font-family:var(--font-mono); font-size:var(--text-xs); letter-spacing:0.08em; text-transform:uppercase; color:var(--color-text-muted); text-decoration:none; }
  .back-link:hover { color:var(--color-primary); }
  .pagination-wrap { margin-top:22px; display:flex; gap:12px; align-items:center; }
  @media (max-width:860px){ .order-grid{ grid-template-columns:1fr; } }
  </style>
  @stack('styles')
</head>
<body>

  <!-- ============ SIDEBAR ============ -->
  <aside class="admin-sidebar" id="sidebar">
    <div class="admin-brand"><span class="brand-mark"></span>APTK <small>ADMIN</small></div>

    <nav class="admin-nav">
      <p class="nav-label">Geral</p>
      <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Dashboard
      </a>

      <p class="nav-label">Catálogo</p>
      <a href="{{ route('admin.products.index') }}" class="{{ request()->routeIs('admin.products.*') ? 'is-active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 8l7-4 7 4v8l-7 4-7-4z"/><path d="M5 8l7 4 7-4M12 12v8"/></svg>
        Produtos
      </a>
      <a href="{{ route('admin.categories.index') }}" class="{{ request()->routeIs('admin.categories.*') ? 'is-active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1.3"/></svg>
        Categorias
      </a>
      <a href="{{ route('admin.stock.index') }}" class="{{ request()->routeIs('admin.stock.*') ? 'is-active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 7l9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M3 12l9 4 9-4"/></svg>
        Estoque
      </a>

      <p class="nav-label">Vendas</p>
      <a href="{{ route('admin.orders.index') }}" class="{{ request()->routeIs('admin.orders.*') ? 'is-active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 5h2l2 11h9l2-7H7"/><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/></svg>
        Pedidos
      </a>
      <a href="{{ route('admin.coupons.index') }}" class="{{ request()->routeIs('admin.coupons.*') ? 'is-active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"/><path d="M14 6v12"/></svg>
        Cupons
      </a>
      <a href="{{ route('admin.customers.index') }}" class="{{ request()->routeIs('admin.customers.*') ? 'is-active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 5.2a3.2 3.2 0 0 1 0 5.6M21 20c0-2.6-1.6-4.8-4-5.6"/></svg>
        Clientes
      </a>
      <a href="{{ route('admin.leads.index') }}" class="{{ request()->routeIs('admin.leads.*') ? 'is-active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
        Leads
      </a>

      <p class="nav-label">Clube</p>
      <a href="{{ route('admin.plans.index') }}" class="{{ request()->routeIs('admin.plans.*') ? 'is-active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3l2.5 5 5.5.8-4 3.9.9 5.5L12 21l-4.9 2.6.9-5.5-4-3.9 5.5-.8z"/></svg>
        Planos
      </a>
      <a href="{{ route('admin.subscriptions.index') }}" class="{{ request()->routeIs('admin.subscriptions.*') ? 'is-active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg>
        Assinaturas
      </a>

      <p class="nav-label">Sistema</p>
      <a href="{{ route('admin.settings.index') }}" class="{{ request()->routeIs('admin.settings.*') ? 'is-active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 6.6 19l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H2a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4 6.6l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V2a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H22a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/></svg>
        Configurações
      </a>
    </nav>

    <div class="admin-sidebar-foot">
      <a href="{{ route('home') }}" class="back-link">← Ver loja</a><br><br>
      v1.0 · APTK Platform
    </div>
  </aside>

  <!-- ============ MAIN ============ -->
  <div class="admin-main">

    <header class="admin-topbar">
      <button class="nav-toggle" id="navToggle" aria-label="Abrir menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
      <span class="topbar-title">@yield('admin_title', 'Painel')</span>
      <div class="topbar-spacer"></div>

      <div class="topbar-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" placeholder="Buscar pedido, produto, cliente…">
      </div>
      <button class="icon-btn" aria-label="Notificações">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
        <span class="dot"></span>
      </button>
      <form method="POST" action="{{ route('logout') }}" style="display:inline;">
        @csrf
        <button type="submit" class="icon-btn" aria-label="Sair" title="Sair da conta">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
        </button>
      </form>

      <div class="topbar-user">
        <div class="avatar">{{ strtoupper(mb_substr(auth()->user()->name ?? 'AP', 0, 2)) }}</div>
        <div class="who"><b>{{ auth()->user()->name ?? 'Admin' }}</b><span>Administrador</span></div>
      </div>
    </header>

    <div class="admin-content">
      @yield('content')
    </div>

  </div>

  @stack('scripts')
  <script>
    document.getElementById('navToggle')?.addEventListener('click', function () {
      document.getElementById('sidebar').classList.toggle('is-open');
    });
  </script>

</body>
</html>
