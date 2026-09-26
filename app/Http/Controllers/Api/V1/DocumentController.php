<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DocumentResource;
use App\Models\Community;
use App\Models\Document;
use App\Models\User;
use App\Support\Api\ApiQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Documents
 *
 * Only the documents your role or residency may see are listed. Download a file from its
 * `file.download_url` with the same bearer token.
 */
class DocumentController extends Controller
{
    /**
     * List documents
     *
     * @queryParam filter[folder_id] Documents in this folder; `root` for those outside any folder. Example: root
     * @queryParam filter[search] Title contains. Example: bylaws
     * @queryParam sort Sort by `title` or `updated_at`. Example: title
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\DocumentResource
     *
     * @apiResourceModel App\Models\Document paginate=25 with=currentVersion
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Document::class, $community]);

        /** @var User $user */
        $user = $request->user();

        return DocumentResource::collection(ApiQuery::paginate(
            $request,
            $community->documents()->with('currentVersion')->getQuery(),
            filters: [
                'folder_id' => fn (Builder $query, string $value) => $value === 'root' ? $query->whereNull('folder_id') : $query->where('folder_id', (int) $value),
                'search' => fn (Builder $query, string $value) => $query->where('title', 'like', '%'.$value.'%'),
            ],
            sorts: ['title', 'updated_at'],
            defaultSort: 'title',
            visible: fn (Document $document) => $user->can('view', $document),
        ));
    }

    /**
     * Show a document
     *
     * @apiResource App\Http\Resources\Api\V1\DocumentResource
     *
     * @apiResourceModel App\Models\Document with=currentVersion
     */
    public function show(Community $community, Document $document): DocumentResource
    {
        Gate::authorize('view', $document);

        return new DocumentResource($document->load('currentVersion'));
    }
}
