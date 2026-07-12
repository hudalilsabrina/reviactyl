export const RTL_LANGUAGES = ['ar'];

export const applyDocumentDirection = (lang: string) => {
    document.documentElement.dir = RTL_LANGUAGES.includes(lang) ? 'rtl' : 'ltr';
    document.documentElement.lang = lang;
};
