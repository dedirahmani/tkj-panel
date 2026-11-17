{{-- Pengalih bahasa ID / EN. --}}
<div class="inline-flex overflow-hidden rounded-md border border-slate-300 text-xs font-semibold dark:border-slate-600" role="group" aria-label="{{ __('common.language') }}">
    @foreach (['id' => 'ID', 'en' => 'EN'] as $code => $label)
        @php $current = app()->getLocale() === $code; @endphp
        <a href="{{ route('locale.switch', $code) }}" lang="{{ $code }}"
           @if ($current) aria-current="true" @endif
           @class([
               'inline-flex min-h-[36px] items-center px-3',
               'bg-brand-600 text-white' => $current,
               'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800' => ! $current,
           ])>
            {{ $label }}
        </a>
    @endforeach
</div>
