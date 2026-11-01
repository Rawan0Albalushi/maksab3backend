<?php

namespace App\Exports;


use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;


class StoreOrderReportExport implements  FromView, ShouldAutoSize, WithStyles,WithColumnWidths ,WithHeadings, WithEvents
{

    use Exportable;
    protected $data;

    public function __construct($data) {
        $this->data = $data;
    }


    /**
     * Row count used to size the styled ranges.
     *
     * The controller streams its rows as a LazyCollection, where count() would re-run every
     * chunk query, so it supplies a precomputed count. A caller still passing an eager
     * Collection falls through to count() unchanged.
     */
    private function rowCount(): int
    {
        return (int) ($this->data['total_orders'] ?? $this->data['orders']->count());
    }

    public function view(): View
    {
        return view('file-exports.store-orders-report', [
            'data' => $this->data,
        ]);
    }

    public function columnWidths(): array
    {
        return [
        ];
    }

    public function styles(Worksheet $sheet) {
        $sheet->getStyle('A2:J4')->getFont()->setBold(true);
        $sheet->getStyle('A4:J4')->getFill()->applyFromArray([
            'fillType' => 'solid',
            'rotation' => 0,
            'color' => ['rgb' => '9F9F9F'],
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
        $sheet->getStyle('A1:C1')->applyFromArray($styleArray);
        return [
            'A1:J'.$this->rowCount() +4 => [
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
                $event->sheet->getStyle('A1:J1')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $event->sheet->getStyle('A2:C2')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $event->sheet->getStyle('A3:C3')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $event->sheet->getStyle('A4:C4')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $event->sheet->getStyle('A4:J'.$this->rowCount() +4)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $event->sheet->getStyle('D2:J2')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $event->sheet->getStyle('D3:J3')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);


                    $event->sheet->mergeCells('A1:J1');
                    $event->sheet->mergeCells('A2:C2');
                    $event->sheet->mergeCells('D2:J2');
                    $event->sheet->mergeCells('A3:C3');
                    $event->sheet->mergeCells('D3:J3');

                    $event->sheet->getDefaultRowDimension()->setRowHeight(30);
                    $event->sheet->getRowDimension(1)->setRowHeight(50);
                    $event->sheet->getRowDimension(2)->setRowHeight(100);
                    $event->sheet->getRowDimension(3)->setRowHeight(80);
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

