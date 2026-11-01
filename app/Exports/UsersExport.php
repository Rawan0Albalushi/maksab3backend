<?php

namespace App\Exports;

use App\Models\User;
use App\Models\DeliveryMan;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithProperties;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;


class UsersExport implements FromView, ShouldAutoSize, WithStyles,WithColumnWidths ,WithHeadings, WithEvents
{
    use Exportable;
    protected $data;

    public function __construct($data) {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('user-export', [
            'data' => $this->data
        ]);
    }



    public function columnWidths(): array
    {
        return [
            'C' => 45,
        ];
    }

    public function styles(Worksheet $sheet) {
        $sheet->getStyle('A2:H3')->getFont()->setBold(true);


        $sheet->getStyle('A3:H3')->getFill()->applyFromArray([
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
    'A1:H1000' => [
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
                $event->sheet->getStyle('A1:H1')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $event->sheet->getStyle('A2:C2')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $event->sheet->getStyle('A3:H300')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $event->sheet->getStyle('D2:H2')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);


                    $event->sheet->mergeCells('A1:H1');
                    $event->sheet->mergeCells('A2:C2');
                    $event->sheet->mergeCells('D2:H2');

                    $event->sheet->getDefaultRowDimension()->setRowHeight(30);
                    $event->sheet->getRowDimension(1)->setRowHeight(50);
                    $event->sheet->getRowDimension(2)->setRowHeight(40);
                    $workSheet = $event->sheet->getDelegate();
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
