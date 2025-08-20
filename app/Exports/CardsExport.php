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

    public function __construct(array $allowedGardenIds = [], array $cardIds = [])
    {
        $this->allowedGardenIds = $allowedGardenIds;
        $this->cardIds = $cardIds;
    }

    public function collection()
    {
        $query = Card::with(['group', 'personType', 'parents', 'people']);

        if (!empty($this->allowedGardenIds)) {
            $query->whereHas('group', function ($q) {
                $q->whereIn('garden_id', $this->allowedGardenIds);
            });
        }

        if (!empty($this->cardIds)) {
            $query->whereIn('id', $this->cardIds);
        }

        return $query->get();
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
        $parents = $card->parents ? $card->parents->map(fn($p) => trim($p->name ?? '').($p->phone ? ' ('.$p->phone.')' : ''))->implode('; ') : '';
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

