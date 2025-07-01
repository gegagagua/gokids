<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ParentModel;

class ParentModelSeeder extends Seeder
{
    public function run(): void
    {
        ParentModel::create([
            'first_name' => 'ლელა',
            'last_name' => 'მარგიანი',
            'status' => 'active',
            'phone' => '555123456',
            'code' => 'PM001',
            'group_id' => 3,
            'card_id' => 2,
        ]);

        ParentModel::create([
            'first_name' => 'გიორგი',
            'last_name' => 'ჯანაშია',
            'status' => 'active',
            'phone' => '555654321',
            'code' => 'PM002',
            'group_id' => 3,
            'card_id' => 2,
        ]);
    }
}
