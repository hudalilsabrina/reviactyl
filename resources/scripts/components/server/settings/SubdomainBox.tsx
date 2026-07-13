import { useEffect, useState } from 'react';
import { ServerContext } from '@/state/server';
import TitledGreyBox from '@/reviactyl/elements/TitledGreyBox';
import { Button } from '@/reviactyl/elements/button/index';
import Field from '@/reviactyl/elements/Field';
import SpinnerOverlay from '@/reviactyl/elements/SpinnerOverlay';
import { httpErrorToHuman } from '@/api/http';
import { useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import ConfirmationModal from '@/reviactyl/elements/ConfirmationModal';
import CopyOnClick from '@/reviactyl/elements/CopyOnClick';
import Input from '@/reviactyl/elements/Input';
import Label from '@/reviactyl/elements/Label';
import Select from '@/reviactyl/elements/Select';
import getSubdomains, { ServerSubdomain } from '@/api/server/subdomain/getSubdomains';
import createSubdomain from '@/api/server/subdomain/createSubdomain';
import deleteSubdomain from '@/api/server/subdomain/deleteSubdomain';
import tw from 'twin.macro';
import { Form, Formik, FormikHelpers } from 'formik';
import { object, string } from 'yup';
import { useTranslation } from 'react-i18next';

interface SubdomainFormValues {
    subdomain: string;
    domain: string;
}

const subdomainSchema = object().shape({
    subdomain: string()
        .required()
        .min(3)
        .max(63)
        .matches(/^[a-z0-9][a-z0-9-]*[a-z0-9]$/, 'Only lowercase letters, numbers, and hyphens allowed'),
    domain: string().required(),
});

const SubdomainBox = () => {
    const { t } = useTranslation('server/settings');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const setServer = ServerContext.useStoreActions((actions) => actions.server.setServer);
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const { addError, clearFlashes } = useStoreActions(
        (actions: import('easy-peasy').Actions<ApplicationStore>) => actions.flashes
    );

    const [subdomains, setSubdomains] = useState<ServerSubdomain[]>([]);
    const [loading, setLoading] = useState(true);
    const [showDelete, setShowDelete] = useState(false);
    const [meta, setMeta] = useState<{
        maxPerServer: number;
        customCount: number;
        availableDomains: string[];
    }>({ maxPerServer: 1, customCount: 0, availableDomains: [] });

    useEffect(() => {
        getSubdomains(uuid)
            .then((data) => {
                setSubdomains(data.subdomains);
                setMeta(data.meta);
            })
            .catch((error) => {
                addError({ key: 'settings', message: httpErrorToHuman(error) });
            })
            .then(() => setLoading(false));
    }, [uuid]);

    const hasSubdomain = subdomains.length > 0;
    const canCreate = meta.maxPerServer === 0 || meta.customCount < meta.maxPerServer;
    const hasMultipleDomains = meta.availableDomains.length > 1;

    const handleCreate = (values: SubdomainFormValues, { setSubmitting }: FormikHelpers<SubdomainFormValues>) => {
        clearFlashes('settings');
        createSubdomain(uuid, values.subdomain, values.domain)
            .then((created) => {
                setSubdomains([...subdomains, created]);
                setServer({ ...server, subdomain: created.fqdn });
                setMeta((prev) => ({ ...prev, customCount: prev.customCount + 1 }));
            })
            .catch((error) => {
                addError({ key: 'settings', message: httpErrorToHuman(error) });
            })
            .then(() => setSubmitting(false));
    };

    const handleDelete = (subdomainId: number) => {
        clearFlashes('settings');
        deleteSubdomain(uuid, subdomainId)
            .then(() => {
                const remaining = subdomains.filter((s) => s.id !== subdomainId);
                setSubdomains(remaining);
                setServer({ ...server, subdomain: remaining[0]?.fqdn ?? null });
                setShowDelete(false);
            })
            .catch((error) => {
                addError({ key: 'settings', message: httpErrorToHuman(error) });
            });
    };

    if (loading) {
        return (
            <TitledGreyBox title={t('subdomain.title')} css={tw`relative`}>
                <SpinnerOverlay visible />
            </TitledGreyBox>
        );
    }

    if (!hasSubdomain && !canCreate) {
        return (
            <TitledGreyBox title={t('subdomain.title')} css={tw`relative`}>
                <p css={tw`text-sm text-gray-400`}>{t('subdomain.no-subdomain')}</p>
                <p css={tw`text-sm text-gray-500 mt-2`}>{t('subdomain.quota_reached')}</p>
            </TitledGreyBox>
        );
    }

    return (
        <TitledGreyBox title={t('subdomain.title')} css={tw`relative`}>
            {/* Existing subdomains */}
            {subdomains.length > 0 && (
                <div css={tw`mb-4 space-y-3`}>
                    {subdomains.map((sub) => (
                        <div key={sub.id} css={tw`flex items-center gap-3`}>
                            <div css={tw`flex-1`}>
                                <CopyOnClick text={sub.fqdn}>
                                    <Input type={'text'} value={sub.fqdn} readOnly />
                                </CopyOnClick>
                                <p css={tw`text-xs text-gray-500 mt-1`}>
                                    {sub.isAutoGenerated ? t('subdomain.auto-generated') : t('subdomain.custom')}
                                </p>
                            </div>
                            <button
                                type={'button'}
                                css={tw`text-sm p-2 text-gray-600 hover:text-red-600 transition-colors duration-150`}
                                onClick={() => setShowDelete(true)}
                            >
                                ×
                            </button>
                            {showDelete && (
                                <ConfirmationModal
                                    title={t('subdomain.confirm-delete')}
                                    buttonText={t('subdomain.delete')}
                                    visible={showDelete}
                                    onConfirmed={() => handleDelete(sub.id)}
                                    onModalDismissed={() => setShowDelete(false)}
                                >
                                    <p css={tw`text-sm`}>{sub.fqdn}</p>
                                </ConfirmationModal>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {/* Create new subdomain */}
            {canCreate && (
                <Formik
                    onSubmit={handleCreate}
                    initialValues={{
                        subdomain: '',
                        domain: meta.availableDomains[0] || '',
                    }}
                    validationSchema={subdomainSchema}
                >
                    {({ values, setFieldValue }) => (
                        <Form css={tw`mb-0`}>
                            {hasMultipleDomains && (
                                <div css={tw`mb-4`}>
                                    <Label>{t('subdomain.select_domain')}</Label>
                                    <Select
                                        value={values.domain}
                                        onChange={(e: React.ChangeEvent<HTMLSelectElement>) =>
                                            setFieldValue('domain', e.target.value)
                                        }
                                    >
                                        {meta.availableDomains.map((d) => (
                                            <option key={d} value={d}>
                                                {d}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                            )}
                            <Field id={'subdomain'} name={'subdomain'} label={t('subdomain.prefix')} type={'text'} />
                            {values.domain && (
                                <p css={tw`text-xs text-gray-500 mt-1`}>
                                    {t('subdomain.preview')}: {values.subdomain || '...'}.{values.domain}
                                </p>
                            )}
                            <div css={tw`mt-6 text-right`}>
                                <Button type={'submit'}>{t('subdomain.create')}</Button>
                            </div>
                        </Form>
                    )}
                </Formik>
            )}
        </TitledGreyBox>
    );
};

export default SubdomainBox;
