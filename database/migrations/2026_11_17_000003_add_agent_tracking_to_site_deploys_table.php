<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Git deploy kini dikerjakan agent sungguhan: catat job deploy terakhir, commit yang
 * terpasang, output git/build, dan kunci publik deploy key (repo privat via SSH).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_deploys', function (Blueprint $table) {
            $table->foreignId('agent_job_id')->nullable()->after('webhook_secret')->constrained('agent_jobs')->nullOnDelete();
            $table->string('last_commit', 64)->nullable()->after('last_status');
            $table->string('last_commit_message')->nullable()->after('last_commit');
            $table->text('last_output')->nullable()->after('last_commit_message');
            $table->text('deploy_public_key')->nullable()->after('last_output');
        });
    }

    public function down(): void
    {
        Schema::table('site_deploys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_job_id');
            $table->dropColumn(['last_commit', 'last_commit_message', 'last_output', 'deploy_public_key']);
        });
    }
};
