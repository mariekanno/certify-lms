<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

class CertificatePdfService
{
    public function generate(Certificate $certificate): void
    {
        $certificate->loadMissing(['user', 'certification']);

        $html = view('certificates.pdf', [
            'certificate' => $certificate,
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'ja',
            'format' => 'A4',
        ]);

        $mpdf->WriteHTML($html);

        Storage::disk('private')->put(
            $certificate->pdf_path,
            $mpdf->Output('', 'S'),
        );
    }
}
