<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCompanyRequest;
use App\Services\CompanyService;
use App\Repositories\CompanyRepository;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Add this line
class CompanyController extends Controller
{
    use AuthorizesRequests; // Add this line
    public function __construct(
        private CompanyService $companyService,
        private CompanyRepository $companyRepository
    ) {}

    /**
     * Display companies listing
     */
    public function index(Request $request): Response
    {
        $this->authorize('view companies');

        $filters = $request->only(['search', 'status']);
        $companies = $this->companyRepository->paginate(15, $filters);
        $stats = $this->companyRepository->getStats();

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'stats' => $stats,
            'filters' => $filters,
        ]);
    }

    /**
     * Show create company form
     */
    public function create(): Response
    {
        $this->authorize('create companies');

        return Inertia::render('Companies/Create');
    }

    /**
     * Store new company
     */
    public function store(CreateCompanyRequest $request): RedirectResponse
    {
        try {
            $company = $this->companyService->createCompany($request->validated());
            
            return redirect()->route('companies.show', $company->id)
                ->with('success', 'Company created successfully.');
                
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Company creation failed. Please try again.']);
        }
    }

    /**
     * Show company details
     */
    public function show(int $id): Response
    {
        $company = $this->companyRepository->findOrFail($id);
        
        $this->authorize('view companies');
        
        return Inertia::render('Companies/Show', [
            'company' => $company->load(['branches', 'users']),
        ]);
    }
}
