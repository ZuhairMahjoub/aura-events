<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ListingSlot extends Model
{
    use HasFactory, HasUlids;
    public $incrementing = false;
    
    protected $keyType = 'string';

    protected $fillable = [
        'listing_availability_id',
        'slot_name',
        'start_time',
        'end_time',
        'remaining_capacity',
    ];

    protected function casts(): array
    {
        return [
            'id' =>'string',
            'slot_name'          => 'array',
            'start_time'         => 'datetime:H:i',
            'end_time'           => 'datetime:H:i',
            'remaining_capacity' => 'integer',
        ];
    }

    // علاقة تربط السلوت باليوم التابع له
    public function availability(): BelongsTo
    {
        return $this->belongsTo(ListingAvailability::class, 'listing_availability_id');
    }
}