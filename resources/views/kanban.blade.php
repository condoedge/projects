{{-- Server-rendered Kanban with native HTML5 drag-drop (inline on* handlers work under Kompo v-html;
     no Alpine / no <script> needed). Dropping a card moves it + POSTs the new status.

     On width: five 260px columns did not fit, so an earlier pass folded the empty ones to a 44px
     strip. That broke the moment a card was dropped into one — folding is decided server-side,
     the drop moves the card client-side, and the card was crushed into the sliver. Columns are
     now elastic instead (flex 1, min 190px), which fits five of them without folding anything
     and removes that whole class of divergence. An empty column says so in plain words.

     On the cards: the project's name is dropped when the board is already filtered to one
     project, the stage colour lives on the column rather than on every card, and the progress
     bar only appears while a task is genuinely part-done — a full green bar under a card already
     in "Terminée" was repeating what the column had said. --}}
@php
    $today = \Illuminate\Support\Carbon::today();
    $scoped = !empty($projectId);

    // The stage colour as a wash behind its header. A 3px line was the only thing telling the
    // columns apart, and it read as an artefact rather than as meaning; a tinted surface plus a
    // dot carries the same information at a glance and leans on no border utility.
    $wash = function ($hex, $alpha) {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $alpha . ')';
    };

    // Both columns are re-synced after a move, not just the one receiving the card. The first
    // version only hid the arrival column's placeholder and nothing ever put it back, so a
    // column you dropped into and then emptied again kept no "nothing here" — and the counts in
    // the headers never moved at all, which made the board disagree with itself until a reload.
    $dropJs = function ($value) use ($statusUrl, $csrf) {
        return 'event.preventDefault();this.classList.remove("pm-over");'
            .'var id=event.dataTransfer.getData("text/plain");'
            .'var card=document.getElementById("pm-card-"+id);'
            .'if(card){var from=card.parentElement;this.appendChild(card);'
            .'[from,this].forEach(function(b){if(!b)return;'
            .'var n=b.querySelectorAll(".pm-card").length;'
            .'var e=b.querySelector(".pm-empty");if(e){e.style.display=n?"none":"";}'
            .'var col=b.closest(".pm-col");var c=col&&col.querySelector(".pm-col-count");'
            .'if(c){c.textContent=n;}});}'
            .'fetch("'.$statusUrl.'",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":"'.$csrf.'","Accept":"application/json"},body:JSON.stringify({task_id:id,status:'.$value.'})});';
    };
@endphp

<style>
    .pm-board{display:flex;gap:14px;align-items:stretch;overflow-x:auto;padding-bottom:14px}
    .pm-col{flex:1 1 0;min-width:190px;display:flex;flex-direction:column;background:#FBFDFC;border-radius:12px;box-shadow:0 1px 3px rgba(20,35,29,.06)}
    .pm-col-head{display:flex;align-items:center;gap:8px;padding:11px 14px;border-radius:12px 12px 0 0}
    .pm-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
    .pm-col-name{font-weight:600;font-size:13px;color:#16231D;letter-spacing:.01em}
    .pm-col-count{margin-left:auto;font-size:12px;font-weight:700}
    .pm-col-body{padding:10px;display:flex;flex-direction:column;gap:9px;flex:1;min-height:110px;border-radius:0 0 12px 12px;transition:background .12s}
    .pm-col-body.pm-over{background:#D4E9E2}
    .pm-col-body{background:transparent}
    .pm-empty{font-size:11px;color:#94A39D;text-align:center;padding:18px 6px;border:1px dashed #DCE8E2;border-radius:9px}
    .pm-card{background:#FFF;border:1px solid #E7EFEA;border-radius:9px;padding:11px 12px;cursor:grab;box-shadow:0 1px 2px rgba(20,35,29,.07);transition:box-shadow .12s,border-color .12s}
    .pm-card:hover{box-shadow:0 3px 10px rgba(20,35,29,.10);border-color:#CBDCD3}
    .pm-card:active{cursor:grabbing}
    .pm-card-title{font-size:13px;font-weight:600;line-height:1.4;color:#16231D}
    .pm-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:9px}
    .pm-prio{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600}
    .pm-prio i{width:3px;height:11px;border-radius:2px;display:inline-block}
    .pm-kind{font-size:11px;color:#7A8A82}
    .pm-tag{font-size:10px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#004A2D;background:#D4E9E2;border-radius:4px;padding:1px 6px}
    .pm-foot{margin-top:9px;font-size:11px;color:#7A8A82;display:flex;align-items:center;justify-content:space-between;gap:8px}
    .pm-date{margin-left:auto;font-size:11px;color:#7A8A82;white-space:nowrap}
    .pm-late{color:#9C2B22;font-weight:700}
    .pm-meta{row-gap:6px}
    .pm-bar{height:3px;background:#E1EAE5;border-radius:2px;margin-top:9px;overflow:hidden}
    .pm-bar span{display:block;height:100%;background:#009243;border-radius:2px}
    .pm-hint{color:#8A9891;font-size:11px;margin-top:2px}
    .pm-lane{font-size:13px;font-weight:700;color:#16231D;margin:14px 0 8px;padding-left:2px;border-left:3px solid #006241;padding-left:9px}
    .pm-who{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:#D4E9E2;color:#004A2D;font-size:9px;font-weight:700;letter-spacing:.02em}
    .pm-open{display:inline-flex;align-items:center;gap:4px;font-size:11px;color:#006241;text-decoration:none;font-weight:600}
    .pm-open:hover{text-decoration:underline}
    .pm-late-banner{display:inline-flex;align-items:center;gap:7px;background:#F6DAD8;color:#9C2B22;font-size:12px;font-weight:600;border-radius:8px;padding:6px 12px;margin-bottom:12px}
</style>

@php
    $lateTotal = collect($lanes)->flatMap(fn ($l) => $l['columns'])->sum(fn ($c) => $c['tasks']->filter(fn ($t) =>
        $t->due_date && !in_array($t->status?->value, [4, 5]) && $t->due_date->lt($today))->count());
@endphp
@if ($lateTotal)
    <div class="pm-late-banner">
        <span style="width:7px;height:7px;border-radius:50%;background:#9C2B22"></span>
        {{ __('projects.n-late-tasks', ['n' => $lateTotal]) }}
    </div>
@endif

@foreach ($lanes as $lane)
@if ($lane['label'])
    <div class="pm-lane">{{ $lane['label'] }}</div>
@endif
<div class="pm-board">
    @foreach ($lane['columns'] as $col)
        <div class="pm-col">
            <div class="pm-col-head" style="background:{{ $wash($col['hex'], '0.13') }}">
                <span class="pm-dot" style="background:{{ $col['hex'] }}"></span>
                <span class="pm-col-name">{{ $col['label'] }}</span>
                <span class="pm-col-count" style="color:{{ $col['hex'] }}">{{ $col['tasks']->count() }}</span>
            </div>

            <div class="pm-col-body"
                 ondragover="event.preventDefault();this.classList.add('pm-over');"
                 ondragleave="this.classList.remove('pm-over');"
                 ondrop="{{ $dropJs($col['value']) }}">

                <div class="pm-empty" @style(['display:none' => $col['tasks']->isNotEmpty()])>{{ __('projects.kanban-empty-column') }}</div>

                @foreach ($col['tasks'] as $t)
                    @php
                        $overdue = $t->due_date && !in_array($t->status?->value, [4, 5]) && $t->due_date->lt($today);
                        $prioHex = $t->priority?->entry()?->enumCase()?->hex() ?? '#5F5F5F';
                        $pct = (int) $t->completion_pct;
                    @endphp
                    {{-- Clicking opens the drawer by way of the hidden Kompo link the page
                         rendered for this task. The drag flag stops a drop from also reading as
                         a click, which would open a drawer every time a card was moved. --}}
                    <div id="pm-card-{{ $t->id }}" class="pm-card" draggable="true"
                         ondragstart="this.dataset.drag='1';event.dataTransfer.setData('text/plain','{{ $t->id }}');event.dataTransfer.effectAllowed='move';this.style.opacity='0.45';"
                         ondragend="this.style.opacity='1';var c=this;setTimeout(function(){c.dataset.drag='';},80);"
                         onclick="if(this.dataset.drag)return;var d=document.getElementById('pm-drawer-{{ $t->id }}');if(d)d.click();">

                        <div class="pm-card-title">{{ $t->title }}</div>

                        <div class="pm-meta">
                            @if ($t->priority)
                                <span class="pm-prio" style="color:{{ $prioHex }}"><i style="background:{{ $prioHex }}"></i>{{ $t->priority->label() }}</span>
                            @endif
                            @if ($t->kind)
                                <span class="pm-kind">{{ $t->kind->label() }}</span>
                            @endif
                            @if (!$scoped && $t->project)
                                <span class="pm-tag">{{ $t->project->name }}</span>
                            @endif
                            {{-- The date rides the same line: on its own it left an orphan row
                                 under every card, which is most of what made them feel loose. --}}
                            @if ($t->due_date)
                                <span class="pm-date @if($overdue) pm-late @endif">{{ $t->due_date->translatedFormat('j M') }}</span>
                            @endif
                        </div>

                        {{-- Only while genuinely part-done: 0% and 100% are already said elsewhere. --}}
                        @if ($pct > 0 && $pct < 100)
                            <div class="pm-bar"><span style="width:{{ $pct }}%"></span></div>
                        @endif

                        {{-- The card was not clickable at all: dragging was the only thing you
                             could do to it. Raw markup inside a Blade view cannot carry a Kompo
                             interaction, so this is a plain link to the record rather than the
                             drawer the tables open — the honest limit of rendering here. --}}
                        <div class="pm-foot">
                            @if ($t->assignee)
                                <span class="pm-who" title="{{ $t->assignee->name }}">{{ \Illuminate\Support\Str::of($t->assignee->name)->explode(' ')->take(2)->map(fn($w) => mb_strtoupper(mb_substr($w,0,1)))->implode('') }}</span>
                            @endif
                            <a class="pm-open" href="{{ route('pm.task', ['id' => $t->id]) }}" draggable="false" onclick="event.stopPropagation()">{{ __('projects.open-record') }} →</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endforeach
<div class="pm-hint">{{ __('projects.kanban-hint') }}</div>
