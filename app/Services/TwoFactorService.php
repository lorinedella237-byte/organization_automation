<?php
namespace App\Services;
use App\Models\User; use Illuminate\Support\Facades\Crypt; use Illuminate\Support\Str;
class TwoFactorService
{
 public function begin(User $user):string { $secret=Str::upper(Str::random(32)); $secret=preg_replace('/[^A-Z2-7]/','A',$secret); $user->forceFill(['two_factor_secret'=>Crypt::encryptString($secret),'two_factor_confirmed_at'=>null])->save(); return $secret; }
 public function confirm(User $user,string $code):bool { if(!$user->two_factor_secret)return false; if(!$this->verify($this->secret($user),$code))return false; $user->forceFill(['two_factor_confirmed_at'=>now()])->save(); return true; }
 public function verifyFor(User $user,string $code):bool { return $user->two_factor_confirmed_at && $this->verify($this->secret($user),$code); }
 public function secret(User $user):string { return Crypt::decryptString($user->two_factor_secret); }
 private function verify(string $secret,string $code):bool { $code=preg_replace('/\D/','',$code); if(strlen($code)!==6)return false; $counter=intdiv(time(),30); for($offset=-1;$offset<=1;$offset++){ $binary=$this->base32($secret); $hash=hash_hmac('sha1',pack('N*',0,intval($counter+$offset)), $binary,true); $index=ord($hash[19])&15; $number=((ord($hash[$index])&127)<<24)|((ord($hash[$index+1])&255)<<16)|((ord($hash[$index+2])&255)<<8)|(ord($hash[$index+3])&255); if(hash_equals(str_pad((string)($number%1000000),6,'0',STR_PAD_LEFT),$code))return true; } return false; }
 private function base32(string $value):string { $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$value=rtrim(strtoupper($value),'=');$buffer=0;$bits=0;$out='';foreach(str_split($value) as $char){$pos=strpos($alphabet,$char);if($pos===false)continue;$buffer=($buffer<<5)|$pos;$bits+=5;if($bits>=8){$bits-=8;$out.=chr(($buffer>>$bits)&255);}}return $out; }
}
