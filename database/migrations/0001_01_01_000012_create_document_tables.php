<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who issues a CP/CS document (Issuer, Statutory Auditor, ROC, Rating Agency …).
        Schema::create('issuing_authorities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        // The legal documents a deal can be executed under (trust deed, deed of hypothecation …),
        // and the products they apply to.
        Schema::create('legal_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->unique();
            $table->string('category', 20);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('legal_document_type_product', function (Blueprint $table) {
            $table->foreignId('legal_document_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['legal_document_type_id', 'product_id']);
        });

        // Conditions precedent (before the issue) and subsequent (after it). The four flags say which
        // kind of issue a document is suggested for; any document can still be added to any deal.
        Schema::create('condition_documents', function (Blueprint $table) {
            $table->id();
            $table->string('stage', 12);
            $table->string('name', 500);
            $table->foreignId('issuing_authority_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('listed_secured')->default(false);
            $table->boolean('listed_unsecured')->default(false);
            $table->boolean('unlisted_secured')->default(false);
            $table->boolean('unlisted_unsecured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['stage', 'name']);
            $table->unique(['stage', 'legacy_id']);
        });

        // A legal document on one deal. Besides the standard document, a deal can carry further copies,
        // supplements and amendments of the same type, numbered 1, 2, … per kind.
        Schema::create('deal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('legal_document_type_id')->constrained()->restrictOnDelete();
            $table->string('kind', 12);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->string('name', 255);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('removed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['transaction_id', 'legal_document_type_id', 'kind', 'sequence'], 'deal_documents_numbering_unique');
        });

        // One CP or CS item on a deal: from the master, or written for this deal only.
        Schema::create('deal_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->string('stage', 12);
            $table->foreignId('condition_document_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('name'); // Stack's CP/CS items can run to a paragraph
            $table->foreignId('issuing_authority_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('due_on')->nullable();
            $table->string('status', 20)->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('checker_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('checker_comment')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->text('waived_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->unique(['transaction_id', 'condition_document_id']);
            $table->unique(['stage', 'legacy_id']);
        });

        // Files uploaded against a deal document or a CP/CS item. Removing a file keeps the row (who
        // removed it and when); `path` is null for a Stack file whose copy hasn't been attached yet.
        Schema::create('document_files', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->morphs('attachable');
            $table->string('path')->nullable();
            $table->string('original_name', 255);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->unique(['attachable_type', 'attachable_id', 'legacy_id'], 'document_files_legacy_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_files');
        Schema::dropIfExists('deal_conditions');
        Schema::dropIfExists('deal_documents');
        Schema::dropIfExists('condition_documents');
        Schema::dropIfExists('legal_document_type_product');
        Schema::dropIfExists('legal_document_types');
        Schema::dropIfExists('issuing_authorities');
    }
};
