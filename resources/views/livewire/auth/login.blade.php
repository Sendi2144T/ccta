<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

<body style="margin:0; height:100vh; background:linear-gradient(#a8e063, #56ab2f); display:flex; justify-content:center; align-items:center;">

    <div style="background:white;padding:30px;border-radius:10px;box-shadow:0px 5px 15px rgba(0,0,0,0.1);width:300px;text-align:center;">
        
        <h2 style="margin-bottom:20px;">Login</h2>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form wire:submit="login" style="display:flex;flex-direction:column;gap:15px;">
            <!-- Email -->
            <flux:input
                wire:model="email"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="Email"
            />

            <!-- Password -->
            <flux:input
                wire:model="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                placeholder="Password"
                viewable
            />            

            <!-- Remember Me -->
            <flux:checkbox wire:model="remember" :label="__('Remember me')" />

            <button type="submit" style="background:#56ab2f;color:white;border:none;padding:10px 25px;border-radius:20px;cursor:pointer;">
                Login
            </button>
        </form>

        @if (Route::has('register'))
            <div style="margin-top:15px;font-size:14px;">
                <span>Don't have an account? </span>
                <flux:link :href="route('register')" wire:navigate>Sign up</flux:link>
            </div>
        @endif
    </div>
</body>

