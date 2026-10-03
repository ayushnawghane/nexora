<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Imported legacy deals keep their legacy ids (so the import can be re-run), and their engagement
     * letters arrive as records first: the PDFs live on the old Stack server and are attached later,
     * and Stack kept no HTML for them.
     */
    public function up(): void
    {
        Schema::table('engagement_letters', function (Blueprint $table) {
            $table->longText('body_html')->nullable()->change();
            $table->string('pdf_path')->nullable()->change();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique()->after('generated_by');
            $table->unsignedBigInteger('legacy_upload_id')->nullable()->after('legacy_id')->comment('Stack upload_file id of the PDF, until the file is copied over');
        });

        foreach (['fee_schedule_periods', 'deal_status_changes', 'deal_status_requests'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        foreach (['fee_schedule_periods', 'deal_status_changes', 'deal_status_requests'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['legacy_id']);
                $table->dropColumn('legacy_id');
            });
        }

        Schema::table('engagement_letters', function (Blueprint $table) {
            $table->dropUnique(['legacy_id']);
            $table->dropColumn(['legacy_id', 'legacy_upload_id']);
        });
        // body_html / pdf_path stay nullable: rows without them may exist by now.
    }
};
