<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $user = DB::table('users')->where('email', 'test@example.com')->first();

        if ($user) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', \App\Models\User::class)
                ->where('tokenable_id', $user->id)
                ->delete();

            DB::table('users')->where('id', $user->id)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
