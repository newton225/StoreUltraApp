<?php

namespace App\Http\Controllers;

use App\Models\TourCompletion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TourController extends Controller
{
    /**
     * Notes that the member has seen the tour of a screen (finished or skipped), so it is not started by
     * itself again. Doing it twice changes nothing.
     */
    public function store(Request $request, string $tour): RedirectResponse
    {
        abort_unless(in_array($tour, TourCompletion::TOURS, true), 404);

        TourCompletion::query()->firstOrCreate(
            ['user_id' => $request->user()->id, 'tour' => $tour],
            ['completed_at' => now()],
        );

        return back();
    }
}
