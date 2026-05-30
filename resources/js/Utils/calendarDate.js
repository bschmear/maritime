/**
 * Calendar dates (DB `date` columns as YYYY-MM-DD) are not instants in time.
 * Parsing them with `new Date(iso)` shifts the day in US timezones.
 */

export function parseCalendarYmdToLocalDate(value) {
    if (value == null || value === '') {
        return null;
    }
    const s = String(value).trim();
    const ymd = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (ymd) {
        const y = parseInt(ymd[1], 10);
        const m = parseInt(ymd[2], 10) - 1;
        const day = parseInt(ymd[3], 10);
        const local = new Date(y, m, day);
        return Number.isNaN(local.getTime()) ? null : local;
    }
    const d = new Date(s);
    return Number.isNaN(d.getTime()) ? null : d;
}

export function startOfLocalToday() {
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

/** Whole calendar days from today (local) to due date; negative = overdue. */
export function calendarDaysFromToday(value) {
    const due = parseCalendarYmdToLocalDate(value);
    if (!due) {
        return null;
    }
    const dueDay = new Date(due.getFullYear(), due.getMonth(), due.getDate());
    const today = startOfLocalToday();
    return Math.round((dueDay - today) / (1000 * 60 * 60 * 24));
}

export function formatCalendarDateShort(value, localeOptions) {
    const d = parseCalendarYmdToLocalDate(value);
    if (!d) {
        return '';
    }
    return d.toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        ...localeOptions,
    });
}

/** Matches dashboard Action center past-due badge. */
export const PAST_DUE_BADGE_CLASS =
    'rounded-md px-2 py-0.5 text-xs font-medium bg-red-200 text-red-900 dark:bg-red-900 dark:text-white';

/** due_time column is "HH:MM:SS" or "HH:MM" — only when has_due_time is true. */
export function formatDueTimeLabel(dueTime) {
    if (dueTime == null || dueTime === '') {
        return null;
    }
    const parts = String(dueTime).trim().split(':');
    const h = parseInt(parts[0], 10);
    const min = parseInt(parts[1] ?? '0', 10);
    if (Number.isNaN(h) || Number.isNaN(min)) {
        return null;
    }
    const ref = new Date(2000, 0, 1, h, min, 0);
    return ref.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
}

/**
 * @param {{ has_due_time?: boolean|number, due_time?: string|null }} task
 */
export function formatPastDueLabel(task, baseLabel = 'Past due') {
    if (!task?.has_due_time || !task?.due_time) {
        return baseLabel;
    }
    const timeLabel = formatDueTimeLabel(task.due_time);
    return timeLabel ? `${baseLabel} · ${timeLabel}` : baseLabel;
}

/**
 * Whether an incomplete task is past due (local calendar day + optional due time).
 *
 * @param {{ due_date?: string|null, has_due_time?: boolean|number, due_time?: string|null }} task
 */
export function isTaskPastDue(task) {
    if (!task?.due_date) {
        return false;
    }

    const diffDays = calendarDaysFromToday(task.due_date);
    if (diffDays === null) {
        return false;
    }

    if (diffDays < 0) {
        return true;
    }

    if (diffDays > 0) {
        return false;
    }

    if (!task.has_due_time || !task.due_time) {
        return false;
    }

    const parts = String(task.due_time).trim().split(':');
    const h = parseInt(parts[0], 10);
    const min = parseInt(parts[1] ?? '0', 10);
    if (Number.isNaN(h) || Number.isNaN(min)) {
        return false;
    }

    const now = new Date();
    const nowMinutes = now.getHours() * 60 + now.getMinutes();
    const dueMinutes = h * 60 + min;

    return nowMinutes > dueMinutes;
}
