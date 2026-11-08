import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowRight,
    Check,
    ChartNoAxesCombined,
    Files,
    FileText,
    History,
    Package,
    Receipt,
    Store,
    Users,
    Wallet,
} from 'lucide-react';
import AppearanceTabs from '@/components/appearance-tabs';
import AppLogoIcon from '@/components/app-logo-icon';
import LanguageSwitcher from '@/components/language-switcher';
import LegalLinks from '@/components/legal-links';
import Reveal from '@/components/reveal';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard, login } from '@/routes';

type Feature = { key: string; icon: LucideIcon };

const FEATURES: Feature[] = [
    { key: 'kits', icon: Package },
    { key: 'clients', icon: Users },
    { key: 'payments', icon: Wallet },
    { key: 'shops', icon: Store },
    { key: 'documents', icon: FileText },
    { key: 'profit', icon: Receipt },
    { key: 'dashboard', icon: ChartNoAxesCombined },
    { key: 'attachments', icon: Files },
    { key: 'audit', icon: History },
];

const STEPS = ['order', 'advance', 'supply', 'deliver'];
const PREVIEW_STEPS = ['proforma', 'advance', 'supply', 'delivery', 'settled'];
const ROLES = ['owner', 'employee', 'agent'];

function SectionTitle({
    title,
    subtitle,
}: {
    title: string;
    subtitle: string;
}) {
    return (
        <Reveal className="mx-auto mb-12 max-w-2xl text-center">
            <h2 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                {title}
            </h2>
            <p className="mt-3 text-muted-foreground">{subtitle}</p>
        </Reveal>
    );
}

export default function Welcome() {
    const { auth, name } = usePage().props;
    const { t } = useTranslation();
    const entry = auth.user ? dashboard() : login();
    const entryLabel = auth.user
        ? t('welcome.hero.cta_dashboard')
        : t('welcome.hero.cta_login');

    return (
        <>
            <Head title={name} />
            <div className="min-h-svh bg-background text-foreground">
                <header className="sticky top-0 z-30 border-b bg-background/80 backdrop-blur">
                    <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                        <a
                            href="#top"
                            className="flex items-center gap-2 font-semibold"
                        >
                            <AppLogoIcon className="size-8" />
                            {name}
                        </a>
                        <nav className="hidden items-center gap-6 text-sm text-muted-foreground md:flex">
                            <a
                                href="#how"
                                className="transition-colors hover:text-foreground"
                            >
                                {t('welcome.nav.how')}
                            </a>
                            <a
                                href="#features"
                                className="transition-colors hover:text-foreground"
                            >
                                {t('welcome.nav.features')}
                            </a>
                            <a
                                href="#roles"
                                className="transition-colors hover:text-foreground"
                            >
                                {t('welcome.nav.roles')}
                            </a>
                        </nav>
                        <div className="flex items-center gap-2">
                            <LanguageSwitcher />
                            <Button asChild size="sm">
                                <Link href={entry}>{entryLabel}</Link>
                            </Button>
                        </div>
                    </div>
                </header>

                <main id="top">
                    <section className="relative overflow-hidden">
                        <div
                            aria-hidden
                            className="pointer-events-none absolute inset-x-0 -top-40 -z-10 h-[32rem] bg-[radial-gradient(ellipse_at_center,var(--color-primary)_0%,transparent_65%)] opacity-10"
                        />
                        <div className="mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:py-24">
                            <div className="fade-up">
                                <span className="inline-flex items-center rounded-full border bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
                                    {t('welcome.hero.badge')}
                                </span>
                                <h1 className="mt-5 text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                                    {t('welcome.hero.title')}
                                </h1>
                                <p className="mt-5 max-w-xl text-lg text-muted-foreground">
                                    {t('welcome.hero.subtitle')}
                                </p>
                                <div className="mt-8 flex flex-wrap gap-3">
                                    <Button asChild size="lg" className="group">
                                        <Link href={entry}>
                                            {entryLabel}
                                            <ArrowRight className="transition-transform duration-200 motion-safe:group-hover:translate-x-1" />
                                        </Link>
                                    </Button>
                                    <Button asChild size="lg" variant="outline">
                                        <a href="#how">
                                            {t('welcome.hero.cta_discover')}
                                        </a>
                                    </Button>
                                </div>
                            </div>

                            <div
                                className="fade-up rounded-2xl border bg-card p-6 shadow-sm"
                                style={{ animationDelay: '120ms' }}
                            >
                                <div className="flex items-center justify-between">
                                    <p className="font-medium">
                                        {t('welcome.preview.title')}
                                    </p>
                                    <span className="rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground">
                                        {t('welcome.preview.week')}
                                    </span>
                                </div>
                                <ol className="mt-6 space-y-0">
                                    {PREVIEW_STEPS.map((step, index) => (
                                        <li
                                            key={step}
                                            className="relative flex gap-4 pb-6 last:pb-0"
                                        >
                                            {index <
                                                PREVIEW_STEPS.length - 1 && (
                                                <span
                                                    aria-hidden
                                                    className="absolute top-8 left-4 h-[calc(100%-2rem)] w-px bg-border"
                                                />
                                            )}
                                            <span className="relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                                <Check className="size-4" />
                                            </span>
                                            <span className="self-center text-sm font-medium">
                                                {t(
                                                    `welcome.preview.steps.${step}`,
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            </div>
                        </div>
                    </section>

                    <section
                        id="how"
                        className="scroll-mt-16 border-y bg-muted/40 py-20"
                    >
                        <div className="mx-auto max-w-6xl px-4 sm:px-6">
                            <SectionTitle
                                title={t('welcome.steps.title')}
                                subtitle={t('welcome.steps.subtitle')}
                            />
                            <ol className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                {STEPS.map((step, index) => (
                                    <Reveal
                                        as="li"
                                        key={step}
                                        delay={index * 90}
                                        className="rounded-xl border bg-card p-6"
                                    >
                                        <span className="flex size-9 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                            {index + 1}
                                        </span>
                                        <h3 className="mt-4 font-semibold">
                                            {t(
                                                `welcome.steps.items.${step}.title`,
                                            )}
                                        </h3>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {t(
                                                `welcome.steps.items.${step}.text`,
                                            )}
                                        </p>
                                    </Reveal>
                                ))}
                            </ol>
                        </div>
                    </section>

                    <section id="features" className="scroll-mt-16 py-20">
                        <div className="mx-auto max-w-6xl px-4 sm:px-6">
                            <SectionTitle
                                title={t('welcome.features.title')}
                                subtitle={t('welcome.features.subtitle')}
                            />
                            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                {FEATURES.map(({ key, icon: Icon }, index) => (
                                    <Reveal key={key} delay={(index % 3) * 90}>
                                        <div className="h-full rounded-xl border bg-card p-6 transition-[transform,border-color,box-shadow] duration-300 hover:border-primary/40 hover:shadow-md motion-safe:hover:-translate-y-1">
                                            <span className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <Icon className="size-5" />
                                            </span>
                                            <h3 className="mt-4 font-semibold">
                                                {t(
                                                    `welcome.features.items.${key}.title`,
                                                )}
                                            </h3>
                                            <p className="mt-2 text-sm text-muted-foreground">
                                                {t(
                                                    `welcome.features.items.${key}.text`,
                                                )}
                                            </p>
                                        </div>
                                    </Reveal>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section
                        id="roles"
                        className="scroll-mt-16 border-y bg-muted/40 py-20"
                    >
                        <div className="mx-auto max-w-6xl px-4 sm:px-6">
                            <SectionTitle
                                title={t('welcome.roles.title')}
                                subtitle={t('welcome.roles.subtitle')}
                            />
                            <div className="grid gap-6 md:grid-cols-3">
                                {ROLES.map((role, index) => (
                                    <Reveal
                                        key={role}
                                        delay={index * 90}
                                        className="rounded-xl border bg-card p-6"
                                    >
                                        <h3 className="font-semibold">
                                            {t(
                                                `welcome.roles.items.${role}.title`,
                                            )}
                                        </h3>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {t(
                                                `welcome.roles.items.${role}.text`,
                                            )}
                                        </p>
                                    </Reveal>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="py-20">
                        <Reveal className="mx-auto max-w-3xl px-4 text-center sm:px-6">
                            <h2 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                                {t('welcome.cta.title')}
                            </h2>
                            <p className="mt-3 text-muted-foreground">
                                {t('welcome.cta.text')}
                            </p>
                            <Button asChild size="lg" className="group mt-8">
                                <Link href={entry}>
                                    {entryLabel}
                                    <ArrowRight className="transition-transform duration-200 motion-safe:group-hover:translate-x-1" />
                                </Link>
                            </Button>
                        </Reveal>
                    </section>
                </main>

                <footer className="border-t">
                    <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 py-6 text-sm text-muted-foreground sm:flex-row sm:px-6">
                        <p>
                            © {new Date().getFullYear()} {name}.{' '}
                            {t('welcome.footer.rights')}
                        </p>
                        <LegalLinks />
                        <AppearanceTabs />
                    </div>
                </footer>
            </div>
        </>
    );
}
