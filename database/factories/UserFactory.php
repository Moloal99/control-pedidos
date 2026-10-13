<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo' => 'V'.fake()->unique()->numberBetween(10, 999),
            'name' => fake()->name(),
            'email' => fake()->unique()->userName().'@plasticoscarmen.com',
            'rol' => Rol::Vendedor,
            'carga_por_otros' => false,
            'despacha' => false,
            'activo' => true,
        ];
    }

    public function rol(Rol $rol): static
    {
        return $this->state(fn () => ['rol' => $rol]);
    }

    public function supervisor(): static
    {
        return $this->state(fn () => ['carga_por_otros' => true]);
    }

    public function despacha(): static
    {
        return $this->state(fn () => ['despacha' => true]);
    }
}
