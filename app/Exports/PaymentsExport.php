<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Models\Payment;

class PaymentsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Payment::with(['card.group.garden:id,name'])->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'ID',
            'Transaction Number',
            'Bank Transaction Number',
            'Card Number',
            'Card ID',
            'Card Phone',
            'Card Status',
            'Garden Group',
            'Garden Name',
            'Created At',
            'Updated At'
        ];
    }

    /**
     * @param mixed $payment
     * @return array
     */
    public function map($payment): array
    {
        return [
            $payment->id,
            $payment->transaction_number,
            $payment->transaction_number_bank ?? '',
            $payment->card_number,
            $payment->card_id,
            $payment->card->phone ?? '',
            $payment->card->status ?? '',
            $payment->card->group->name ?? '',
            $payment->card->group->garden->name ?? '',
            $payment->created_at,
            $payment->updated_at
        ];
    }
}
