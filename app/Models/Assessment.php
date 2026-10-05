<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Assessment extends Model
{
    protected $guarded=[];
    protected function casts():array{return ['opens_at'=>'datetime','due_at'=>'datetime','confirmed'=>'boolean','metadata'=>'array'];}
    public function course():BelongsTo{return $this->belongsTo(Course::class);}
}
