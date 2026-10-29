<?php
namespace App\Http\Controllers;
use App\Models\OrganizationMembership; use App\Services\DashboardService; use Illuminate\Http\Request;
class DashboardController extends Controller { public function __construct(private DashboardService $dashboard){} public function show(Request $r){$actor=$r->attributes->get('currentMembership'); if(!$actor){return $r->expectsJson()?response()->json(['data'=>[]]):view('dashboard',['data'=>[]]);} $data=$this->dashboard->for($actor); return $r->expectsJson()?response()->json(['data'=>$data]):view('dashboard',compact('data'));} }
