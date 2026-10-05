<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ClassOccurrence extends Model
{
    protected $guarded=[];
    protected function casts():array{return ['starts_at'=>'datetime','ends_at'=>'datetime','source_payload'=>'array'];}
    public function course():BelongsTo{return $this->belongsTo(Course::class);}
}
