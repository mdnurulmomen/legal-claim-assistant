<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalComment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $with = ['user', 'replied_comment'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replies()
    {
        return $this->hasMany(InternalComment::class, 'comment_id');
    }

    public function replied_comment()
    {
      return $this->belongsTo(InternalComment::class,'comment_id', 'id');
    }

    public function participants()
    {
      return $this->hasMany(User::class,'user_id', 'id');
    }
}
