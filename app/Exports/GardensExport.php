<?php

namespace App\Exports;

use App\Models\Garden;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GardensExport implements FromCollection, WithHeadings, WithMapping
{
    private array $gardenIds;
    private array $filters;

    public function __construct(array $gardenIds = [], array $filters = [])
    {
        $this->gardenIds = $gardenIds;
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = Garden::with(['city', 'countryData', 'images']);
        
        // Apply garden ID filter
        if (!empty($this->gardenIds)) {
            $query->whereIn('id', $this->gardenIds);
        }

        // Apply additional filters
        if (!empty($this->filters)) {
            if (isset($this->filters['name'])) {
                $query->where(function($q) {
                    $q->where('name', 'like', '%' . $this->filters['name'] . '%')
                      ->orWhere('referral_code', 'like', '%' . $this->filters['name'] . '%');
                });
            }

            if (isset($this->filters['address'])) {
                $query->where('address', 'like', '%' . $this->filters['address'] . '%');
            }

            if (isset($this->filters['country'])) {
                $query->where('country_id', $this->filters['country']);
            }

            if (isset($this->filters['tax_id'])) {
                $query->where('tax_id', $this->filters['tax_id']);
            }

            if (isset($this->filters['phone'])) {
                $query->where('phone', 'like', '%' . $this->filters['phone'] . '%');
            }

            if (isset($this->filters['email'])) {
                $query->where('email', 'like', '%' . $this->filters['email'] . '%');
            }

            if (isset($this->filters['status'])) {
                $query->where('status', $this->filters['status']);
            }

            if (isset($this->filters['balance_min'])) {
                $query->where('balance', '>=', $this->filters['balance_min']);
            }

            if (isset($this->filters['balance_max'])) {
                $query->where('balance', '<=', $this->filters['balance_max']);
            }
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'ID', 'Name', 'Address', 'Tax ID', 'City ID', 'City Name', 'Country ID', 'Country Name', 'Country Phone Index', 'Country SMS Gateway ID', 'Phone', 'Email', 'Status', 'Balance', 'Balance Comment', 'Percents', 'Created At', 'Updated At', 'Images'
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
            $garden->country_id,
            optional($garden->countryData)->name,
            optional($garden->countryData)->phone_index,
            optional($garden->countryData)->sms_gateway_id,
            $garden->phone,
            $garden->email,
            $garden->status,
            $garden->balance,
            $garden->balance_comment,
            $garden->percents,
            $garden->created_at,
            $garden->updated_at,
            $images,
        ];
    }
}

