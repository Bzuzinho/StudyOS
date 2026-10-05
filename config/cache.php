<?php
return ['default'=>env('CACHE_STORE','file'),'stores'=>['file'=>['driver'=>'file','path'=>storage_path('framework/cache/data')],'database'=>['driver'=>'database','connection'=>null,'table'=>'cache','lock_connection'=>null,'lock_table'=>null]],'prefix'=>env('CACHE_PREFIX','studyos_cache_')];
