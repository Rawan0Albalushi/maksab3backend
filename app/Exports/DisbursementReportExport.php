<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
class DisbursementReportExport implements  FromView, ShouldAutoSize, WithStyles ,WithHeadings, WithEvents
{

    use Exportable;
    protected $data;

    public function __construct($data) {
        $this->data = $data;
    }

    private function rowCount(): int
    {
        return (int) ($this->data['disbursements_count'] ?? $this->data['disbursements']->count());
    }

    public function view(): View
    {
        return view('file-exports.disbursement-report', [
            'data' => $this->data,
        ]);
    }


    public function styles(Worksheet $sheet) {
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A4:F4')->getFill()->applyFromArray([
            'fillType' => 'solid',
            'rotation' => 0,
            'color' => ['rgb' => 'dae0e0'],
        ]);

        $sheet->setShowGridlines(false);
        $styleArray = [
            'borders' => [
                'bottom' => ['borderStyle' => 'hair', 'color' => ['argb' => 'FFFF0000']],
                'top' => ['borderStyle' => 'hair', 'color' => ['argb' => 'FFFF0000']],
                'right' => ['borderStyle' => 'hair', 'color' => ['argb' => 'FF00FF00']],
                'left' => ['borderStyle' => 'hair', 'color' => ['argb' => 'FF00FF00']],
            ],
            'fillType' => 'solid',
            'rotation' => 0,
        ];
        $sheet->getStyle('A1:F1')->applyFromArray($styleArray);
        return [
            'A1:F'.$this->rowCount() +4 => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => '000000'],
                    ],
                ],
            ],
        ];

    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $event->sheet->getStyle('A1:F1')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $event->sheet->getStyle('A2:D'.$this->rowCount() +4)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);


                    $event->sheet->mergeCells('A1:F1');
                    $event->sheet->mergeCells('A2:B2');
                    $event->sheet->mergeCells('C2:F2');
                    $event->sheet->mergeCells('A3:B3');
                    $event->sheet->mergeCells('C3:D3');
                    $event->sheet->mergeCells('E3:F3');


                    $event->sheet->getDefaultRowDimension()->setRowHeight(30);
                    $event->sheet->getRowDimension(1)->setRowHeight(50);
                    $event->sheet->getRowDimension(2)->setRowHeight(100);
                },
        ];
    }
    public function headings(): array
    {
        return [
            '1'
        ];
    }
}

