<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserSearchAuditTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_search_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->string('username')->nullable();
            $table->string('action_type')->default('search')->index();
            $table->string('entity')->nullable()->index();
            $table->string('request_url')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('question')->nullable();
            $table->longText('response')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('form_data')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->timestamp('searched_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_search_audit');
    }
}
