<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void{
        Schema::create('sync_connections',function(Blueprint $t){$t->id();$t->string('source');$t->string('name');$t->text('secret')->nullable();$t->json('config')->nullable();$t->string('status')->default('pending');$t->timestampTz('last_synced_at')->nullable();$t->timestamps();});
        Schema::create('sync_runs',function(Blueprint $t){$t->id();$t->foreignId('sync_connection_id')->constrained()->cascadeOnDelete();$t->string('status')->default('running');$t->timestampTz('started_at');$t->timestampTz('finished_at')->nullable();$t->json('stats')->nullable();$t->text('error')->nullable();$t->timestamps();});
        Schema::create('evidence',function(Blueprint $t){$t->id();$t->string('entity_type');$t->unsignedBigInteger('entity_id')->nullable();$t->string('fact_type');$t->string('source');$t->decimal('confidence',3,2)->default(1);$t->timestampTz('observed_at');$t->json('payload')->nullable();$t->timestamps();$t->index(['entity_type','entity_id']);});
    }
    public function down():void{Schema::dropIfExists('evidence');Schema::dropIfExists('sync_runs');Schema::dropIfExists('sync_connections');}
};
