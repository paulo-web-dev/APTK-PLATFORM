<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Usuário admin para o painel (senha em texto puro — o cast 'hashed' criptografa).
        User::updateOrCreate(
            ['email' => 'admin@aptk.test'],
            [
                'name'     => 'Admin APTK',
                'password' => 'password',
                'role'     => 'admin',
            ],
        );

        $this->call([
            ProductSeeder::class,           // categorias + produtos
            SubscriptionPlanSeeder::class,  // planos do Clube
            CustomerSeeder::class,          // clientes de demonstração
            OrderSeeder::class,             // pedidos variados (depende dos dois acima)
        ]);
    }
}
