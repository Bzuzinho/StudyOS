<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SourceCourse extends Model
{
    protected $guarded=[];
    protected function casts():array{return ['metadata'=>'array'];}
    public function course():BelongsTo{return $this->belongsTo(Course::class);}
}
