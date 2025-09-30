<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Models\Notification;

class NotificationExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        // Get current date in the application's timezone
        $today = now()->startOfDay();
        $tomorrow = now()->addDay()->startOfDay();
        
        return Notification::with(['device:id,name', 'card:id,phone,status'])
            ->whereBetween('created_at', [$today, $tomorrow])
            ->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'ID',
            'Title',
            'Body',
            'Status',
            'Device Name',
            'Card Phone',
            'Card Status',
            'Expo Token',
            'Created At',
            'Sent At',
            'Updated At'
        ];
    }

    /**
     * @param mixed $notification
     * @return array
     */
    public function map($notification): array
    {
        return [
            $notification->id,
            $notification->title,
            $notification->body,
            $notification->status,
            $notification->device->name ?? '',
            $notification->card->phone ?? '',
            $notification->card->status ?? '',
            $notification->expo_token,
            $notification->created_at,
            $notification->sent_at ?? '',
            $notification->updated_at
        ];
    }
}
