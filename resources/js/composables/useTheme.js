import { ref } from 'vue';

const theme = ref('auto');

export function useTheme() {
    const setTheme = (newTheme) => {
        theme.value = newTheme;
        localStorage.setItem('theme', newTheme);
        applyTheme(newTheme);
    };

    const applyTheme = (selectedTheme) => {
        const root = document.documentElement;
        
        if (selectedTheme === 'auto') {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (prefersDark) {
                root.classList.add('dark');
            } else {
                root.classList.remove('dark');
            }
        } else if (selectedTheme === 'dark') {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
    };

    const initTheme = () => {
        const savedTheme = localStorage.getItem('theme') || 'auto';
        theme.value = savedTheme;
        applyTheme(savedTheme);

        // Listen for system theme changes when in auto mode
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handleChange = () => {
            if (theme.value === 'auto') {
                applyTheme('auto');
            }
        };
        
        mediaQuery.addEventListener('change', handleChange);
        
        // Cleanup listener
        return () => mediaQuery.removeEventListener('change', handleChange);
    };

    return {
        theme,
        setTheme,
        initTheme,
    };
}

