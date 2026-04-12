@php
    /** @var \App\Domain\Survey\Models\Survey $survey */
    $sortedQuestions = $survey->questions->values();
    $privacy = $survey->privacy_settings ?? [];
    $questionsPayload = $sortedQuestions->map(fn ($q) => [
        'id' => $q->id,
        'label' => $q->label,
        'type' => $q->type,
        'required' => (bool) $q->required,
        'options' => $q->options ?? [],
        'config' => $q->config ?? (object) [],
        'conditional_logic' => $q->conditional_logic,
    ])->values()->all();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $survey->title }}</title>
    <style>
        :root { --survey-color: {{ $surveyColor }}; }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #374151;
            background: #fff;
            padding: 16px;
        }
        h1 { font-size: 1.25rem; font-weight: 600; color: #111; margin-bottom: 1rem; }
        .q { margin-bottom: 1.5rem; }
        .q[hidden] { display: none !important; }
        .ql { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #111; }
        .req { color: #dc2626; }
        input[type="text"], input[type="email"], textarea, select {
            width: 100%; padding: 0.6rem 0.75rem; font-size: 14px;
            border: 1px solid #d1d5db; border-radius: 6px; background: #f9fafb;
        }
        input:focus, textarea:focus, select:focus {
            outline: none; border-color: var(--survey-color); background: #fff;
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--survey-color) 25%, transparent);
        }
        textarea { min-height: 100px; resize: vertical; }
        .opt { display: flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0; cursor: pointer; }
        .rating-row, .nps-row { display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; }
        .star-btn {
            border: none; background: none; cursor: pointer; font-size: 1.5rem;
            line-height: 1; color: #d1d5db; padding: 0.15rem;
        }
        .star-btn.on { color: #fbbf24; }
        .nps-btn {
            width: 2.25rem; height: 2.25rem; border: 1px solid #d1d5db; border-radius: 6px;
            background: #fff; cursor: pointer; font-size: 13px; font-weight: 500;
        }
        .nps-btn.sel { color: #fff; border-color: transparent; }
        .nps-btn.sel.p { background: #10b981; }
        .nps-btn.sel.m { background: #f59e0b; }
        .nps-btn.sel.d { background: #ef4444; }
        .nps-hint { display: flex; justify-content: space-between; font-size: 11px; color: #6b7280; margin-top: 0.25rem; }
        .ident { display: grid; gap: 0.75rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; }
        @media (min-width: 480px) { .ident-2 { grid-template-columns: 1fr 1fr; } }
        .btn {
            width: 100%; margin-top: 1.25rem; padding: 0.75rem 1rem; font-size: 16px; font-weight: 600;
            color: #fff; background: var(--survey-color); border: none; border-radius: 8px; cursor: pointer;
        }
        .btn:disabled { opacity: 0.55; cursor: not-allowed; }
        .err {
            padding: 0.65rem 0.75rem; margin-bottom: 1rem; font-size: 13px;
            color: #991b1b; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px;
        }
        .ok { text-align: center; padding: 2rem 1rem; }
        .ok h2 { font-size: 1.25rem; margin-bottom: 0.5rem; color: #111; }
        .ok p { color: #6b7280; margin-bottom: 1rem; }
        .loading form { opacity: 0.65; pointer-events: none; }
    </style>
</head>
<body>
    <div id="wrap">
        <div id="alert" class="err" hidden role="alert"></div>

        <div id="success" class="ok" hidden>
            <h2>Thank you</h2>
            <p id="success-msg"></p>
            <a id="success-link" class="btn" style="display:inline-block;width:auto;text-decoration:none;" href="#" hidden>Continue</a>
        </div>

        <form id="sf">
            <h1>{{ $survey->title }}</h1>

            @foreach ($sortedQuestions as $qi => $question)
                @php
                    $qid = $question->id;
                    $opts = $question->options ?? [];
                    $rmax = (int) (($question->config['max'] ?? 5) ?: 5);
                @endphp
                <div class="q" data-qid="{{ $qid }}" data-qix="{{ $qi }}">
                    <span class="ql">
                        {{ $loop->iteration }}. {{ $question->label }}
                        @if ($question->required)<span class="req">*</span>@endif
                    </span>

                    @if ($question->type === 'text')
                        <input type="text" name="a_{{ $qid }}" data-qid="{{ $qid }}" data-kind="text" autocomplete="off">
                    @elseif ($question->type === 'textarea')
                        <textarea name="a_{{ $qid }}" data-qid="{{ $qid }}" data-kind="textarea" rows="{{ (int) ($question->config['rows'] ?? 4) }}"></textarea>
                    @elseif ($question->type === 'multiple_choice')
                        @foreach ($opts as $oi => $opt)
                            <label class="opt">
                                <input type="radio" name="q_{{ $qid }}" value="{{ $opt }}" data-qid="{{ $qid }}" data-kind="radio">
                                <span>{{ $opt }}</span>
                            </label>
                        @endforeach
                    @elseif ($question->type === 'dropdown')
                        <select data-qid="{{ $qid }}" data-kind="select">
                            <option value="">Select…</option>
                            @foreach ($opts as $opt)
                                <option value="{{ $opt }}">{{ $opt }}</option>
                            @endforeach
                        </select>
                    @elseif ($question->type === 'rating')
                        <input type="hidden" id="hid_{{ $qid }}" value="" data-qid="{{ $qid }}" data-kind="hidden">
                        <div class="rating-row" data-rating-for="{{ $qid }}">
                            @for ($s = 1; $s <= $rmax; $s++)
                                <button type="button" class="star-btn" data-star="{{ $s }}" aria-label="{{ $s }}">{{ '★' }}</button>
                            @endfor
                            <span class="rating-hint" style="margin-left:0.5rem;font-size:12px;color:#6b7280;"></span>
                        </div>
                    @elseif ($question->type === 'nps')
                        <input type="hidden" id="hid_{{ $qid }}" value="" data-qid="{{ $qid }}" data-kind="hidden">
                        <div class="nps-row" data-nps-for="{{ $qid }}">
                            @for ($n = 0; $n <= 10; $n++)
                                <button type="button" class="nps-btn" data-nps="{{ $n }}">{{ $n }}</button>
                            @endfor
                        </div>
                        <div class="nps-hint"><span>Not at all likely</span><span>Extremely likely</span></div>
                    @endif
                </div>
            @endforeach

            @if (empty($privacy['anonymous']))
                <div class="ident ident-2">
                    <div>
                        <label class="ql" for="fn">First name @if (!empty($privacy['require_email']))<span class="req">*</span>@endif</label>
                        <input id="fn" name="first_name" type="text" @if (!empty($privacy['require_email'])) required @endif>
                    </div>
                    <div>
                        <label class="ql" for="ln">Last name @if (!empty($privacy['require_email']))<span class="req">*</span>@endif</label>
                        <input id="ln" name="last_name" type="text" @if (!empty($privacy['require_email'])) required @endif>
                    </div>
                </div>
                <div class="ident">
                    <label class="ql" for="em">Email @if (!empty($privacy['require_email']))<span class="req">*</span>@endif</label>
                    <input id="em" name="email" type="email" @if (!empty($privacy['require_email'])) required @endif>
                </div>
            @endif

            <button type="submit" class="btn" id="sub">Submit</button>
        </form>
    </div>

    <script type="application/json" id="embed-config">{!! json_encode([
        'surveyUuid' => $survey->uuid,
        'submitUrl' => $submitUrl,
        'agentId' => $agentId,
        'questions' => $questionsPayload,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>

    <script>
(function () {
    var cfg = JSON.parse(document.getElementById('embed-config').textContent);
    var qs = cfg.questions;
    var token = document.querySelector('meta[name="csrf-token"]');
    var startTime = Date.now();

    function getAnswer(qid) {
        qid = String(qid);
        var el = document.querySelector('[data-qid="' + qid + '"][data-kind="text"]')
            || document.querySelector('[data-qid="' + qid + '"][data-kind="textarea"]');
        if (el) return (el.value || '').trim();
        el = document.querySelector('select[data-qid="' + qid + '"]');
        if (el) return (el.value || '').trim();
        var r = document.querySelector('input[name="q_' + qid + '"]:checked');
        if (r) return r.value;
        el = document.getElementById('hid_' + qid);
        if (el && el.value !== '') {
            var n = Number(el.value);
            return isNaN(n) ? el.value : n;
        }
        return null;
    }

    function visible(q, i) {
        var L = q.conditional_logic;
        if (!L || typeof L !== 'object') return true;
        if (L.rules && Array.isArray(L.rules)) {
            for (var r = 0; r < L.rules.length; r++) {
                var rule = L.rules[r];
                if (!rule || rule.question == null) continue;
                var tq = qs[rule.question];
                if (!tq) continue;
                var av = getAnswer(tq.id);
                if (av == rule.equals) return true;
            }
            return false;
        }
        var si = L.show_if_question;
        var tq = (si != null && qs[si]) ? qs[si] : null;
        if (!tq) return true;
        var av = getAnswer(tq.id);
        if (Object.prototype.hasOwnProperty.call(L, 'equals')) return av == L.equals;
        if (L.equals_any && Array.isArray(L.equals_any)) return L.equals_any.indexOf(av) !== -1;
        return true;
    }

    function refreshVisibility() {
        qs.forEach(function (q, i) {
            var row = document.querySelector('.q[data-qid="' + q.id + '"]');
            if (!row) return;
            if (visible(q, i)) row.removeAttribute('hidden');
            else row.setAttribute('hidden', '');
        });
    }

    function npsTier(n) {
        if (n >= 9) return 'p';
        if (n >= 7) return 'm';
        return 'd';
    }

    document.getElementById('wrap').addEventListener('click', function (e) {
        var t = e.target;
        if (t.matches('.star-btn')) {
            var row = t.closest('[data-rating-for]');
            if (!row) return;
            var qid = row.getAttribute('data-rating-for');
            var v = parseInt(t.getAttribute('data-star'), 10);
            var hid = document.getElementById('hid_' + qid);
            if (hid) hid.value = String(v);
            row.querySelectorAll('.star-btn').forEach(function (b, i) {
                b.classList.toggle('on', i < v);
            });
            var h = row.querySelector('.rating-hint');
            if (h) h.textContent = v ? (v + ' / ' + row.querySelectorAll('.star-btn').length) : '';
            refreshVisibility();
        }
        if (t.matches('.nps-btn')) {
            var nrow = t.closest('[data-nps-for]');
            if (!nrow) return;
            var qid2 = nrow.getAttribute('data-nps-for');
            var nv = parseInt(t.getAttribute('data-nps'), 10);
            var hid2 = document.getElementById('hid_' + qid2);
            if (hid2) hid2.value = String(nv);
            var tier = npsTier(nv);
            nrow.querySelectorAll('.nps-btn').forEach(function (b) {
                b.classList.remove('sel', 'p', 'm', 'd');
            });
            t.classList.add('sel', tier === 'p' ? 'p' : tier === 'm' ? 'm' : 'd');
            refreshVisibility();
        }
    });

    document.getElementById('wrap').addEventListener('input', function () { refreshVisibility(); });
    document.getElementById('wrap').addEventListener('change', function () { refreshVisibility(); });

    refreshVisibility();

    document.getElementById('sf').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var alertEl = document.getElementById('alert');
        alertEl.hidden = true;

        var answers = {};
        qs.forEach(function (q, i) {
            if (!visible(q, i)) return;
            var v = getAnswer(q.id);
            if (v !== null && v !== undefined && v !== '') answers[String(q.id)] = v;
        });

        for (var j = 0; j < qs.length; j++) {
            var qq = qs[j];
            if (!qq.required || !visible(qq, j)) continue;
            var vv = getAnswer(qq.id);
            if (vv === null || vv === undefined || vv === '') {
                alertEl.textContent = 'Please answer all required questions.';
                alertEl.hidden = false;
                return;
            }
        }

        var elapsed = Date.now() - startTime;
        if (elapsed < 2000) {
            alertEl.textContent = 'Please wait a moment before submitting.';
            alertEl.hidden = false;
            return;
        }

        var payload = {
            id: cfg.surveyUuid,
            answers: answers,
            first_name: (document.getElementById('fn') && document.getElementById('fn').value) || '',
            last_name: (document.getElementById('ln') && document.getElementById('ln').value) || '',
            email: (document.getElementById('em') && document.getElementById('em').value) || '',
            start_time: startTime
        };
        if (cfg.agentId) payload.aid = cfg.agentId;

        var btn = document.getElementById('sub');
        btn.disabled = true;
        document.getElementById('wrap').classList.add('loading');

        fetch(cfg.submitUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        }).then(function (r) {
            return r.text().then(function (t) {
                var d = {};
                try { d = t ? JSON.parse(t) : {}; } catch (e) { d = { message: t || 'Invalid response' }; }
                return { ok: r.ok, status: r.status, d: d };
            });
        })
        .then(function (res) {
            document.getElementById('wrap').classList.remove('loading');
            btn.disabled = false;
            if (res.ok && res.d && res.d.success) {
                document.getElementById('sf').hidden = true;
                document.getElementById('success').hidden = false;
                document.getElementById('success-msg').textContent = res.d.message || 'Thank you for your response.';
                if (res.d.redirect_url) {
                    var a = document.getElementById('success-link');
                    a.href = res.d.redirect_url;
                    a.hidden = false;
                    setTimeout(function () { window.top.location.href = res.d.redirect_url; }, 2000);
                }
            } else {
                var msg = (res.d && res.d.message) ? res.d.message : 'Submission failed. Please try again.';
                if (res.d && res.d.errors) {
                    var first = Object.values(res.d.errors)[0];
                    if (Array.isArray(first) && first[0]) msg = first[0];
                }
                alertEl.textContent = msg;
                alertEl.hidden = false;
            }
        }).catch(function () {
            document.getElementById('wrap').classList.remove('loading');
            btn.disabled = false;
            alertEl.textContent = 'Network error. Please try again.';
            alertEl.hidden = false;
        });
    });
})();
    </script>
</body>
</html>
