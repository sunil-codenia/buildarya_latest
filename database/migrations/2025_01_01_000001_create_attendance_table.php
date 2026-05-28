<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceTable extends Migration
{
    /**
     * Run the migrations.
     * NOTE: This project uses multi-tenant databases.
     * Run this on each company database connection, e.g.:
     *   php artisan migrate --database=company_new_buildarya
     *
     * @return void
     */
    public function up()
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();

            // User & Site references
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('site_id')->nullable()->index();

            // Date & Times
            $table->date('date')->index();
            $table->dateTime('in_time')->nullable();
            $table->dateTime('out_time')->nullable();

            // Status: Present, Absent, Half Day, Leave, Holiday
            $table->string('status', 50)->default('Present');

            // GPS Locations stored as "lat,lng" strings
            $table->string('in_location', 100)->nullable();
            $table->string('out_location', 100)->nullable();

            // Photo paths (relative to public/)
            $table->string('image', 500)->nullable();
            $table->string('out_image', 500)->nullable();

            // Remarks / notes
            $table->text('remarks')->nullable();

            $table->timestamps();

            // Prevent duplicate check-ins per user per day
            $table->unique(['user_id', 'date'], 'attendance_user_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attendance');
    }
}
