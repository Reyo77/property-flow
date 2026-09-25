<?php

namespace App\Support\Finance;

use App\Models\Community;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free sequential numbers per community (invoice 1, 2, 3...). Must be called inside a
 * transaction: it locks the community row so two requests can't take the same number.
 */
class DocumentNumbers
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function next(Community $community, string $modelClass): int
    {
        Community::query()->withoutGlobalScopes()->whereKey($community->id)->lockForUpdate()->first();

        $table = (new $modelClass)->getTable();

        return ((int) DB::table($table)->where('community_id', $community->id)->max('number')) + 1;
    }
}
