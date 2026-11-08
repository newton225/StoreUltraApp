import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import LanguageSwitcher from '@/components/language-switcher';
import LegalLinks from '@/components/legal-links';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard, home, login } from '@/routes';

type Section = {
    heading: string;
    paragraphs?: string[];
    items?: string[];
    rows?: [string, string][];
};

type Props = {
    document: {
        key: string;
        title: string;
        version: string;
        sections: Section[];
    };
};

export default function LegalShow({ document }: Props) {
    const { t } = useTranslation();
    const { name, auth } = usePage().props;
    const signedIn = Boolean(auth?.user);

    return (
        <>
            <Head title={document.title} />

            <div className="min-h-dvh bg-background text-foreground">
                <header className="border-b">
                    <div className="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                        <Link
                            href={home()}
                            className="flex items-center gap-2 text-sm font-medium"
                        >
                            <AppLogoIcon className="size-6" />
                            {name}
                        </Link>
                        <LanguageSwitcher />
                    </div>
                </header>

                <main className="mx-auto max-w-3xl space-y-8 px-4 py-8 sm:px-6">
                    <div className="space-y-2">
                        <Link
                            href={signedIn ? dashboard() : login()}
                            className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            {t('navigation.legal.back')}
                        </Link>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {document.title}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('navigation.legal.version', {
                                version: document.version,
                            })}
                        </p>
                    </div>

                    {document.sections.map((section) => (
                        <section key={section.heading} className="space-y-3">
                            <h2 className="text-lg font-semibold">
                                {section.heading}
                            </h2>

                            {section.paragraphs?.map((paragraph) => (
                                <p
                                    key={paragraph}
                                    className="text-sm leading-relaxed"
                                >
                                    {paragraph}
                                </p>
                            ))}

                            {section.items && (
                                <ul className="list-disc space-y-1.5 pl-5 text-sm leading-relaxed">
                                    {section.items.map((item) => (
                                        <li key={item}>{item}</li>
                                    ))}
                                </ul>
                            )}

                            {section.rows && (
                                <dl className="divide-y divide-border rounded-lg border border-border text-sm">
                                    {section.rows.map(([label, value]) => (
                                        <div
                                            key={label}
                                            className="grid gap-1 px-4 py-2.5 sm:grid-cols-[14rem_1fr] sm:gap-4"
                                        >
                                            <dt className="font-medium">
                                                {label}
                                            </dt>
                                            <dd className="text-muted-foreground">
                                                {value}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                        </section>
                    ))}
                </main>

                <footer className="border-t">
                    <div className="mx-auto max-w-3xl px-4 py-6 sm:px-6">
                        <LegalLinks current={document.key} />
                    </div>
                </footer>
            </div>
        </>
    );
}
