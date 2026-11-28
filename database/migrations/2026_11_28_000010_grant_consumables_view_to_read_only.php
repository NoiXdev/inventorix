<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The consumables migration added the section without letting existing
     * read-only groups see it, unlike licenses, components and kits.
     */
    public function up(): void
    {
        foreach (DB::table('groups')->where('name', 'Read only')->get() as $group) {
            $permissions = json_decode($group->permissions ?? '{}', true) ?: [];
            $permissions['consumables.view'] = 1;
            DB::table('groups')->where('id', $group->id)->update(['permissions' => json_encode($permissions)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('groups')->where('name', 'Read only')->get() as $group) {
            $permissions = json_decode($group->permissions ?? '{}', true) ?: [];
            unset($permissions['consumables.view']);
            DB::table('groups')->where('id', $group->id)->update(['permissions' => json_encode($permissions)]);
        }
    }
};
