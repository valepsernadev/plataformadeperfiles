<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    /**
     * ATENCIÓN — lista blanca deliberada.
     *
     * `role` NO está aquí y no debe estarlo nunca: es el campo que decide los
     * privilegios, así que permitir su asignación masiva desde el formulario de
     * registro o de perfil abriría una escalada de privilegios vertical
     * (usuario -> administrador) simplemente enviando `role=administrador`
     * en el POST. Se asigna siempre de forma explícita en código confiable
     * (seeders y pruebas).
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre_completo',
        'numero_identificacion',
        'email',
        'password',
        'telefono',
        'direccion',
        'ocupacion',
        'ingresos_mensuales',
        'entidad_bancaria',
    ];

    /**
     * Nunca deben salir en una respuesta ni en un `toArray()`/`toJson()`.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // Hashea con bcrypt al asignar. Es irreversible, no cifrado.
            'password' => 'hashed',
            // `decimal`, no `float`: evita errores de redondeo en dinero.
            'ingresos_mensuales' => 'decimal:2',
        ];
    }

    /**
     * La tarjeta del usuario (relación 1 a 1, guía sección 9).
     *
     * @return HasOne<Tarjeta, $this>
     */
    public function tarjeta(): HasOne
    {
        return $this->hasOne(Tarjeta::class, 'usuario_id');
    }

    /**
     * Fuente única de verdad sobre quién es administrador. Se usa en el
     * middleware, las policies y la navegación.
     */
    public function esAdministrador(): bool
    {
        return $this->role === 'administrador';
    }

    /**
     * Datos mínimos que el panel de administración puede listar (guía, sección 7):
     * nombre, correo y cédula. Nunca ingresos, entidad bancaria ni tarjeta.
     *
     * @return list<string>
     */
    public static function columnasListadoAdmin(): array
    {
        return ['id', 'nombre_completo', 'email', 'numero_identificacion'];
    }
}
