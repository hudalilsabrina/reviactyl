import http from '@/api/http';

export interface LoginResponse {
    complete: boolean;
    intended?: string;
    confirmationToken?: string;
}

export interface LoginData {
    username: string;
    password: string;
    captchaToken?: string | null;
    captchaProvider?: string;
}

export default ({ username, password, captchaToken, captchaProvider }: LoginData): Promise<LoginResponse> => {
    return new Promise((resolve, reject) => {
        // Build captcha response field based on provider
        const captchaField =
            captchaProvider === 'turnstile'
                ? { 'cf-turnstile-response': captchaToken }
                : { 'g-recaptcha-response': captchaToken };

        http.get('/sanctum/csrf-cookie')
            .then(() =>
                http.post('/auth/login', {
                    user: username,
                    password,
                    ...captchaField,
                })
            )
            .then((response) => {
                if (!(response.data instanceof Object)) {
                    return reject(new Error('An error occurred while processing the login request.'));
                }

                return resolve({
                    complete: response.data.data.complete,
                    intended: response.data.data.intended || undefined,
                    confirmationToken: response.data.data.confirmation_token || undefined,
                });
            })
            .catch(reject);
    });
};
