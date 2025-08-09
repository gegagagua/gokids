<?php

namespace App\Exports;

use App\Models\Garden;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GardensExport implements FromCollection, WithHeadings, WithMapping
{
    private array $gardenIds;

    public function __construct(array $gardenIds = [])
    {
        $this->gardenIds = $gardenIds;
    }

    public function collection()
    {
        $query = Garden::with(['city', 'images']);
        if (!empty($this->gardenIds)) {
            $query->whereIn('id', $this->gardenIds);
        }
        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID', 'Name', 'Address', 'Tax ID', 'City ID', 'City Name', 'Phone', 'Email', 'Created At', 'Updated At', 'Images'
        ];
    }

    public function map($garden): array
    {
        $images = $garden->images->pluck('image')->implode(', ');
        return [
            $garden->id,
            $garden->name,
            $garden->address,
            $garden->tax_id,
            $garden->city_id,
            optional($garden->city)->name,
            $garden->phone,
            $garden->email,
            $garden->created_at,
            $garden->updated_at,
            $images,
        ];
    }
}

