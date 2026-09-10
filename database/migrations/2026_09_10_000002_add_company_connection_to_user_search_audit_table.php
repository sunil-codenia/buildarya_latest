<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompanyConnectionToUserSearchAuditTable extends Migration
{
    public function up()
    {
        Schema::table('user_search_audit', function (Blueprint $table) {
            $table->string('company_connection')->nullable()->index()->after('username');
        });
    }

    public function down()
    {
        Schema::table('user_search_audit', function (Blueprint $table) {
            $table->dropIndex(['company_connection']);
            $table->dropColumn('company_connection');
        });
    }
}
