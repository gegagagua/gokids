<?php

namespace App\Exports;

use App\Models\Card;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CardsExport implements FromCollection, WithHeadings, WithMapping
{
    private array $allowedGardenIds;
    private array $cardIds;
    private array $filters;

    public function __construct(array $allowedGardenIds = [], array $cardIds = [], array $filters = [])
    {
        $this->allowedGardenIds = $allowedGardenIds;
        $this->cardIds = $cardIds;
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = Card::with(['group.garden.countryData', 'group.garden.city', 'personType', 'parents', 'people']);

        // Apply allowed garden IDs filter
        if (!empty($this->allowedGardenIds)) {
            $query->whereHas('group', function ($q) {
                $q->whereIn('garden_id', $this->allowedGardenIds);
            });
        }

        // Apply card IDs filter
        if (!empty($this->cardIds)) {
            $query->whereIn('id', $this->cardIds);
        }

        // Apply additional filters
        if (!empty($this->filters)) {
            if (isset($this->filters['search'])) {
                $query->where(function ($q) {
                    $q->where('child_first_name', 'like', '%' . $this->filters['search'] . '%')
                        ->orWhere('child_last_name', 'like', '%' . $this->filters['search'] . '%')
                        ->orWhere('parent_name', 'like', '%' . $this->filters['search'] . '%');
                });
            }

            if (isset($this->filters['phone'])) {
                $query->where('phone', 'like', '%' . $this->filters['phone'] . '%');
            }

            if (isset($this->filters['status'])) {
                $query->where('status', $this->filters['status']);
            }

            if (isset($this->filters['group_id'])) {
                $query->where('group_id', $this->filters['group_id']);
            }

            if (isset($this->filters['garden_id'])) {
                $query->whereHas('group', function ($q) {
                    $q->where('garden_id', $this->filters['garden_id']);
                });
            }

            if (isset($this->filters['country_id'])) {
                $query->whereHas('group.garden', function ($q) {
                    $q->where('country_id', $this->filters['country_id']);
                });
            }

            if (isset($this->filters['city_id'])) {
                $query->whereHas('group.garden', function ($q) {
                    $q->where('city_id', $this->filters['city_id']);
                });
            }

            if (isset($this->filters['person_type_id'])) {
                $query->where('person_type_id', $this->filters['person_type_id']);
            }

            if (isset($this->filters['parent_code'])) {
                $query->where('parent_code', $this->filters['parent_code']);
            }

            if (isset($this->filters['parent_verification'])) {
                $query->where('parent_verification', $this->filters['parent_verification']);
            }

            if (isset($this->filters['license_type'])) {
                $query->whereJsonContains('license->type', $this->filters['license_type']);
            }
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Child First Name',
            'Child Last Name',
            'Parent Name',
            'Phone',
            'Status',
            'Group ID',
            'Group Name',
            'Garden ID',
            'Garden Name',
            'Country ID',
            'Country Name',
            'City ID',
            'City Name',
            'Person Type',
            'Parent Code',
            'Parent Verification',
            'License',
            'Parents',
            'People',
            'Created At',
            'Updated At',
        ];
    }

    public function map($card): array
    {
        $license = $card->license ? json_encode($card->license) : '';
        $parents = $card->parents ? $card->parents->map(fn($p) => trim(($p->first_name ?? '').' '.($p->last_name ?? '')).($p->phone ? ' ('.$p->phone.')' : ''))->implode('; ') : '';
        $people = $card->people ? $card->people->map(fn($p) => trim($p->name ?? '').($p->relationship ? ' - '.$p->relationship : ''))->implode('; ') : '';

        return [
            $card->id,
            $card->child_first_name,
            $card->child_last_name,
            $card->parent_name,
            $card->phone,
            $card->status,
            optional($card->group)->id,
            optional($card->group)->name,
            optional($card->group)->garden_id,
            optional($card->group->garden)->name,
            optional($card->group->garden)->country_id,
            optional($card->group->garden->countryData)->name,
            optional($card->group->garden)->city_id,
            optional($card->group->garden->city)->name,
            optional($card->personType)->name,
            $card->parent_code,
            $card->parent_verification ? 'true' : 'false',
            $license,
            $parents,
            $people,
            $card->created_at,
            $card->updated_at,
        ];
    }
}

