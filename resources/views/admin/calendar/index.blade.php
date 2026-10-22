@extends('admin.layout')
@section('styles')
<link rel="stylesheet" href="{{ asset('assets/admin/vendor/fullcalendar-7.0.2/skeleton.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('assets/admin/vendor/fullcalendar-7.0.2/themes/classic/theme.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('assets/admin/vendor/fullcalendar-7.0.2/themes/classic/palette.css') }}?v={{ time() }}">    
<style type="text/css">
/* ------------------------------------------------------------------------
    FullCalendar 7 — CoolAdmin brand mapping.

    v7 replaced v6's semantic class names (.fc-daygrid-event, .fc-button-primary,
    …) with hashed, build-generated ones, so there is no stable class surface to
    override. Theming is done entirely through the theme's public custom
    properties — see vendor/fullcalendar-7.0.2/themes/classic/palette.css for the
    full list. We rebind them to CoolAdmin's --m-* design tokens so the calendar
    inherits the app palette (including the accent-preset switcher).
    ------------------------------------------------------------------------ */
body.app {
    /* Toolbar buttons — flat, surface-colored, accent when active */
    --fc-classic-button:              var(--m-surface);
    --fc-classic-button-border:       var(--m-border);
    --fc-classic-button-foreground:   var(--m-text);
    --fc-classic-button-strong:       var(--m-accent);
    --fc-classic-button-strong-border: var(--m-accent);
    --fc-classic-button-outline:      rgba(var(--m-accent-rgb), 0.4);

    /* Primary / events */
    --fc-classic-primary:             var(--m-accent);
    --fc-classic-primary-foreground:  #fff;

    /* Calendar content */
    --fc-classic-today:               var(--m-accent-soft);
    --fc-classic-highlight:           rgba(var(--m-accent-rgb), 0.12);
    --fc-classic-now:                 #dc2626;

    /* Neutrals — align the grid with card surfaces */
    --fc-classic-background:          var(--m-surface);
    --fc-classic-foreground:          var(--m-text);
    --fc-classic-muted-foreground:    var(--m-text-muted);
    --fc-classic-faint-foreground:    var(--m-text-faint);
    --fc-classic-border:              var(--m-border);
    --fc-classic-strong-border:       var(--m-border);
}

/* Typography hooks. These class names are injected via the calendar's
    public dayHeaderInnerClass / dayCellTopInnerClass options (see
    calendar.scripts.html) rather than by targeting v7's hashed internals. */
body.app .ca-fc-day-header {
    color: var(--m-text-faint);
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
body.app .ca-fc-day-number { font-size: 13px; }
</style>
@endsection

@section('content')
<div class="page-header">
            <div>
                <h1>Calendar</h1>
                <p class="subtitle">Schedule of meetings, deadlines, and events.</p>
            </div>
            <div class="page-header__actions">
                <button type="button" class="m-btn m-btn--ghost">
                    <i class="fa-solid fa-download" aria-hidden="true"></i>
                    Export
                </button>
                <button type="button" class="m-btn m-btn--primary" onclick="addNewEvent()">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Add event
                </button>
            </div>
        </div>

        <div class="row row-tight">
            <div class="col-lg-9">
                <section class="m-card">
                    <header class="m-card__header">
                        <div>
                            <h2 class="m-card__title">Event calendar</h2>
                            <p class="m-card__subtitle">Click any day or event to see details.</p>
                        </div>
                    </header>
                    <div id="calendar" class="calendar-container"></div>
                </section>
            </div>
            <div class="col-lg-3">
                <section class="m-card">
                    <header class="m-card__header">
                        <div>
                            <h2 class="m-card__title">Upcoming events</h2>
                            <p class="m-card__subtitle">Next two weeks.</p>
                        </div>
                    </header>
                    <ul class="deadline-list">
                        <li>
                            <div class="deadline-list__date"><strong>16</strong><span>May</span></div>
                            <div class="deadline-list__body">
                                <p class="deadline-list__title">Team meeting</p>
                                <span class="deadline-list__project">9:00 AM &middot; 60 min</span>
                            </div>
                        </li>
                        <li>
                            <div class="deadline-list__date"><strong>18</strong><span>May</span></div>
                            <div class="deadline-list__body">
                                <p class="deadline-list__title">Sprint planning</p>
                                <span class="deadline-list__project">10:00 AM &middot; 90 min</span>
                            </div>
                        </li>
                        <li>
                            <div class="deadline-list__date"><strong>20</strong><span>May</span></div>
                            <div class="deadline-list__body">
                                <p class="deadline-list__title">Project deadline</p>
                                <span class="deadline-list__project">All day</span>
                            </div>
                        </li>
                        <li>
                            <div class="deadline-list__date"><strong>22</strong><span>May</span></div>
                            <div class="deadline-list__body">
                                <p class="deadline-list__title">Client presentation</p>
                                <span class="deadline-list__project">2:00 PM &middot; 90 min</span>
                            </div>
                        </li>
                        <li>
                            <div class="deadline-list__date"><strong>24</strong><span>May</span></div>
                            <div class="deadline-list__body">
                                <p class="deadline-list__title">1:1 with John</p>
                                <span class="deadline-list__project">3:00 PM &middot; 30 min</span>
                            </div>
                        </li>
                        <li>
                            <div class="deadline-list__date"><strong>28</strong><span>May</span></div>
                            <div class="deadline-list__body">
                                <p class="deadline-list__title">Quarterly review</p>
                                <span class="deadline-list__project">11:00 AM &middot; 120 min</span>
                            </div>
                        </li>
                    </ul>
                </section>

                <section class="m-card" style="margin-top: 16px;">
                    <header class="m-card__header">
                        <div>
                            <h2 class="m-card__title">Event types</h2>
                        </div>
                    </header>
                    <ul class="legend-list">
                        <li><span class="legend-dot" style="background: var(--m-c1);"></span>Meetings</li>
                        <li><span class="legend-dot" style="background: var(--m-c2);"></span>Tasks</li>
                        <li><span class="legend-dot" style="background: var(--m-warning);"></span>Appointments</li>
                        <li><span class="legend-dot" style="background: var(--m-danger);"></span>Deadlines</li>
                        <li><span class="legend-dot" style="background: var(--m-c4);"></span>Presentations</li>
                    </ul>
                </section>
            </div>
        </div>
@endsection

@section('scripts')
<script>
/* ------------------------------------------------------------------------
    Date-relative event generator
    The calendar populates itself with events spread across the CURRENT
    month every time the page loads. So whether someone opens this template
    in May 2026 or March 2030, the demo always shows a populated month with
    past, present, and upcoming events.
    ------------------------------------------------------------------------ */

document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    const events = buildEvents(new Date());
    renderDeadlineList(events);

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
        },
        height: 'auto',
        events: events,
        eventDisplay: 'block',
        displayEventTime: true,
        dayMaxEvents: 3,
        moreLinkText: 'more',
        navLinks: true,
        nowIndicator: true,
        /* v7 generates hashed internal class names, so CoolAdmin styling
            hooks in through the public *Class options instead of guessing
            at them. These stay stable across FullCalendar patch releases. */
        dayHeaderInnerClass: 'ca-fc-day-header',
        dayCellTopInnerClass: 'ca-fc-day-number',
        eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' },
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            const e = info.event;
            const start = e.start ? e.start.toLocaleString(undefined, {
                weekday: 'short', month: 'short', day: 'numeric',
                hour: 'numeric', minute: '2-digit',
            }) : '';
            const end = e.end ? e.end.toLocaleString(undefined, {
                hour: 'numeric', minute: '2-digit',
            }) : '';
            /* Demo feedback — swap for your own detail view / modal. */
            if (window.toast) {
                window.toast.info(end ? start + ' – ' + end : start, e.title);
            }
        },
        /* v7 removed the `windowResize` option — the calendar now tracks
            container size itself, so no manual updateSize() call is needed. */
    });
    calendar.render();
    window.calendarInstance = calendar;
});

/* Generate ~20 events spread across the current month — recurring weekly
    items + a few date-relative anchors so the month always looks alive. */
function buildEvents(today) {
    const y = today.getFullYear();
    const m = today.getMonth();
    const ymd = (d) => d.getFullYear() + '-' +
        String(d.getMonth() + 1).padStart(2, '0') + '-' +
        String(d.getDate()).padStart(2, '0');
    const ymdt = (d, h, mm) => ymd(d) + 'T' +
        String(h).padStart(2, '0') + ':' +
        String(mm || 0).padStart(2, '0') + ':00';
    const at = (date, h, mm) => new Date(date.getFullYear(), date.getMonth(), date.getDate(), h, mm || 0);
    const sameMonth = (d) => d.getMonth() === m && d.getFullYear() === y;
    const offset = (n) => {
        const d = new Date(today);
        d.setDate(today.getDate() + n);
        return d;
    };
    const nthDow = (n, dow) => {
        const d = new Date(y, m, 1);
        let count = 0;
        while (d.getMonth() === m) {
            if (d.getDay() === dow) {
                count++;
                if (count === n) return new Date(d);
            }
            d.setDate(d.getDate() + 1);
        }
        return null;
    };
    const lastDow = (dow) => {
        const d = new Date(y, m + 1, 0);
        while (d.getDay() !== dow) d.setDate(d.getDate() - 1);
        return d;
    };

    const TAG_COLORS = {
        meeting:      { bg: '#eaf0fc', fg: '#4272d7' },  /* brand blue */
        task:         { bg: '#e0f3f1', fg: '#0d8780' },  /* teal */
        presentation: { bg: '#fff1e6', fg: '#d45f0a' },  /* warm orange */
        deadline:     { bg: '#fef2f2', fg: '#dc2626' },  /* red */
        personal:     { bg: '#fce7f3', fg: '#be185d' },  /* magenta */
    };

    /* FullCalendar 7 replaced the per-event backgroundColor/borderColor/
        textColor trio with `color` (fill) + `contrastColor` (text). */
    const e = (title, start, end, allDay, tag) => ({
        title,
        start,
        end,
        allDay,
        color: TAG_COLORS[tag].bg,
        contrastColor: TAG_COLORS[tag].fg,
        extendedProps: { type: tag },
    });

    const events = [];

    // 1. Recurring daily standup — every weekday, 9:00–9:15
    const cur = new Date(y, m, 1);
    while (cur.getMonth() === m) {
        const dow = cur.getDay();
        if (dow >= 1 && dow <= 5) {
            events.push(e('Team standup', ymdt(cur, 9, 0), ymdt(cur, 9, 15), false, 'meeting'));
        }
        cur.setDate(cur.getDate() + 1);
    }

    // 2. Sprint planning — first Monday, 10:00–11:30
    const sp = nthDow(1, 1);
    if (sp) events.push(e('Sprint planning', ymdt(sp, 10, 0), ymdt(sp, 11, 30), false, 'meeting'));

    // 3. Sprint retro — last Friday, 15:00–16:30
    const retro = lastDow(5);
    events.push(e('Sprint retro', ymdt(retro, 15, 0), ymdt(retro, 16, 30), false, 'meeting'));

    // 4. 1:1 with manager — every other Tuesday, 14:00
    const firstTue = nthDow(1, 2);
    if (firstTue) {
        for (let week = 0; week < 5; week += 2) {
            const d = new Date(firstTue);
            d.setDate(d.getDate() + week * 7);
            if (!sameMonth(d)) break;
            events.push(e('1:1 with manager', ymdt(d, 14, 0), ymdt(d, 14, 30), false, 'meeting'));
        }
    }

    // 5. Town hall — 3rd Thursday, 16:00–17:00
    const th = nthDow(3, 4);
    if (th) events.push(e('All-hands town hall', ymdt(th, 16, 0), ymdt(th, 17, 0), false, 'presentation'));

    // 6. Today (or next weekday) — Design review with stakeholders
    let t0 = new Date(today);
    if (t0.getDay() === 0) t0 = offset(1);
    if (t0.getDay() === 6) t0 = offset(2);
    if (sameMonth(t0)) {
        events.push(e('Design review with stakeholders', ymdt(t0, 11, 0), ymdt(t0, 12, 30), false, 'presentation'));
    }

    // 7. Today + 2 — Customer interviews (pricing)
    const t2 = offset(2);
    if (sameMonth(t2)) events.push(e('Customer interviews — pricing', ymdt(t2, 13, 0), ymdt(t2, 14, 0), false, 'task'));

    // 8. Today + 4 — Client demo (Acme)
    const t4 = offset(4);
    if (sameMonth(t4)) events.push(e('Client demo — Acme', ymdt(t4, 14, 0), ymdt(t4, 15, 30), false, 'presentation'));

    // 9. Today + 7 — Code freeze (all-day)
    const t7 = offset(7);
    if (sameMonth(t7)) events.push(e('Code freeze', ymd(t7), null, true, 'deadline'));

    // 10. Today + 12 — Q2 product launch (all-day)
    const t12 = offset(12);
    if (sameMonth(t12)) events.push(e('Q2 product launch', ymd(t12), null, true, 'presentation'));

    // 11. Today − 5 — Brand kickoff (past event)
    const tn5 = offset(-5);
    if (sameMonth(tn5)) events.push(e('Brand kickoff', ymdt(tn5, 10, 30), ymdt(tn5, 12, 0), false, 'meeting'));

    // 12. Today − 9 — Quarterly OKR review (past, all-day)
    const tn9 = offset(-9);
    if (sameMonth(tn9)) events.push(e('Quarterly OKR review', ymd(tn9), null, true, 'deadline'));

    // 13. Mid-month all-day team offsite (15th, or last day if month is short)
    const lastDayOfMonth = new Date(y, m + 1, 0).getDate();
    const offsiteDay = Math.min(15, lastDayOfMonth);
    const offsite = new Date(y, m, offsiteDay);
    events.push(e('Team strategy offsite', ymd(offsite), null, true, 'task'));

    // 14. Personal — gym (twice a week, Mon + Wed evenings, 18:30)
    const cursor = new Date(y, m, 1);
    while (cursor.getMonth() === m) {
        if (cursor.getDay() === 1 || cursor.getDay() === 3) {
            events.push(e('Gym', ymdt(cursor, 18, 30), ymdt(cursor, 19, 30), false, 'personal'));
        }
        cursor.setDate(cursor.getDate() + 1);
    }

    // 15. Friday afternoon coffee chat (every Friday, 16:30)
    const cof = new Date(y, m, 1);
    while (cof.getMonth() === m) {
        if (cof.getDay() === 5) {
            events.push(e('Coffee with the team', ymdt(cof, 16, 30), ymdt(cof, 17, 0), false, 'personal'));
        }
        cof.setDate(cof.getDate() + 1);
    }

    return events;
}

/* Populate the sidebar "Upcoming events" list with the next 6 events
    starting from today (or today onward), sorted ascending. */
function renderDeadlineList(allEvents) {
    const container = document.querySelector('.deadline-list');
    if (!container) return;

    const todayISO = new Date();
    todayISO.setHours(0, 0, 0, 0);
    const upcoming = allEvents
        .map((ev) => ({ ev, start: new Date(ev.start) }))
        .filter((x) => x.start >= todayISO)
        .sort((a, b) => a.start - b.start)
        .slice(0, 6);

    container.innerHTML = '';
    upcoming.forEach(({ ev, start }) => {
        const day = start.getDate();
        const month = start.toLocaleDateString(undefined, { month: 'short' });
        const meta = ev.allDay ? 'All day' : start.toLocaleTimeString(undefined, {
            hour: 'numeric', minute: '2-digit', hour12: true,
        });
        const li = document.createElement('li');
        const dateBox = document.createElement('div');
        dateBox.className = 'deadline-list__date';
        dateBox.innerHTML = '<strong>' + String(day).padStart(2, '0') + '</strong><span>' + month + '</span>';
        const body = document.createElement('div');
        body.className = 'deadline-list__body';
        const title = document.createElement('p');
        title.className = 'deadline-list__title';
        title.textContent = ev.title;
        const project = document.createElement('span');
        project.className = 'deadline-list__project';
        project.textContent = meta;
        body.append(title, project);
        li.append(dateBox, body);
        container.appendChild(li);
    });

    if (upcoming.length === 0) {
        container.innerHTML = '<li style="padding:14px 0; color: var(--m-text-faint); font-size: 13px;">No upcoming events.</li>';
    }
}

/* "Add event" button in the page header */
function addNewEvent() {
    const title = prompt('Event title:');
    if (!title || !window.calendarInstance) return;
    window.calendarInstance.addEvent({
        title,
        start: new Date(),
        color: '#eaf0fc',
        contrastColor: '#4272d7',
        extendedProps: { type: 'custom' },
    });
}
</script>
@endsection