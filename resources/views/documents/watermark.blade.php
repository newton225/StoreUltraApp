{{-- Company name printed across every page, very light so that reading and printing are not hindered. --}}
@if (! empty($watermark))
    <div style="position: fixed; top: 38%; left: 0; width: 100%; text-align: center; font-size: 64px; font-weight: bold; color: #9ca3af; opacity: 0.12; transform: rotate(-30deg); transform-origin: 50% 50%; z-index: -1;">{{ $watermark }}</div>
@endif
