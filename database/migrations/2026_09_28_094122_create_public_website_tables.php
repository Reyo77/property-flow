<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            // Globally unique (not just within the company): it's the subdomain of the public site.
            $table->string('slug')->nullable()->unique()->after('name');
        });

        $this->backfillCommunitySlugs();

        Schema::table('announcements', function (Blueprint $table) {
            // Shown on the community's public news page, in addition to (not instead of) its normal audience.
            $table->boolean('is_public')->default(false)->after('pinned');
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->text('message');
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });

        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }

    /**
     * Give every existing community a unique slug derived from its name, since new rows get one
     * from Community's own creating hook but rows that predate this migration have none.
     */
    private function backfillCommunitySlugs(): void
    {
        $usedSlugs = [];

        foreach (DB::table('communities')->select('id', 'name')->get() as $community) {
            $base = Str::slug($community->name) ?: 'community';
            $slug = $base;
            $suffix = 2;

            while (in_array($slug, $usedSlugs, true)) {
                $slug = $base.'-'.$suffix++;
            }

            $usedSlugs[] = $slug;

            DB::table('communities')->where('id', $community->id)->update(['slug' => $slug]);
        }
    }
};
