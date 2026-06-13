<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Mariana Alves', 'Rafael Costa', 'Juliana Mendes', 'Bruno Carvalho',
            'Camila Rocha', 'Diego Fernandes', 'Patrícia Lima', 'Thiago Souza',
            'Larissa Pereira', 'Gustavo Martins', 'Aline Barbosa', 'Felipe Ramos',
        ];

        foreach ($names as $name) {
            $email = Str::slug($name, '.').'@cliente.test';

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name'     => $name,
                    'password' => 'password', // cast 'hashed' criptografa
                    'role'     => 'customer',
                    'phone'    => sprintf('(11) 9%04d-%04d', rand(1000, 9999), rand(1000, 9999)),
                ],
            );

            // Data de cadastro espalhada (alguns na semana atual p/ "novos esta semana").
            $user->created_at = now()->subDays(rand(0, 120));
            $user->save();
        }

        $this->command->info('Clientes de demonstração: '.User::where('role', 'customer')->count());
    }
}
