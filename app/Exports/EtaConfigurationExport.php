<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EtaConfigurationExport implements FromView, ShouldAutoSize, WithEvents, WithHeadings, WithStyles
{
    use Exportable;

    private const LAST_COLUMN = 'J';

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('file-exports.eta-configuration', [
            'data' => $this->data,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $last = self::LAST_COLUMN;
        $lastRow = $this->data['data']->count() + 3;

        $sheet->getStyle("A2:{$last}2")->getFont()->setBold(true);
        $sheet->getStyle("A3:{$last}3")->getFont()->setBold(true);
        $sheet->getStyle("A3:{$last}3")->getFill()->applyFromArray([
            'fillType' => 'solid',
            'rotation' => 0,
            'color' => ['rgb' => '9F9F9F'],
        ]);

        $sheet->setShowGridlines(false);

        return [
            "A1:{$last}{$lastRow}" => [
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
            AfterSheet::class => function (AfterSheet $event) {
                $last = self::LAST_COLUMN;
                $lastRow = $this->data['data']->count() + 3;

                $event->sheet->getStyle("A1:{$last}1")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $event->sheet->getStyle('A2:B2')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $event->sheet->getStyle("C2:{$last}2")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $event->sheet->getStyle("A3:{$last}{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $event->sheet->mergeCells("A1:{$last}1");
                $event->sheet->mergeCells('A2:B2');
                $event->sheet->mergeCells("C2:{$last}2");

                $event->sheet->getDefaultRowDimension()->setRowHeight(30);
                $event->sheet->getRowDimension(1)->setRowHeight(50);
                $event->sheet->getRowDimension(2)->setRowHeight(40);
            },
        ];
    }

    public function headings(): array
    {
        return ['1'];
    }
}
