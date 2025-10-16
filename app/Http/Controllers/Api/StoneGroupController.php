<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoneGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Ramsey\Uuid\Uuid;

class StoneGroupController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/stone-groups",
     *     summary="Get all stone groups with pagination (Admin only)",
     *     tags={"Stone Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="is_disabled",
     *         in="query",
     *         description="Filter by disabled status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (default: 15)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of stone groups"
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin access required")
     * )
     */
    public function index(Request $request)
    {
        $query = StoneGroup::with(['user:id,name,email']);

        // Filter by disabled status
        if ($request->has('is_disabled') && $request->is_disabled !== '') {
            $query->where('is_disabled', $request->boolean('is_disabled'));
        }

        // Pagination
        $perPage = $request->per_page ?? 15;
        $stoneGroups = $query->orderBy('stonegroup_name')->paginate($perPage);

        // Custom pagination response
        $response = [
            'data' => $stoneGroups->items(),
            'pagination' => [
                'current_page' => $stoneGroups->currentPage(),
                'per_page' => $stoneGroups->perPage(),
                'total' => $stoneGroups->total(),
                'last_page' => $stoneGroups->lastPage(),
                'from' => $stoneGroups->firstItem(),
                'to' => $stoneGroups->lastItem(),
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $response,
            'message' => 'Stone groups fetched successfully.'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/stone-groups",
     *     summary="Create a new stone group (Admin only)",
     *     tags={"Stone Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"stonegroup_name", "stonegroup_GUID"},
     *             @OA\Property(property="stonegroup_GUID", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     *             @OA\Property(property="stonegroup_name", type="string", example="Granite Group"),
     *             @OA\Property(property="stonegroup_shortname", type="string", example="GRAN"),
     *             @OA\Property(property="is_disabled", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Stone group created successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin access required"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'stonegroup_GUID' => 'required|uuid|unique:stone_groups,stonegroup_GUID',
            'stonegroup_name' => 'required|string|max:255|unique:stone_groups,stonegroup_name',
            'stonegroup_shortname' => 'nullable|string|max:100|unique:stone_groups,stonegroup_shortname',
            'is_disabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $stoneGroup = StoneGroup::create([
            'stonegroup_GUID' => $request->stonegroup_GUID,
            'stonegroup_name' => $request->stonegroup_name,
            'stonegroup_shortname' => $request->stonegroup_shortname,
            'is_disabled' => $request->is_disabled ?? false,
            'user_id' => auth()->id(),
        ]);

        $stoneGroup->load(['user:id,name,email']);

        return response()->json([
            'success' => true,
            'message' => 'Stone group created successfully',
            'data' => $stoneGroup
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/stone-groups/{stonegroup_ID}",
     *     summary="Get stone group by ID (Admin only)",
     *     tags={"Stone Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="stonegroup_ID",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Stone group details"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin access required"),
     *     @OA\Response(response=404, description="Stone group not found")
     * )
     */
    public function show($stonegroup_ID)
    {
        $stoneGroup = StoneGroup::with(['user:id,name,email'])->find($stonegroup_ID);

        if (!$stoneGroup) {
            return response()->json([
                'success' => false,
                'message' => 'Stone group not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $stoneGroup
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/stone-groups/{stonegroup_ID}",
     *     summary="Update a stone group (Admin only)",
     *     tags={"Stone Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="stonegroup_ID",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="stonegroup_GUID", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     *             @OA\Property(property="stonegroup_name", type="string", example="Updated Granite Group"),
     *             @OA\Property(property="stonegroup_shortname", type="string", example="UGRAN"),
     *             @OA\Property(property="is_disabled", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Stone group updated successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin access required"),
     *     @OA\Response(response=404, description="Stone group not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $stonegroup_ID)
    {
        $stoneGroup = StoneGroup::find($stonegroup_ID);

        if (!$stoneGroup) {
            return response()->json([
                'success' => false,
                'message' => 'Stone group not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'stonegroup_GUID' => [
                'sometimes',
                'uuid',
                Rule::unique('stone_groups', 'stonegroup_GUID')->ignore($stoneGroup->stonegroup_ID, 'stonegroup_ID')
            ],
            'stonegroup_name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('stone_groups', 'stonegroup_name')->ignore($stoneGroup->stonegroup_ID, 'stonegroup_ID')
            ],
            'stonegroup_shortname' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('stone_groups', 'stonegroup_shortname')->ignore($stoneGroup->stonegroup_ID, 'stonegroup_ID')
            ],
            'is_disabled' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $stoneGroup->update($request->all());
        $stoneGroup->load(['user:id,name,email']);

        return response()->json([
            'success' => true,
            'message' => 'Stone group updated successfully',
            'data' => $stoneGroup
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/stone-groups/{stonegroup_ID}",
     *     summary="Delete a stone group (Admin only)",
     *     tags={"Stone Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="stonegroup_ID",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Stone group deleted successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin access required"),
     *     @OA\Response(response=404, description="Stone group not found")
     * )
     */
    public function destroy($stonegroup_ID)
    {
        $stoneGroup = StoneGroup::find($stonegroup_ID);

        if (!$stoneGroup) {
            return response()->json([
                'success' => false,
                'message' => 'Stone group not found'
            ], 404);
        }

        $stoneGroup->delete();

        return response()->json([
            'success' => true,
            'message' => 'Stone group deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/stone-groups/search/{keyword}",
     *     summary="Search stone groups by keyword (Admin only)",
     *     tags={"Stone Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="keyword",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="is_disabled",
     *         in="query",
     *         description="Filter by disabled status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Search results"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin access required")
     * )
     */
    public function search($keyword, Request $request)
    {
        $query = StoneGroup::with(['user:id,name,email'])
            ->where(function ($q) use ($keyword) {
                $q->where('stonegroup_name', 'like', "%{$keyword}%")
                  ->orWhere('stonegroup_shortname', 'like', "%{$keyword}%")
                  ->orWhere('stonegroup_GUID', 'like', "%{$keyword}%");
            });

        // Filter by disabled status
        if ($request->has('is_disabled') && $request->is_disabled !== '') {
            $query->where('is_disabled', $request->boolean('is_disabled'));
        }

        // Pagination
        $perPage = $request->per_page ?? 15;
        $page = $request->page ?? 1;
        
        $stoneGroups = $query->orderBy('stonegroup_name')->paginate($perPage, ['*'], 'page', $page);

        // Custom pagination response for search
        $response = [
            'data' => $stoneGroups->items(),
            'pagination' => [
                'current_page' => $stoneGroups->currentPage(),
                'per_page' => $stoneGroups->perPage(),
                'total' => $stoneGroups->total(),
                'last_page' => $stoneGroups->lastPage(),
                'from' => $stoneGroups->firstItem(),
                'to' => $stoneGroups->lastItem(),
            ]
        ];

        return response()->json([
            'success' => true,
            'search_keyword' => $keyword,
            'data' => $response,
            'filters' => [
                'is_disabled' => $request->has('is_disabled') ? $request->boolean('is_disabled') : null,
                'per_page' => $perPage,
                'page' => $page
            ],
            'message' => 'Stone groups search completed successfully.'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/stone-groups/bulk-upload",
     *     summary="Bulk upload stone groups from Excel file (Admin only)",
     *     tags={"Stone Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Excel file (.xlsx, .xls, .csv) with columns: stonegroup_GUID, stonegroup_name, stonegroup_shortname, is_disabled"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Bulk upload completed with summary"),
     *     @OA\Response(response=400, description="Invalid file format or structure"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin access required"),
     *     @OA\Response(response=422, description="Validation errors in file data")
     * )
     */
    public function bulkUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file format.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            Log::info('Bulk upload file received', ['filename' => $file->getClientOriginalName()]);

            // Read Excel data as an array
            $rows = Excel::toArray([], $file);
            $data = $rows[0]; // first sheet

            if (empty($data) || count($data) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'The uploaded file is empty or missing data rows.'
                ], 400);
            }

            // First row assumed to be headers - normalize them
            $headers = array_map('strtolower', array_map('trim', $data[0]));
            $inserted = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];

            DB::beginTransaction();

            foreach (array_slice($data, 1) as $index => $row) {
                // Ensure the row has the same number of columns as headers
                if (count($row) !== count($headers)) {
                    $skipped++;
                    $errors[] = "Row " . ($index + 2) . ": Column count mismatch";
                    continue;
                }

                $rowData = array_combine($headers, $row);
                
                // Clean the data
                $rowData = array_map(function ($value) {
                    return is_string($value) ? trim($value) : $value;
                }, $rowData);

                // Validate required fields
                if (empty($rowData['stonegroup_name']) || empty($rowData['stonegroup_guid'])) {
                    $skipped++;
                    $errors[] = "Row " . ($index + 2) . ": Missing required fields (stonegroup_name or stonegroup_guid)";
                    continue;
                }

                // Validate GUID format
                if (!Uuid::isValid($rowData['stonegroup_guid'])) {
                    $skipped++;
                    $errors[] = "Row " . ($index + 2) . ": Invalid GUID format";
                    continue;
                }

                // Prepare data for create/update
                $stoneGroupData = [
                    'stonegroup_GUID' => $rowData['stonegroup_guid'],
                    'stonegroup_name' => $rowData['stonegroup_name'],
                    'stonegroup_shortname' => $rowData['stonegroup_shortname'] ?? null,
                    'is_disabled' => isset($rowData['is_disabled']) ? 
                        (bool)$rowData['is_disabled'] : false,
                    'user_id' => auth()->id(),
                ];

                // Check existing record by GUID or name
                $existing = StoneGroup::where('stonegroup_GUID', $rowData['stonegroup_guid'])
                    ->orWhere('stonegroup_name', $rowData['stonegroup_name'])
                    ->first();

                if ($existing) {
                    // Update existing record
                    $existing->update($stoneGroupData);
                    $updated++;
                } else {
                    // Create new record
                    StoneGroup::create($stoneGroupData);
                    $inserted++;
                }
            }

            DB::commit();

            $response = [
                'success' => true,
                'message' => 'Bulk upload completed successfully.',
                'summary' => [
                    'inserted' => $inserted,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'total_processed' => $inserted + $updated + $skipped,
                ]
            ];

            if (!empty($errors)) {
                $response['errors'] = $errors;
                $response['message'] = 'Bulk upload completed with some errors.';
            }

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stone Group Bulk Upload Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during bulk upload.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}