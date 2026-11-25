<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InviteRedemption extends Model
{
    public $timestamps = false;

    protected $fillable = ['invite_id', 'user_id', 'created_account', 'ip', 'user_agent', 'redeemed_at'];

    protected function casts(): array
    {
        return [
            'created_account' => 'boolean',
            'redeemed_at' => 'datetime',
        ];
    }

    public function invite(): BelongsTo
    {
        return $this->belongsTo(Invite::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
