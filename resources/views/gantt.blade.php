{{-- Server-rendered Gantt (no JS): task rows with proportional bars over a shared window. --}}
@php $statusHex = [1=>'#9ca3af',2=>'#3b82f6',3=>'#ef4444',4=>'#22c55e',5=>'#71717a']; @endphp

@if (!$window)
    <div style="color:#6b7280;padding:24px;text-align:center">Aucune tâche avec dates à afficher dans le Gantt.</div>
@else
    <div style="font-size:12px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <strong style="font-size:16px">Gantt</strong>
            <span style="color:#6b7280">{{ $window['label'] }}</span>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
            @foreach ($groups as $projectName => $rows)
                <div style="background:#eef2ff;color:#3730a3;font-weight:600;padding:6px 10px;border-bottom:1px solid #e5e7eb;font-size:11px">
                    {{ $projectName }} ({{ $rows->count() }})
                </div>
                @foreach ($rows as $row)
                    @php $t = $row['task']; $bar = $row['bar']; @endphp
                    <div style="display:flex;align-items:center;border-bottom:1px solid #f1f1f1;height:34px">
                        <div style="flex:0 0 280px;padding:0 10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:6px">
                            <span style="width:8px;height:8px;border-radius:50%;flex:0 0 auto;background:{{ $statusHex[$t->status?->value] ?? '#9ca3af' }}"></span>
                            <span>{{ $t->title }}</span>
                        </div>
                        <div style="position:relative;flex:1;height:100%;border-left:1px solid #e5e7eb">
                            @if ($window['todayPct'] !== null)
                                <div style="position:absolute;top:0;bottom:0;width:1px;background:#ec4899;left:{{ $window['todayPct'] }}%"></div>
                            @endif
                            @if ($bar)
                                <div title="{{ $t->start_date?->format('Y-m-d') }} → {{ $t->due_date?->format('Y-m-d') }}"
                                     style="position:absolute;top:7px;height:20px;border-radius:5px;color:#fff;font-size:10px;display:flex;align-items:center;padding:0 6px;overflow:hidden;left:{{ $bar['left'] }}%;width:{{ $bar['width'] }}%;background:{{ $statusHex[$t->status?->value] ?? '#9ca3af' }}">
                                    <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $t->title }}</span>
                                </div>
                            @else
                                <span style="position:absolute;left:6px;top:9px;color:#9ca3af;font-size:10px">(sans dates)</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
        <div style="color:#9ca3af;font-size:11px;margin-top:6px">Barre = début→échéance · ligne rose = aujourd'hui</div>
    </div>
@endif
