<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tarjeta extends Model
{
    // Sin `HasFactory` a propósito: no hay factoría de tarjetas porque las
    // tarjetas siempre se crean a través de la relación (`$usuario->tarjeta()->create()`),
    // que es el mismo camino que usa la aplicación. Una factoría aparte sería
    // código muerto y, además, tendría que asignar `usuario_id` esquivando la
    // lista blanca.
    use SoftDeletes;

    protected $table = 'tarjetas';

    /**
     * `usuario_id` se deja fuera a propósito: la tarjeta siempre se crea y se
     * lee a través de la relación `$usuario->tarjeta()`, que fija la clave
     * foránea directamente. Si fuera asignable en masa, un usuario podría
     * reasignar su tarjeta (o la de otro) manipulando el campo en el POST.
     *
     * @var list<string>
     */
    protected $fillable = [
        'numero_tarjeta',
        'ultimos_4_digitos',
        'nombre_titular',
        'fecha_vencimiento',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // ÚNICO campo cifrado a nivel de aplicación en todo el sistema.
            // El número de tarjeta es el único dato clasificado como
            // "Restringido" (guía, sección 2). Se cifra y descifra con la
            // APP_KEY, así que un volcado de la base de datos sin esa clave
            // no expone el número.
            'numero_tarjeta' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Representación enmascarada para mostrar en pantalla sin descifrar el
     * número: `**** **** **** 1234`. Es la razón de existir de
     * `ultimos_4_digitos` (guía, sección 9).
     *
     * @return Attribute<string, never>
     */
    protected function numeroEnmascarado(): Attribute
    {
        return Attribute::get(
            fn (): string => '**** **** **** '.$this->ultimos_4_digitos
        );
    }
}
