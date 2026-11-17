{{-- Pengalih bahasa ID / EN. --}}
<div class="inline-flex overflow-hidden rounded-lg border border-slate-200 text-xs font-semibold dark:border-slate-700">
    @foreach (['id' => 'ID', 'en' => 'EN'] as $code => $label)
        <a href="{{ route('locale.switch', $code) }}"
           class="px-2.5 py-1.5 transition {{ app()->getLocale() === $code
                ? 'bg-brand-600 text-white'
                : 'bg-white text-slate-500 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-slate-800' }}">
            {{ $label }}
        </a>
    @endforeach
</div>
