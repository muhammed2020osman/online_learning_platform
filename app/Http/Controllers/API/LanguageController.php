<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Languages;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class LanguageController extends Controller
{
    /**
     * Get all languages (with soft deletes support)
     * GET /api/languages
     * Query parameters:
     *   - include_deleted: boolean (show soft-deleted languages)
     *   - status: 1|0 (filter by active/inactive)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Languages::query();

            // Include soft-deleted languages if requested
            if ($request->boolean('include_deleted')) {
                $query->withTrashed();
            }

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $languages = $query->get();

            return response()->json([
                'success' => true,
                'message' => 'Languages retrieved successfully',
                'total' => count($languages),
                'data' => $languages,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching languages', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve languages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new language
     * POST /api/languages
     * Body: {
     *   "name_en": "English",
     *   "name_ar": "الإنجليزية",
     *   "status": 1
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name_en' => 'required|string|unique:languages,name_en',
            'name_ar' => 'required|string|unique:languages,name_ar',
            'status' => 'nullable|boolean',
        ]);

        try {
            $language = Languages::create([
                'name_en' => $request->name_en,
                'name_ar' => $request->name_ar,
                'status' => $request->status ?? 1, // Default to active
            ]);

            Log::info('Language created', ['language_id' => $language->id, 'name_en' => $language->name_en]);

            return response()->json([
                'success' => true,
                'message' => 'Language created successfully',
                'data' => $language,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating language', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create language',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific language
     * GET /api/languages/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            $language = Languages::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Language retrieved successfully',
                'data' => $language,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching language', ['language_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve language',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a language
     * PUT /api/languages/{id}
     * Body: {
     *   "name_en": "Updated English",
     *   "name_ar": "الإنجليزية المحدثة",
     *   "status": 1
     * }
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $language = Languages::findOrFail($id);

            $request->validate([
                'name_en' => 'sometimes|string|unique:languages,name_en,' . $id,
                'name_ar' => 'sometimes|string|unique:languages,name_ar,' . $id,
                'status' => 'nullable|boolean',
            ]);

            $language->update([
                'name_en' => $request->name_en ?? $language->name_en,
                'name_ar' => $request->name_ar ?? $language->name_ar,
                'status' => $request->has('status') ? $request->status : $language->status,
            ]);

            Log::info('Language updated', ['language_id' => $language->id]);

            return response()->json([
                'success' => true,
                'message' => 'Language updated successfully',
                'data' => $language,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating language', ['language_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update language',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft delete a language (preserves data)
     * DELETE /api/languages/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $language = Languages::findOrFail($id);

            // Check if language is being used by teachers
            $teacherCount = $language->teacherLanguages()->count();
            if ($teacherCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete language that is assigned to teachers',
                    'data' => [
                        'teacher_count' => $teacherCount,
                    ]
                ], 409);
            }

            $language->delete(); // Soft delete if SoftDeletes trait is used

            Log::info('Language deleted', ['language_id' => $language->id]);

            return response()->json([
                'success' => true,
                'message' => 'Language deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting language', ['language_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete language',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Permanently delete a language (hard delete)
     * DELETE /api/languages/{id}/force
     */
    public function forceDestroy($id): JsonResponse
    {
        try {
            $language = Languages::withTrashed()->findOrFail($id);

            // Check if language is being used by teachers
            $teacherCount = $language->teacherLanguages()->count();
            if ($teacherCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot permanently delete language that is assigned to teachers',
                    'data' => [
                        'teacher_count' => $teacherCount,
                    ]
                ], 409);
            }

            $language->forceDelete(); // Permanent delete

            Log::info('Language permanently deleted', ['language_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Language permanently deleted',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error permanently deleting language', ['language_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete language',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted language
     * POST /api/languages/{id}/restore
     */
    public function restore($id): JsonResponse
    {
        try {
            $language = Languages::withTrashed()->findOrFail($id);

            if (!$language->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Language is not deleted'
                ], 409);
            }

            $language->restore();

            Log::info('Language restored', ['language_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Language restored successfully',
                'data' => $language,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error restoring language', ['language_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore language',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
