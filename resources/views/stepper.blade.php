{{-- Self-contained lifecycle stepper (no app CSS dependency; keeps the package extractable).

     The stages are links now. They were drawn as a read-only diagram while the only way to move
     between them sat in a separate control below — the picture of the pipeline and the way
     through it were two different things on the same card. Colours come from the enum's own ramp
     rather than the generic indigo/green this started with. --}}
@php
    $values = array_map(fn ($c) => $c->value, $cases);
    $currentIdx = $current ? array_search($current->value, $values, true) : 0;
    $stepUrl = $stepUrl ?? null;
@endphp
<div style="display:flex;align-items:center;gap:0;flex-wrap:wrap;margin-top:8px">
    @foreach ($cases as $i => $case)
        @php
            $done = $i < $currentIdx;
            $isCurrent = $i === $currentIdx;
            $hex = method_exists($case, 'hex') ? $case->hex() : '#006241';
            $bg = $isCurrent ? $hex : ($done ? '#009243' : '#EBEBEB');
            $fg = ($isCurrent || $done) ? '#fff' : '#5F5F5F';
        @endphp
        <div style="display:flex;align-items:center">
            <a href="{{ $stepUrl ? $stepUrl.'&status='.$case->value : '#' }}"
               title="{{ $case->label() }}"
               style="display:flex;flex-direction:column;align-items:center;width:86px;text-decoration:none;cursor:{{ $stepUrl ? 'pointer' : 'default' }}">
                <div style="width:26px;height:26px;border-radius:50%;background:{{ $bg }};color:{{ $fg }};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700">
                    {{ $done ? '✓' : $i + 1 }}
                </div>
                <div style="font-size:10px;text-align:center;margin-top:4px;color:{{ $isCurrent ? '#16231D' : '#5F5F5F' }};font-weight:{{ $isCurrent ? 600 : 400 }}">{{ $case->label() }}</div>
            </a>
            @if (!$loop->last)
                <div style="height:2px;width:24px;background:{{ $i < $currentIdx ? '#009243' : '#EBEBEB' }};margin-bottom:16px"></div>
            @endif
        </div>
    @endforeach
</div>
