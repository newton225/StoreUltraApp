import { Link, usePage } from '@inertiajs/react';
import {
    Boxes,
    ClipboardList,
    CreditCard,
    Paperclip,
    Receipt,
    TrendingUp,
    Tags,
    FileText,
    Truck,
    ShoppingCart,
    Landmark,
    Wallet,
    Banknote,
    Building2,
    Footprints,
    HeartPulse,
    History,
    LayoutGrid,
    UserRound,
    MapPin,
    Package,
    ShieldCheck,
    SlidersHorizontal,
    Store,
    Users,
} from 'lucide-react';
import { index as clientsIndex } from '@/actions/App/Http/Controllers/ClientController';
import { index as kitsIndex } from '@/actions/App/Http/Controllers/KitController';
import { index as attachmentsIndex } from '@/actions/App/Http/Controllers/AttachmentController';
import { index as attachmentTypesIndex } from '@/actions/App/Http/Controllers/AttachmentTypeController';
import { index as cashIndex } from '@/actions/App/Http/Controllers/CashMovementController';
import { index as expensesIndex } from '@/actions/App/Http/Controllers/ExpenseController';
import { index as expenseTypesIndex } from '@/actions/App/Http/Controllers/ExpenseTypeController';
import { index as profitsIndex } from '@/actions/App/Http/Controllers/ProfitController';
import { index as documentsIndex } from '@/actions/App/Http/Controllers/DocumentController';
import { index as deliveriesIndex } from '@/actions/App/Http/Controllers/DeliveryController';
import { index as accessIndex } from '@/actions/App/Http/Controllers/AccessLogController';
import { index as auditIndex } from '@/actions/App/Http/Controllers/AuditLogController';
import { index as healthIndex } from '@/actions/App/Http/Controllers/HealthController';
import { index as ordersIndex } from '@/actions/App/Http/Controllers/OrderController';
import {
    arrears as arrearsIndex,
    index as paymentsIndex,
} from '@/actions/App/Http/Controllers/PaymentController';
import { index as paymentMethodsIndex } from '@/actions/App/Http/Controllers/PaymentMethodController';
import { index as purchasesIndex } from '@/actions/App/Http/Controllers/PurchaseController';
import { index as productsIndex } from '@/actions/App/Http/Controllers/ProductController';
import { index as profilesIndex } from '@/actions/App/Http/Controllers/ProfileController';
import { index as shopsIndex } from '@/actions/App/Http/Controllers/ShopController';
import { index as usersIndex } from '@/actions/App/Http/Controllers/UserController';
import { index as zonesIndex } from '@/actions/App/Http/Controllers/ZoneController';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import { edit as companyEdit } from '@/routes/company';
import { edit as parametersEdit } from '@/routes/parameters';
import type { NavGroup } from '@/types';

export function AppSidebar() {
    const { t, locale } = useTranslation();
    const { auth, appVersion } = usePage().props;
    const can = (permission: string): boolean =>
        auth.permissions.includes(permission);
    const canClients = can('manage_clients');
    const canDeliveries = can('manage_deliveries');
    const canPayments = can('manage_payments');
    const canDocuments = can('manage_documents');
    const canPurchases = can('manage_purchases');
    const canExpenses = can('manage_expenses');
    const canCash = can('manage_cash');
    const canCatalog = can('manage_catalog');
    const isOwner = auth.user?.role === 'owner';

    const navGroups: NavGroup[] = [
        {
            tone: 'dashboard',
            items: [
                {
                    title: t('navigation.dashboard'),
                    href: dashboard(),
                    icon: LayoutGrid,
                },
            ],
        },
        {
            label: t('navigation.groups.sales'),
            tone: 'sales',
            items: [
                ...(!canClients
                    ? []
                    : [
                          {
                              title: t('navigation.clients'),
                              href: clientsIndex(),
                              icon: UserRound,
                          },
                      ]),
                {
                    title: t('navigation.orders'),
                    href: ordersIndex(),
                    icon: ClipboardList,
                },
                ...(!canDeliveries
                    ? []
                    : [
                          {
                              title: t('navigation.deliveries'),
                              href: deliveriesIndex(),
                              icon: Truck,
                          },
                      ]),
                ...(!canPayments
                    ? []
                    : [
                          {
                              title: t('navigation.payments'),
                              href: paymentsIndex(),
                              icon: Wallet,
                          },
                          {
                              title: t('navigation.arrears'),
                              href: arrearsIndex(),
                              icon: CreditCard,
                          },
                      ]),
                ...(!canDocuments
                    ? []
                    : [
                          {
                              title: t('navigation.documents'),
                              href: documentsIndex(),
                              icon: FileText,
                          },
                      ]),
            ],
        },
        {
            label: t('navigation.groups.supply'),
            tone: 'supply',
            items: [
                ...(canPurchases
                    ? [
                          {
                              title: t('navigation.purchases'),
                              href: purchasesIndex(),
                              icon: ShoppingCart,
                          },
                      ]
                    : []),
                ...(canCatalog
                    ? [
                          {
                              title: t('navigation.shops'),
                              href: shopsIndex(),
                              icon: Store,
                          },
                          {
                              title: t('navigation.products'),
                              href: productsIndex(),
                              icon: Package,
                          },
                          {
                              title: t('navigation.kits'),
                              href: kitsIndex(),
                              icon: Boxes,
                          },
                      ]
                    : []),
            ],
        },
        {
            label: t('navigation.groups.finance'),
            tone: 'finance',
            items: [
                ...(!canCash
                    ? []
                    : [
                          {
                              title: t('navigation.cash'),
                              href: cashIndex(),
                              icon: Banknote,
                          },
                      ]),
                ...(!canExpenses
                    ? []
                    : [
                          {
                              title: t('navigation.expenses'),
                              href: expensesIndex(),
                              icon: Receipt,
                          },
                      ]),
                ...(auth.canViewProfit
                    ? [
                          {
                              title: t('navigation.profits'),
                              href: profitsIndex(),
                              icon: TrendingUp,
                          },
                      ]
                    : []),
            ],
        },
        {
            label: t('navigation.groups.administration'),
            tone: 'admin',
            items: isOwner
                ? [
                      {
                          title: t('navigation.attachments'),
                          href: attachmentsIndex(),
                          icon: Paperclip,
                      },
                      {
                          title: t('navigation.users'),
                          href: usersIndex(),
                          icon: Users,
                      },
                      {
                          title: t('navigation.profiles'),
                          href: profilesIndex(),
                          icon: ShieldCheck,
                      },
                      {
                          title: t('navigation.audit'),
                          href: auditIndex(),
                          icon: History,
                      },
                      {
                          title: t('navigation.access'),
                          href: accessIndex(),
                          icon: Footprints,
                      },
                      {
                          title: t('navigation.health'),
                          href: healthIndex(),
                          icon: HeartPulse,
                      },
                  ]
                : [],
        },
        {
            label: t('navigation.groups.configuration'),
            tone: 'config',
            items: isOwner
                ? [
                      {
                          title: t('navigation.company'),
                          href: companyEdit(),
                          icon: Building2,
                      },
                      {
                          title: t('navigation.parameters'),
                          href: parametersEdit(),
                          icon: SlidersHorizontal,
                      },
                      {
                          title: t('navigation.zones'),
                          href: zonesIndex(),
                          icon: MapPin,
                      },
                      {
                          title: t('navigation.payment_methods'),
                          href: paymentMethodsIndex(),
                          icon: Landmark,
                      },
                      {
                          title: t('navigation.expense_types'),
                          href: expenseTypesIndex(),
                          icon: Tags,
                      },
                      {
                          title: t('navigation.attachment_types'),
                          href: attachmentTypesIndex(),
                          icon: Tags,
                      },
                  ]
                : [],
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent data-tour="menu">
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
                <p
                    className="px-2 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden"
                    data-test="app-version"
                >
                    {t('navigation.version', {
                        number: appVersion.number,
                        date: new Intl.DateTimeFormat(locale, {
                            dateStyle: 'long',
                        }).format(
                            new Date(`${appVersion.released_on}T00:00:00`),
                        ),
                    })}
                </p>
            </SidebarFooter>
        </Sidebar>
    );
}
