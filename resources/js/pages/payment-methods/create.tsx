import { Head, setLayoutProps } from '@inertiajs/react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/PaymentMethodController';
import Heading from '@/components/heading';
import PaymentMethodForm from '@/components/payment-method-form';
import { useTranslation } from '@/hooks/use-translation';

export default function CreatePaymentMethod() {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('payments.methods.title'), href: index() },
            { title: t('payments.methods.create_title'), href: create() },
        ],
    });

    return (
        <>
            <Head title={t('payments.methods.create_title')} />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading title={t('payments.methods.create_title')} />
                <PaymentMethodForm formProps={store.form()} />
            </div>
        </>
    );
}
