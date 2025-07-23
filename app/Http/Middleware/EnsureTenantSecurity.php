<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSecurity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        // Check if user is active
        if (!$user->isActive()) {
            auth()->logout();
            return redirect()->route('login')->withErrors(['error' => 'Your account has been deactivated.']);
        }

        // Check if user's company is active
        if ($user->company && !$user->company->isActive()) {
            auth()->logout();
            return redirect()->route('login')->withErrors(['error' => 'Your company account has been suspended.']);
        }

        // Check if user's branch is active (if assigned to a branch)
        if ($user->branch && !$user->branch->isActive()) {
            auth()->logout();
            return redirect()->route('login')->withErrors(['error' => 'Your branch has been deactivated.']);
        }

        // Add tenant context to the request
        $this->addTenantContext($request, $user);

        return $next($request);
    }

    /**
     * Add tenant context to request
     */
    private function addTenantContext(Request $request, $user): void
    {
        // Add current tenant information to request
        $request->attributes->add([
            'tenant_company_id' => $user->company_id,
            'tenant_branch_id' => $user->branch_id,
            'tenant_user' => $user,
        ]);

        // Share with views
        view()->share('currentCompany', $user->company);
        view()->share('currentBranch', $user->branch);
        view()->share('currentUser', $user);
    }
}