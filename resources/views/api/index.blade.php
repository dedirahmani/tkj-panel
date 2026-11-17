@extends('layouts.app')

@section('title', __('api.title'))

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight">{{ __('api.title') }}</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('api.subtitle') }}</p>
    </div>

    @if ($plain = session('plain_token'))
        <div x-data="{ copied: false }" class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10">
            <p class="font-semibold text-amber-800 dark:text-amber-300">{{ __('api.token') }}</p>
            <p class="text-sm text-amber-700/80 dark:text-amber-300/80">{{ __('api.plain_warning') }}</p>
            <div class="mt-2 flex items-center gap-2">
                <code x-ref="tok" class="flex-1 break-all rounded bg-amber-100 px-3 py-2 font-mono text-xs dark:bg-amber-500/20">{{ $plain }}</code>
                <button type="button" @click="navigator.clipboard.writeText($refs.tok.textContent.trim()); copied = true; setTimeout(() => copied = false, 1500)"
                        class="rounded bg-amber-200 px-2 py-1 text-xs text-amber-800 dark:bg-amber-500/30 dark:text-amber-200">
                    <span x-show="!copied">copy</span><span x-show="copied" x-cloak>✓</span>
                </button>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Create token --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="mb-3 font-semibold">{{ __('api.create') }}</h3>
            <form method="POST" action="{{ route('api-tokens.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('api.name') }}</label>
                    <input name="name" type="text" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                </div>
                <div>
                    <p class="mb-1 text-sm font-medium">{{ __('api.abilities') }}</p>
                    <div class="space-y-1.5">
                        @foreach ($abilities as $ability)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="abilities[]" value="{{ $ability }}"
                                       class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
                                <code class="text-xs">{{ $ability }}</code>
                            </label>
                        @endforeach
                    </div>
                </div>
                <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('api.create') }}</button>
            </form>
        </div>

        {{-- Token list + usage --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr><th class="px-4 py-3 font-semibold">{{ __('api.name') }}</th><th class="px-4 py-3 font-semibold">{{ __('api.abilities') }}</th><th class="px-4 py-3 font-semibold">{{ __('api.last_used') }}</th><th class="px-4 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($tokens as $token)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $token->name }}</td>
                                <td class="px-4 py-3"><span class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ implode(', ', $token->abilities) }}</span></td>
                                <td class="px-4 py-3 text-xs text-slate-400">{{ $token->last_used_at?->diffForHumans() ?? __('api.never') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('api-tokens.destroy', $token->id) }}" onsubmit="return confirm('{{ __('api.revoke') }}?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">{{ __('api.revoke') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-slate-400">{{ __('api.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-2 font-semibold">{{ __('api.usage') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('api.base_url') }}: <code>{{ url('/api/v1') }}</code></p>
                <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-900 px-4 py-3 text-xs text-slate-100"><code>curl -H "Authorization: Bearer &lt;token&gt;" {{ url('/api/v1/sites') }}</code></pre>
                <ul class="mt-3 space-y-1 font-mono text-xs text-slate-500 dark:text-slate-400">
                    <li>GET /api/v1/me</li>
                    <li>GET /api/v1/sites <span class="text-slate-400">(sites:read)</span></li>
                    <li>POST /api/v1/sites <span class="text-slate-400">(sites:write)</span></li>
                    <li>GET /api/v1/databases <span class="text-slate-400">(databases:read)</span></li>
                </ul>
            </div>
        </div>
    </div>
@endsection
