<?php

use App\Support\DisplayNameNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('normalized_name', 64)->nullable()->after('display_name');
        });

        $used = [];

        DB::table('users')
            ->select(['id', 'display_name'])
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$used): void {
                foreach ($users as $user) {
                    $displayName = (string) $user->display_name;
                    $normalized = DisplayNameNormalizer::normalize($displayName);
                    $normalized = $normalized !== '' ? $normalized : 'guest-'.$user->id;

                    if (isset($used[$normalized])) {
                        $candidate = (int) $user->id;

                        do {
                            $suffix = ' '.(string) $candidate++;
                            $displayName = mb_substr((string) $user->display_name, 0, max(1, 16 - mb_strlen($suffix))).$suffix;
                            $normalized = DisplayNameNormalizer::normalize($displayName);
                        } while (isset($used[$normalized]));
                    }

                    $used[$normalized] = true;

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'display_name' => $displayName,
                            'normalized_name' => $normalized,
                        ]);
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('normalized_name', 'users_normalized_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_normalized_name_unique');
            $table->dropColumn('normalized_name');
        });
    }
};
