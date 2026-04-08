<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CompanyModuleCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  int  $module_id
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, $module_id)
    {
        if (!canViewModule($module_id)) {
            return redirect('/dashboard')->with('error', 'Module Access Denied for your Company!');
        }

        return $next($request);
    }
}
