<?php
namespace App\Http\Middleware;
use Closure; use Illuminate\Http\Request;
class RequirePrivilegedTwoFactor { public function handle(Request $request,Closure $next){ if(!app()->environment('production'))return $next($request);$m=$request->attributes->get('currentMembership');if($m&&collect(['organization.manage','roles.manage','payroll.manage','payroll.approve'])->contains(fn($ability)=>app(\App\Services\WorkspaceAccess::class)->scopes($m,$ability)!==[])&&!$request->user()->two_factor_confirmed_at)abort(403,'Privileged accounts require two-factor authentication.');return $next($request); } }
