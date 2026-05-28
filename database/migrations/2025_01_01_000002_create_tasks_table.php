<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTasksTable extends Migration
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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            // Task details
            $table->string('title', 255);
            $table->text('description')->nullable();

            // Site & User references
            $table->unsignedBigInteger('site_id')->nullable()->index();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->unsignedBigInteger('assigned_by')->nullable()->index();

            // Priority: Low, Medium, High
            $table->enum('priority', ['Low', 'Medium', 'High'])->default('Medium');

            // Status: Pending, In Progress, Completed
            $table->enum('status', ['Pending', 'In Progress', 'Completed'])->default('Pending');

            // Due date (used for past-date lock and date filtering)
            $table->date('due_date')->nullable()->index();

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
        Schema::dropIfExists('tasks');
    }
}
