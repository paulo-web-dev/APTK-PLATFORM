<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Usuário admin para testar o painel.
        // (Senha em texto puro — o cast 'hashed' do model criptografa sozinho.)
        User::updateOrCreate(
            ['email' => 'admin@aptk.test'],
            [
                'name'     => 'Admin APTK',
                'password' => 'password',
                'role'     => 'admin',
            ],
        );

        $this->call([
            ProductSeeder::class,
        ]);
    }
}
