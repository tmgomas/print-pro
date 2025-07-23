<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        // Check if user has any of the required roles
        $hasRole = false;
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                $hasRole = true;
                break;
            }
        }

        if (!$hasRole) {
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}


class CheckCompanyAccess
{
    /**
     * Handle an incoming request.
     * Ensures users can only access data from their own company
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        // Super Admin can access everything
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Check if route has company_id parameter
        $companyId = $request->route('company') ?? $request->route('company_id');
        
        if ($companyId && !$user->belongsToCompany(\App\Models\Company::find($companyId))) {
            abort(403, 'You can only access data from your own company.');
        }

        return $next($request);
    }
}


class CheckBranchAccess
{
    /**
     * Handle an incoming request.
     * Ensures users can only access data from authorized branches
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        // Super Admin can access everything
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Check if route has branch_id parameter
        $branchId = $request->route('branch') ?? $request->route('branch_id');
        
        if ($branchId) {
            $branch = \App\Models\Branch::find($branchId);
            
            if ($branch && !$user->canAccessBranch($branch)) {
                abort(403, 'You do not have access to this branch.');
            }
        }

        return $next($request);
    }
}