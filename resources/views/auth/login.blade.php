<x-guest-layout>
    <p class="auth-sub">Acesse sua conta</p>

    @if (session('status'))
        <div class="auth-status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   class="form-control" required autofocus autocomplete="username"
                   placeholder="seu@email.com">
            @error('email') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="password">Senha</label>
            <input id="password" type="password" name="password"
                   class="form-control" required autocomplete="current-password"
                   placeholder="••••••••">
            @error('password') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label" for="remember" style="margin:0; color:var(--color-text-muted);">Lembrar de mim</label>
            </div>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" style="color:var(--color-primary); font-size:var(--text-sm);">Esqueci a senha</a>
            @endif
        </div>

        <button type="submit" class="btn-aptk btn-aptk--block">Entrar</button>
    </form>

    <div class="auth-foot">
        Não tem conta? <a href="{{ route('register') }}">Cadastre-se</a>
    </div>
</x-guest-layout>
