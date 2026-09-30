<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the starter-kit two-factor and passkey schema (DECISIONS.md D-095).
 *
 * The original create migrations stay in place; down() restores their schema.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $twoFactorColumns = [
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('passkeys');

        $columns = array_values(array_filter(
            $this->twoFactorColumns,
            fn (string $column): bool => Schema::hasColumn('users', $column),
        ));

        if ($columns !== []) {
            Schema::table('users', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'two_factor_secret')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('two_factor_secret')->after('password')->nullable();
                $table->text('two_factor_recovery_codes')->after('two_factor_secret')->nullable();
                $table->timestamp('two_factor_confirmed_at')->after('two_factor_recovery_codes')->nullable();
            });
        }

        if (! Schema::hasTable('passkeys')) {
            Schema::create('passkeys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('credential_id')->unique();
                $table->json('credential');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->index('user_id');
            });
        }
    }
};
