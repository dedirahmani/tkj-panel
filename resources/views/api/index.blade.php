@extends('layouts.app')

@section('title', __('api.title'))

@section('content')
    <p class="tkj-intro mb-5">{{ __('api.subtitle') }}</p>

    @if ($plain = session('plain_token'))
        <div x-data="{ copied: false }" class="tkj-warn mb-5">
            <p class="font-semibold">{{ __('api.token') }}</p>
            <p class="mt-0.5">{{ __('api.plain_warning') }}</p>
            <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
                <code x-ref="tok" class="min-w-0 flex-1 break-all rounded-md border border-amber-300 bg-white px-3 py-2 font-mono text-[13px] text-slate-900 dark:border-amber-500/40 dark:bg-slate-950 dark:text-slate-100">{{ $plain }}</code>
                <button type="button" @click="navigator.clipboard.writeText($refs.tok.textContent.trim()); copied = true; setTimeout(() => copied = false, 1500)"
                        class="tkj-btn-secondary shrink-0">
                    <span x-show="!copied">{{ __('api.copy') }}</span><span x-show="copied" x-cloak>{{ __('api.copied') }}</span>
                </button>
            </div>
            <p class="sr-only" aria-live="polite" x-text="copied ? @js(__('api.copied')) : ''"></p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3 lg:items-start">
        {{-- Create token --}}
        <section aria-labelledby="create-token-heading" class="tkj-panel">
            <h2 id="create-token-heading" class="tkj-panel-head">{{ __('api.create') }}</h2>
            <form method="POST" action="{{ route('api-tokens.store') }}" class="tkj-panel-body space-y-4">
                @csrf
                <div>
                    <label for="token-name" class="tkj-label">{{ __('api.name') }}</label>
                    <input id="token-name" name="name" type="text" required value="{{ old('name') }}"
                           class="tkj-input" @error('name') aria-invalid="true" @enderror>
                    @error('name')<p class="tkj-error">{{ $message }}</p>@enderror
                </div>
                <fieldset>
                    <legend class="tkj-label">{{ __('api.abilities') }}</legend>
                    <div class="space-y-1">
                        @foreach ($abilities as $ability)
                            <label class="flex min-h-[32px] items-center gap-2 text-sm text-slate-800 dark:text-slate-200">
                                <input type="checkbox" name="abilities[]" value="{{ $ability }}" class="tkj-check"
                                       @checked(in_array($ability, old('abilities', []), true))>
                                <span class="tkj-code">{{ $ability }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('abilities')<p class="tkj-error">{{ $message }}</p>@enderror
                    @error('abilities.*')<p class="tkj-error">{{ $message }}</p>@enderror
                </fieldset>
                <button type="submit" class="tkj-btn w-full">{{ __('api.create') }}</button>
            </form>
        </section>

        {{-- Token list + usage --}}
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @if ($tokens->isEmpty())
                <div class="tkj-panel tkj-empty">
                    <p>{{ __('api.empty') }}</p>
                    <a href="#token-name" class="tkj-btn mt-4">{{ __('api.create') }}</a>
                </div>
            @else
                <div class="tkj-panel overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="tkj-table">
                            <thead>
                                <tr>
                                    <th scope="col" class="tkj-th">{{ __('api.name') }}</th>
                                    <th scope="col" class="tkj-th">{{ __('api.abilities') }}</th>
                                    <th scope="col" class="tkj-th">{{ __('api.last_used') }}</th>
                                    <th scope="col" class="tkj-th text-right">{{ __('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                @foreach ($tokens as $token)
                                    <tr>
                                        <td class="tkj-td"><span class="tkj-code font-medium text-slate-900 dark:text-white">{{ $token->name }}</span></td>
                                        <td class="tkj-td"><span class="tkj-code text-slate-600 dark:text-slate-400">{{ implode(', ', $token->abilities) }}</span></td>
                                        <td class="tkj-td whitespace-nowrap text-slate-600 dark:text-slate-400">{{ $token->last_used_at?->diffForHumans() ?? __('api.never') }}</td>
                                        <td class="tkj-td">
                                            <div class="flex justify-end">
                                                <form method="POST" action="{{ route('api-tokens.destroy', $token->id) }}" onsubmit="return confirm('{{ __('api.revoke') }}?')">
                                                    @csrf @method('DELETE')
                                                    <button class="tkj-action-danger">{{ __('api.revoke') }}</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <section aria-labelledby="usage-heading" class="tkj-panel">
                <h2 id="usage-heading" class="tkj-panel-head">{{ __('api.usage') }}</h2>
                <div class="tkj-panel-body">
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('api.base_url') }}: <code class="tkj-code break-all text-slate-900 dark:text-slate-100">{{ url('/api/v1') }}</code></p>
                    <pre class="mt-3 overflow-x-auto rounded-md bg-slate-900 px-4 py-3 font-mono text-[13px] text-slate-100 dark:border dark:border-slate-700 dark:bg-slate-950"><code>curl -H "Authorization: Bearer &lt;token&gt;" {{ url('/api/v1/sites') }}</code></pre>
                    <h3 class="mt-4 text-sm font-semibold text-slate-900 dark:text-white">{{ __('api.endpoints') }}</h3>
                    <ul class="mt-2 space-y-1 font-mono text-[13px] text-slate-800 dark:text-slate-200">
                        <li>GET /api/v1/me</li>
                        <li>GET /api/v1/sites <span class="text-slate-600 dark:text-slate-400">(sites:read)</span></li>
                        <li>POST /api/v1/sites <span class="text-slate-600 dark:text-slate-400">(sites:write)</span></li>
                        <li>GET /api/v1/databases <span class="text-slate-600 dark:text-slate-400">(databases:read)</span></li>
                    </ul>
                </div>
            </section>
        </div>
    </div>
@endsection
