<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Traits\BelongsToOwner;

class Category extends Model
{
    use BelongsToOwner;
    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
    ];

    // Auto-generate slug dari name
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($category) {
            $category->slug = Str::slug($category->name);
        });

        static::updating(function ($category) {
            $category->slug = Str::slug($category->name);
        });
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}