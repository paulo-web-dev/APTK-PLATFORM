<x-guest-layout>
    <p class="auth-sub">Nova senha</p>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="mb-3">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}"
                   class="form-control" required autofocus autocomplete="username"
                   placeholder="seu@email.com">
            @error('email') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="password">Nova senha</label>
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

        <button type="submit" class="btn-aptk btn-aptk--block">Redefinir senha</button>
    </form>
</x-guest-layout>
