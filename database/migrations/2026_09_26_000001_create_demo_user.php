<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Pastikan role 'demo' ada di tabel roles
        $demoRole = DB::table('roles')->where('nama_role', 'demo')->first();
        if (! $demoRole) {
            $idRole = DB::table('roles')->insertGetId([
                'id_role' => 5,
                'nama_role' => 'demo',
            ]);
        } else {
            $idRole = $demoRole->id_role;
        }

        // 2. Ambil id_sekolah aktif pertama sebagai data contoh
        $idSekolah = DB::table('tb_sekolah')->where('is_active', 1)->value('id_sekolah') ?? 1;

        // 3. Buat atau perbarui akun khusus demo di tb_user
        $existingDemoUser = DB::table('tb_user')->where('username', 'demo')->first();
        if (! $existingDemoUser) {
            DB::table('tb_user')->insert([
                'id_sekolah' => $idSekolah,
                'id_role' => $idRole,
                'username' => 'demo',
                'password' => Hash::make('demo123'),
                'nama_lengkap' => 'Akun Khusus Demo',
                'is_active' => 1,
                'created_at' => now(),
            ]);
        } else {
            DB::table('tb_user')->where('username', 'demo')->update([
                'id_role' => $idRole,
                'is_active' => 1,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tb_user')->where('username', 'demo')->delete();
        DB::table('roles')->where('nama_role', 'demo')->delete();
    }
};
