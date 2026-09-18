<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('mysql')->hasTable('customer_soa_auto_configs')) {
            Schema::connection('mysql')->create('customer_soa_auto_configs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->unique();
                $table->boolean('enabled')->default(false);
                $table->unsignedInteger('lead_value')->default(14);
                $table->string('lead_unit', 20)->default('days');
                $table->timestamp('last_sent_at')->nullable();
                $table->text('last_error')->nullable();
                $table->string('updated_by', 191)->nullable();
                $table->timestamps();

                $table->index(['enabled', 'customer_id'], 'customer_soa_auto_enabled_customer_idx');
            });
        }

        if (!Schema::connection('mysql')->hasTable('customer_soa_auto_send_logs')) {
            Schema::connection('mysql')->create('customer_soa_auto_send_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('login_id')->nullable();
                $table->string('email', 255);
                $table->string('invoice_key', 255);
                $table->string('invoice_no', 255)->nullable();
                $table->dateTime('invoice_date')->nullable();
                $table->dateTime('due_at')->nullable();
                $table->dateTime('send_at')->nullable();
                $table->decimal('balance', 18, 2)->default(0);
                $table->string('batch_key', 64)->nullable();
                $table->string('status', 32)->default('sent');
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->unique(['customer_id', 'invoice_key'], 'customer_soa_invoice_once_unique');
                $table->index(['customer_id', 'sent_at'], 'customer_soa_log_customer_sent_idx');
                $table->index('batch_key', 'customer_soa_log_batch_idx');
            });
        }

        if (!Schema::connection('mysql')->hasTable('customer_portal_notifications')) {
            Schema::connection('mysql')->create('customer_portal_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('login_id');
                $table->unsignedBigInteger('customer_id');
                $table->string('event_type', 50);
                $table->string('event_key', 191)->unique();
                $table->string('title', 191);
                $table->text('message')->nullable();
                $table->timestamp('event_at')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['login_id', 'customer_id', 'is_read'], 'customer_portal_generic_owner_unread_idx');
                $table->index(['customer_id', 'event_at'], 'customer_portal_generic_customer_event_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('customer_portal_notifications');
        Schema::connection('mysql')->dropIfExists('customer_soa_auto_send_logs');
        Schema::connection('mysql')->dropIfExists('customer_soa_auto_configs');
    }
};
