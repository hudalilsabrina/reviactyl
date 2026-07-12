import http from '@/api/http';

export default (email: string, captchaToken?: string, captchaProvider?: string): Promise<string> => {
    // Build captcha response field based on provider
    const captchaField =
        captchaProvider === 'turnstile'
            ? { 'cf-turnstile-response': captchaToken }
            : { 'g-recaptcha-response': captchaToken };

    return new Promise((resolve, reject) => {
        http.post('/auth/password', { email, ...captchaField })
            .then((response) => resolve(response.data.status || ''))
            .catch(reject);
    });
};
