<x-guest-layout>
    <p class="auth-sub">Crie sua conta</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="mb-3">
            <label for="name">Nome</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}"
                   class="form-control" required autofocus autocomplete="name"
                   placeholder="Seu nome">
            @error('name') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   class="form-control" required autocomplete="username"
                   placeholder="seu@email.com">
            @error('email') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="password">Senha</label>
            <input id="password" type="password" name="password"
                   class="form-control" required autocomplete="new-password"
                   placeholder="••••••••">
            @error('password') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-4">
            <label for="password_confirmation">Confirmar senha</label>
            <input id="password_confirmation" type="password" name="password_confirmation"
                   class="form-control" required autocomplete="new-password"
                   placeholder="••••••••">
            @error('password_confirmation') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn-aptk btn-aptk--block">Criar conta</button>
    </form>

    <div class="auth-foot">
        Já tem conta? <a href="{{ route('login') }}">Entrar</a>
    </div>
</x-guest-layout>
