<x-guest-layout>
    <p class="auth-sub">Recuperar senha</p>

    <p style="color:var(--color-text-muted); font-size:var(--text-sm); text-align:center; margin-bottom:22px;">
        Informe seu e-mail e enviaremos um link para redefinir a senha.
    </p>

    @if (session('status'))
        <div class="auth-status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-4">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   class="form-control" required autofocus
                   placeholder="seu@email.com">
            @error('email') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn-aptk btn-aptk--block">Enviar link</button>
    </form>

    <div class="auth-foot">
        <a href="{{ route('login') }}">Voltar ao login</a>
    </div>
</x-guest-layout>
