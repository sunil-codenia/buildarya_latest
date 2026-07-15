<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExtraUsersAndExtraSitesToCompaniesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('extra_users')->default(0)->after('max_sites');
            $table->unsignedInteger('extra_sites')->default(0)->after('extra_users');
            $table->timestamp('extra_users_expired')->nullable()->after('extra_sites');
            $table->timestamp('extra_sites_expired')->nullable()->after('extra_users_expired');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['extra_users', 'extra_sites', 'extra_users_expired', 'extra_sites_expired']);
        });
    }
}
