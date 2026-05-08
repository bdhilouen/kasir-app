<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToOwner
{
    /**
     * Boot trait — auto filter by owner dan auto set owner_id saat create.
     */
    protected static function bootBelongsToOwner(): void
    {
        static::creating(function ($model) {
            if (Auth::check() && empty($model->owner_id)) {
                $model->owner_id = self::resolveOwnerId();
            }
        });

        static::addGlobalScope('owner', function (Builder $builder) {
            if (Auth::check()) {
                $ownerId = self::resolveOwnerId();
                $builder->where($builder->getModel()->getTable() . '.owner_id', $ownerId);
            }
        });
    }

    /**
     * Kasir pakai owner_id dari admin yang membuatnya.
     * Admin pakai id-nya sendiri.
     */
    public static function resolveOwnerId(): ?int
    {
        if (!Auth::check()) {
            return null;
        }

        $user = Auth::user();

        if ($user->role === 'cashier') {
            return $user->created_by ?? $user->id; // data milik admin yang buat kasir ini
        }

        return $user->id;
    }
}
