<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsCheckedToUserSearchAuditTable extends Migration
{
    public function up()
    {
        Schema::table('user_search_audit', function (Blueprint $table) {
            if (!Schema::hasColumn('user_search_audit', 'is_checked')) {
                $table->boolean('is_checked')->default(0)->after('response_payload');
            }
        });
    }

    public function down()
    {
        Schema::table('user_search_audit', function (Blueprint $table) {
            if (Schema::hasColumn('user_search_audit', 'is_checked')) {
                $table->dropColumn('is_checked');
            }
        });
    }
}
