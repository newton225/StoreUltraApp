<?php

namespace App\Http\Controllers;

use App\Models\Kit;
use App\Models\Shop;
use App\Models\User;
use App\Models\Zone;
use App\Support\BusinessSettings;
use App\Support\DashboardReport;
use App\Support\MoneyFormatter;
use App\Support\ReportFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The indicators, the synthesis by shop, the profit and the alerts, for a period.
     */
    public function index(Request $request, DashboardReport $dashboard): Response
    {
        $user = $this->user($request);
        $filters = ReportFilters::fromRequest($request);

        return Inertia::render('dashboard', [
            'filters' => $filters,
            'report' => $dashboard->build($user, $filters),
            'zones' => $this->zones($user),
            'kits' => Kit::query()->orderBy('name_fr')->get()->map(fn (Kit $kit): array => ['id' => $kit->id, 'name' => $kit->name])->values(),
            'shopChoices' => $this->shopChoices($user),
            'exports' => $this->exports($user),
        ]);
    }

    /**
     * The same figures as a printable PDF, in the language of the user.
     */
    public function pdf(Request $request, DashboardReport $dashboard, BusinessSettings $settings): HttpResponse
    {
        $user = $this->user($request);
        $filters = ReportFilters::fromRequest($request);
        $locale = app()->getLocale();

        $pdf = Pdf::loadView('reports.synthesis', [
            'locale' => $locale,
            'companyName' => $settings->get('company_name'),
            'filters' => $filters,
            'report' => $dashboard->build($user, $filters),
            'money' => fn (int $amount): string => MoneyFormatter::format($amount, $locale),
            'zone' => $filters['zone'] === null ? null : Zone::find($filters['zone'])?->name,
            'kit' => $filters['kit'] === null ? null : Kit::find($filters['kit'])?->name,
            'shop' => $filters['shop'] === null ? null : Shop::find($filters['shop'])?->name,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4');

        return $pdf->download('synthese-'.$filters['from'].'-'.$filters['to'].'.pdf');
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_if($user === null, 403);

        return $user;
    }

    /**
     * The sub-prefectures the user can choose from.
     *
     * @return list<array{id: int, name: string}>
     */
    private function zones(User $user): array
    {
        $zones = $user->isRestrictedToZones() ? $user->zones() : Zone::query();
        $list = [];

        foreach ($zones->orderBy('zones.name')->get(['zones.id', 'zones.name']) as $zone) {
            $list[] = ['id' => $zone->id, 'name' => $zone->name];
        }

        return $list;
    }

    /**
     * The shops the synthesis can be narrowed to. A field agent sees nothing about shops.
     *
     * @return list<array{id: int, name: string}>
     */
    private function shopChoices(User $user): array
    {
        if (Gate::forUser($user)->denies('manage-orders')) {
            return [];
        }

        $list = [];

        foreach (Shop::query()->orderBy('name')->get(['id', 'name']) as $shop) {
            $list[] = ['id' => $shop->id, 'name' => $shop->name];
        }

        return $list;
    }

    /**
     * The exports the user is allowed to download.
     *
     * @return list<string>
     */
    private function exports(User $user): array
    {
        return array_values(array_filter(
            ExportController::REPORTS,
            fn (string $report): bool => ExportController::allows($user, $report),
        ));
    }
}
