<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    private const STATUSES = ['new', 'contacted', 'converted', 'archived'];

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $leads = Lead::when(in_array($status, self::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $counts = [
            'all'       => Lead::count(),
            'new'       => Lead::where('status', 'new')->count(),
            'contacted' => Lead::where('status', 'contacted')->count(),
            'converted' => Lead::where('status', 'converted')->count(),
            'archived'  => Lead::where('status', 'archived')->count(),
        ];

        return view('admin.leads.index', compact('leads', 'status', 'counts'));
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
        ]);

        $lead->update($data);

        return back()->with('status', 'Status do lead atualizado.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $lead->delete();

        return back()->with('status', 'Lead removido.');
    }
}
