<?php
namespace App\Services;
use App\Models\Report;
class ReportPdfService
{
    public function render(Report $report): string
    {
        $version=$report->versions()->latest('version')->first(); $snapshot=$version?->snapshot??[]; $lines=['LORINE REPORT','Report #'.$report->id,'Period: '.$report->period_start->toDateString().' to '.$report->period_end->toDateString(),'Status: '.$report->status]; foreach($snapshot as $key=>$value){$text=is_scalar($value)?(string)$value:json_encode($value,JSON_UNESCAPED_SLASHES);$lines[]=str_replace('_',' ',ucwords((string)$key,'_')).': '.$text;}
        $stream="BT\n/F1 16 Tf\n50 790 Td\n";foreach(array_slice($lines,0,42) as $index=>$line){$escaped=$this->escape($line);if($index>0)$stream.="0 -18 Td\n";$stream.="($escaped) Tj\n";}$stream.="ET\n";
        $objects=["<< /Type /Catalog /Pages 2 0 R >>","<< /Type /Pages /Kids [3 0 R] /Count 1 >>","<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>","<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>","<< /Length ".strlen($stream)." >>\nstream\n".$stream."endstream"];
        $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[];foreach($objects as $number=>$object){$offsets[] = strlen($pdf);$pdf.=($number+1)." 0 obj\n".$object."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";foreach($offsets as $offset)$pdf.=sprintf('%010d 00000 n ',$offset)."\n";$pdf.="trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";return $pdf;
    }
    private function escape(string $value):string{return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],mb_substr(preg_replace('/[^\x20-\x7E]/','?',$value),0,180));}
}
