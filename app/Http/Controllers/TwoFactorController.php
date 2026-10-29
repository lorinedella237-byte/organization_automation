<?php
namespace App\Http\Controllers;
use App\Services\TwoFactorService; use Illuminate\Http\Request;
class TwoFactorController extends Controller { public function __construct(private TwoFactorService $twoFactor){} public function begin(Request $r){return response()->json(['secret'=>$this->twoFactor->begin($r->user())]);} public function confirm(Request $r){$data=$r->validate(['code'=>['required','digits:6']]);abort_unless($this->twoFactor->confirm($r->user(),$data['code']),422,'The authentication code is invalid.');return response()->json(['status'=>'confirmed']);} }
