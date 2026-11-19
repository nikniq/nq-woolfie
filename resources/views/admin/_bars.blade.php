{{-- Daily bar chart. Expects $title, $data (date => count) and optional $tone. --}}
@php($max = max(1, max($data ?: [0])))
<figure class="admin-chart @if (! empty($tone)) admin-chart--{{ $tone }} @endif">
    <figcaption><span>{{ $title }}</span> <strong>{{ number_format(array_sum($data)) }}</strong> <span class="muted">· today {{ number_format(end($data) ?: 0) }}</span></figcaption>
    <div class="admin-chart__bars" role="img" aria-label="{{ $title }} per day, last {{ count($data) }} days">
        @foreach ($data as $day => $n)
            <span style="height: {{ max(2, round($n / $max * 100)) }}%" title="{{ \Illuminate\Support\Carbon::parse($day)->format('D j M') }}: {{ $n }}"></span>
        @endforeach
    </div>
    <div class="admin-chart__axis muted"><span>{{ \Illuminate\Support\Carbon::parse(array_key_first($data))->format('j M') }}</span><span>{{ \Illuminate\Support\Carbon::parse(array_key_last($data))->format('j M') }}</span></div>
</figure>
