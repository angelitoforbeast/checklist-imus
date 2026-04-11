<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password')->constrained('roles')->nullOnDelete();
        });

        // Migrate existing string roles to role_id
        $adminRole = \App\Models\Role::where('slug', 'admin')->first();
        $kasambahayRole = \App\Models\Role::where('slug', 'kasambahay')->first();

        if ($adminRole) {
            \DB::table('users')->where('role', 'admin')->update(['role_id' => $adminRole->id]);
        }
        if ($kasambahayRole) {
            \DB::table('users')->where('role', 'staff')->update(['role_id' => $kasambahayRole->id]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'staff'])->default('staff')->after('password');
        });

        // Migrate back
        $adminRole = \App\Models\Role::where('slug', 'admin')->first();
        if ($adminRole) {
            \DB::table('users')->where('role_id', $adminRole->id)->update(['role' => 'admin']);
        }
        \DB::table('users')->whereNull('role')->update(['role' => 'staff']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }
};
