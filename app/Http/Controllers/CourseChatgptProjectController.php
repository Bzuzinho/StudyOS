<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseChatgptProjectController
{
 public function store(Request $request, Course $course) {
   $data=$request->validate(['project_url'=>['required','url','max:500']]);
   $url=$data['project_url'];
   $parts=parse_url($url);
   if (($parts['scheme']??'')!=='https' || ($parts['host']??'')!=='chatgpt.com'
       || !preg_match('~^/(?:g/g-p-[^/?#]+|g-p-[^/?#]+|project/[^/?#]+)(?:/[^?#]*)?$~', $parts['path']??'')) {
      throw ValidationException::withMessages(['project_url'=>'Indica o endereço HTTPS do projeto no chatgpt.com, não uma conversa ou outro site.']);
   }
   DB::table('course_chatgpt_projects')->updateOrInsert(
     ['course_id'=>$course->id],['project_url'=>$url,'updated_at'=>now(),'created_at'=>now()]
   );
   return redirect()->route('practice.external.index',$course)->with('status','Projeto ChatGPT associado à UC.');
 }
}