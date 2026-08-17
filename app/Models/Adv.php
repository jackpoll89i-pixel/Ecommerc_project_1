<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Adv extends Model
{
    protected $fillable = [
        'image',
        'price',
        'location',
        'views_count',
        'interactions_count',
        'category_id',
        'description',
        'is_active',
        'user_id',
        'phone',
        'rate',
        'is_featured',
    ];

    // protected $hidden = ['created_at', 'updated_at'];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function likedByUsers()
    {
        return $this->belongsToMany(User::class, 'likes', 'adv_id', 'user_id');
    }

    public function favoritedByUsers()
    {
        return $this->belongsToMany(User::class, 'favorites', 'adv_id', 'user_id');
    }

    public function evaluatedByUsers()
    {
        return $this->belongsToMany(User::class, 'evaluations', 'adv_id', 'user_id')
                    ->withPivot('rating', 'comment')
                    ->withTimestamps();
    }

    public function reportedByUsers()
    {
        return $this->belongsToMany(User::class, 'reports', 'adv_id', 'user_id')
                    ->withPivot('type', 'content')
                    ->withTimestamps();
    }

    /**
     * Get users through activities (many-to-many relationship).
     */
    public function activitiesUsers()
    {
        return $this->belongsToMany(User::class, 'user_activities', 'adv_id', 'user_id')
                    ->withPivot('activity_type')
                    ->withTimestamps();
    }

    /**
     * Get users by activity type.
     */
    public function usersByActivityType($type)
    {
        return $this->belongsToMany(User::class, 'user_activities', 'adv_id', 'user_id')
                    ->withPivot('activity_type')
                    ->wherePivot('activity_type', $type)
                    ->withTimestamps();
    }

    /**
     * Get all activities performed on this advertisement.
     */
    public function advActivities()
    {
        return $this->hasMany(UserActivities::class, 'adv_id');
    }

    /**
     * Get activities by type.
     */
    public function advActivitiesByType($type)
    {
        return $this->hasMany(UserActivities::class, 'adv_id')->where('activity_type', $type);
    }

    /**
     * Accessor: return full image URL if stored path exists
     */
    public function getImageAttribute($value)
    {
        if (empty($value)) {
            return null;
        }
        if (is_string($value) && (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/storage/'))){
            return $value;
        }
        return Storage::url($value);
    }

}
