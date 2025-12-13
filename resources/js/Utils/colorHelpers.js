export const getPrimaryColor = () => {
    return document.documentElement.classList.contains("dark")
        ? "#1C64F2"
        : "#1A56DB";
};

export const getSecondaryColor = () => {
    return document.documentElement.classList.contains("dark")
        ? "#FF8A4C"
        : "#FF9963";
};

export const getTertiaryColor = () => {
    return document.documentElement.classList.contains("dark")
        ? "#16BDCA"
        : "#12ADBD";
};

export const getQuaternaryColor = () => {
    return document.documentElement.classList.contains("dark")
        ? "#9061F9"
        : "#7E3AF2";
};

export const getQuinaryColor = () => {
    return document.documentElement.classList.contains("dark")
        ? "#F559A5"
        : "#E74694";
};

export const getSuccessColor = () => {
    return document.documentElement.classList.contains("dark")
        ? "#31C48D"
        : "#0E9F6E";
};

export const getDangerColor = () => {
    return document.documentElement.classList.contains("dark")
        ? "#F05252"
        : "#E02424";
};

export const getColorClasses = (color) => {
    const colors = {
        blue: {
            bg: 'bg-blue-50 dark:bg-blue-900/20',
            icon: 'text-blue-600 dark:text-blue-400',
            hover: 'hover:bg-blue-50 dark:hover:bg-blue-900/30',
            border: 'group-hover:border-blue-300 dark:group-hover:border-blue-600',
        },
        green: {
            bg: 'bg-green-50 dark:bg-green-900/20',
            icon: 'text-green-600 dark:text-green-400',
            hover: 'hover:bg-green-50 dark:hover:bg-green-900/30',
            border: 'group-hover:border-green-300 dark:group-hover:border-green-600',
        },
        purple: {
            bg: 'bg-purple-50 dark:bg-purple-900/20',
            icon: 'text-purple-600 dark:text-purple-400',
            hover: 'hover:bg-purple-50 dark:hover:bg-purple-900/30',
            border: 'group-hover:border-purple-300 dark:group-hover:border-purple-600',
        },
        red: {
            bg: 'bg-red-50 dark:bg-red-900/20',
            icon: 'text-red-600 dark:text-red-400',
            hover: 'hover:bg-red-50 dark:hover:bg-red-900/30',
            border: 'group-hover:border-red-300 dark:group-hover:border-red-600',
        },
        yellow: {
            bg: 'bg-yellow-50 dark:bg-yellow-900/20',
            icon: 'text-yellow-600 dark:text-yellow-400',
            hover: 'hover:bg-yellow-50 dark:hover:bg-yellow-900/30',
            border: 'group-hover:border-yellow-300 dark:group-hover:border-yellow-600',
        },
        orange: {
            bg: 'bg-orange-50 dark:bg-orange-900/20',
            icon: 'text-orange-600 dark:text-orange-400',
            hover: 'hover:bg-orange-50 dark:hover:bg-orange-900/30',
            border: 'group-hover:border-orange-300 dark:group-hover:border-orange-600',
        },
        pink: {
            bg: 'bg-pink-50 dark:bg-pink-900/20',
            icon: 'text-pink-600 dark:text-pink-400',
            hover: 'hover:bg-pink-50 dark:hover:bg-pink-900/30',
            border: 'group-hover:border-pink-300 dark:group-hover:border-pink-600',
        },
        indigo: {
            bg: 'bg-indigo-50 dark:bg-indigo-900/20',
            icon: 'text-indigo-600 dark:text-indigo-400',
            hover: 'hover:bg-indigo-50 dark:hover:bg-indigo-900/30',
            border: 'group-hover:border-indigo-300 dark:group-hover:border-indigo-600',
        },
        teal: {
            bg: 'bg-teal-50 dark:bg-teal-900/20',
            icon: 'text-teal-600 dark:text-teal-400',
            hover: 'hover:bg-teal-50 dark:hover:bg-teal-900/30',
            border: 'group-hover:border-teal-300 dark:group-hover:border-teal-600',
        },
    };
    return colors[color] || colors.blue;
};
