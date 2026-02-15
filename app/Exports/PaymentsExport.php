<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Models\Payment;
use App\Models\Garden;
use App\Models\Country;
use App\Models\Dister;

class PaymentsExport implements FromCollection, WithHeadings, WithMapping
{
    protected array $gardenDisterMap = [];
    protected $gardens;
    protected $countries;
    protected ?string $dateFrom;
    protected ?string $dateTo;

    public function __construct(?string $dateFrom = null, ?string $dateTo = null)
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;

        // Pre-load dister→garden mapping
        $disters = Dister::all();
        foreach ($disters as $dister) {
            if (is_array($dister->gardens)) {
                foreach ($dister->gardens as $gId) {
                    $this->gardenDisterMap[$gId] = $dister;
                }
            }
        }
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = Payment::with([
            'card:id,phone,status,child_first_name,child_last_name,parent_name,group_id',
            'card.group:id,name,garden_id',
            'card.group.garden:id,name,country_id',
            'paymentGateway:id,name,currency',
            'garden:id,name,country_id',
        ]);

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $payments = $query->orderBy('created_at', 'desc')->get();

        // Pre-load gardens and countries for distribution
        $gardenIds = $payments->pluck('garden_id')
            ->merge($payments->pluck('card.group.garden.id'))
            ->filter()->unique()->values()->all();

        $this->gardens = Garden::whereIn('id', $gardenIds)->get()->keyBy('id');
        $countryIds = $this->gardens->pluck('country_id')->unique()->filter()->values()->all();
        $this->countries = Country::whereIn('id', $countryIds)->get()->keyBy('id');

        return $payments;
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
            'Card ID',
            'Card Phone',
            'Child Name',
            'Parent Name',
            'Garden Group',
            'Garden Name',
            'Amount',
            'Currency',
            'Type',
            'Status',
            'Comment',
            'Payment Gateway',
            'Admin %',
            'Admin Amount',
            'Dister',
            'Dister %',
            'Dister Amount',
            'Second Dister',
            'Second Dister %',
            'Second Dister Amount',
            'Created At',
        ];
    }

    /**
     * @param mixed $payment
     * @return array
     */
    public function map($payment): array
    {
        $amount = abs((float) $payment->amount);

        // Resolve garden
        $gardenId = $payment->garden_id;
        if (!$gardenId && $payment->card && $payment->card->group && $payment->card->group->garden) {
            $gardenId = $payment->card->group->garden->id;
        }

        $garden = $gardenId ? ($this->gardens[$gardenId] ?? null) : null;
        $dister = $gardenId ? ($this->gardenDisterMap[$gardenId] ?? null) : null;

        // Distribution (must sum to 100%)
        $disterPercent = $dister ? (float) ($dister->percent ?? 0) : 0;
        // second_percent only applies if the dister actually has a main_dister (parent)
        $hasMainDister = $dister && !empty($dister->main_dister);
        $secondDisterPercent = $hasMainDister ? (float) ($dister->second_percent ?? 0) : 0;
        $adminPercent = round(100 - $disterPercent - $secondDisterPercent, 2);
        if ($adminPercent < 0) $adminPercent = 0;

        $disterAmount = round($amount * $disterPercent / 100, 2);
        $secondDisterAmount = round($amount * $secondDisterPercent / 100, 2);
        $adminAmount = round($amount - $disterAmount - $secondDisterAmount, 2);
        if ($adminAmount < 0) $adminAmount = 0;

        $disterName = $dister ? ($dister->first_name . ' ' . $dister->last_name) : '';

        // Second dister name
        $secondDisterName = '';
        if ($secondDisterPercent > 0 && $dister && is_array($dister->main_dister)) {
            $mainDisterId = $dister->main_dister['id'] ?? null;
            if ($mainDisterId) {
                $mainDister = Dister::find($mainDisterId);
                $secondDisterName = $mainDister ? ($mainDister->first_name . ' ' . $mainDister->last_name) : '';
            }
        }

        return [
            $payment->id,
            $payment->transaction_number,
            $payment->transaction_number_bank ?? '',
            $payment->card_id,
            $payment->card->phone ?? '',
            $payment->card ? trim(($payment->card->child_first_name ?? '') . ' ' . ($payment->card->child_last_name ?? '')) : '',
            $payment->card->parent_name ?? '',
            $payment->card->group->name ?? '',
            $garden->name ?? ($payment->card->group->garden->name ?? ''),
            $payment->amount,
            $payment->currency ?? '',
            $payment->type ?? '',
            $payment->status ?? '',
            $payment->comment ?? '',
            $payment->paymentGateway->name ?? '',
            $adminPercent . '%',
            $adminAmount,
            $disterName,
            $disterPercent . '%',
            $disterAmount,
            $secondDisterName ?: '',
            ($secondDisterPercent > 0 && $secondDisterName) ? ($secondDisterPercent . '%') : '',
            ($secondDisterPercent > 0 && $secondDisterName) ? $secondDisterAmount : '',
            $payment->created_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }
}
