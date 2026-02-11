import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useTimezone() {
    const page = usePage();

    // Timezone conversion functions
    const convertUTCToTimezone = (utcDate, timezone) => {
        if (!utcDate) return null;
        const date = new Date(utcDate + (utcDate.includes('T') ? '' : 'T00:00:00'));
        return new Date(date.toLocaleString('en-US', { timeZone: timezone }));
    };

    const convertTimezoneToUTC = (localDate, timezone) => {
        if (!localDate) return null;
        // Create date in the target timezone
        const date = new Date(localDate);
        const utcDate = new Date(date.toLocaleString('en-US', { timeZone: 'UTC' }));
        return utcDate;
    };

    // Timezone labels mapping from props with fallback
    const timezoneLabels = computed(() => {
        const timezones = page.props.timezones || [];
        if (timezones && timezones.length > 0) {
            const labels = {};
            timezones.forEach(tz => {
                labels[tz.id] = tz.name;
            });
            return labels;
        }
        // Fallback mapping if timezones prop is not provided
        return {
            'America/New_York': 'Eastern - US & Canada',
            'America/Chicago': 'Central - US & Canada',
            'America/Denver': 'Mountain - US & Canada',
            'America/Los_Angeles': 'Pacific - US & Canada',
            'America/Anchorage': 'Alaska',
            'Pacific/Honolulu': 'Hawaii',
            'Etc/GMT': 'GMT',
            'UTC': 'UTC',
            'Europe/London': 'London',
            'Europe/Paris': 'Paris',
            'Europe/Berlin': 'Berlin',
            'Asia/Tokyo': 'Tokyo',
            'Australia/Sydney': 'Sydney',
        };
    });

    // Get account settings timezone
    const accountTimezone = computed(() => page.props.account?.timezone || 'UTC');
    const accountTimezoneLabel = computed(() => timezoneLabels.value[accountTimezone.value] || accountTimezone.value);

    return {
        convertUTCToTimezone,
        convertTimezoneToUTC,
        timezoneLabels,
        accountTimezone,
        accountTimezoneLabel,
    };
}