<?php
use Illuminate\Support\Facades\Artisan;
Artisan::command('studyos:status',function(){$this->info('StudyOS operational.');})->purpose('Show StudyOS application status');
