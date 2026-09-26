<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DocumentFolderResource;
use App\Models\Community;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Documents
 */
class DocumentFolderController extends Controller
{
    /**
     * List folders
     *
     * Every folder you can see, flat; build the tree from `parent_id` (null = top level).
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\DocumentFolderResource
     *
     * @apiResourceModel App\Models\DocumentFolder
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Document::class, $community]);

        /** @var User $user */
        $user = $request->user();

        return DocumentFolderResource::collection(
            $community->documentFolders()->orderBy('name')->get()
                ->filter(fn (DocumentFolder $folder) => $user->can('view', $folder))
                ->values(),
        );
    }
}
