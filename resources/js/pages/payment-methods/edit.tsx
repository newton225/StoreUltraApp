import { Head, setLayoutProps } from '@inertiajs/react';
import {
    edit,
    index,
    update,
} from '@/actions/App/Http/Controllers/PaymentMethodController';
import Heading from '@/components/heading';
import type { EditableMethod } from '@/components/payment-method-form';
import PaymentMethodForm from '@/components/payment-method-form';
import { useTranslation } from '@/hooks/use-translation';

export default function EditPaymentMethod({
    method,
}: {
    method: EditableMethod;
}) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('payments.methods.title'), href: index() },
            { title: method.label_fr, href: edit(method.id) },
        ],
    });

    return (
        <>
            <Head title={t('payments.methods.edit_title')} />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading title={t('payments.methods.edit_title')} />
                <PaymentMethodForm
                    formProps={update.form(method.id)}
                    method={method}
                />
            </div>
        </>
    );
}
