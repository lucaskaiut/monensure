<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $group = Group::factory()->create();
        $users = User::factory(5)->make();

        foreach($users as $user){
            User::create([
                'group_id' => $group->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'password' => $user->password,
                'photo' => $user->photo,
                'phone' => $user->phone
            ]);
        }

        Category::insert([
            [
                'name' => 'Alimentação',
                'description' => 'Comida, lanches, bebidas, etc',
                'group_id' => $group->id,
                'id' => Uuid::uuid4()
            ], 
            [
                'name' => 'Contas de Casa', 
                'description' => 'Água, luz, telefone, gás, aluguel, etc',
                'group_id' => $group->id,
                'id' => Uuid::uuid4()
            ]
        ]);
    }
}
