<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlatformWallet;

class PlatformWalletSeeder extends Seeder
{
    public function run(): void
    {
        
        PlatformWallet::firstOrCreate([
            'id' => 1
        ], [
            'balance' => 0
        ]);
    }
}