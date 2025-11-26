<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Languages;
use App\Models\TeacherLanguage;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\TeacherServices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LanguageStudyController extends Controller
{
    /**
     * Get all teachers offering language study service with their languages
     * GET /api/language-study/teachers
     */
    public function getAllTeachersWithLanguages(): JsonResponse
    {
        try {
            // Get all teachers with language study service (service_id = 4)
            $teachers = User::where('role_id', 3)
                ->where('is_active', 1)
                ->with([
                    'teacherLanguages.language',
                    'profile:id,user_id,bio,verified'
                ])
                ->join('teacher_services', 'users.id', '=', 'teacher_services.teacher_id')
                ->where('teacher_services.service_id', 4) // Language study service
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.gender', 'users.nationality')
                ->distinct()
                ->get();

            $data = $teachers->map(function ($teacher) {
                $languages = $teacher->teacherLanguages()->with('language')->get()->map(function ($tl) {
                    return [
                        'id' => $tl->language->id,
                        'name_en' => $tl->language->name_en,
                        'name_ar' => $tl->language->name_ar,
                    ];
                })->values();

                return [
                    'id' => $teacher->id,
                    'first_name' => $teacher->first_name,
                    'last_name' => $teacher->last_name,
                    'email' => $teacher->email,
                    'gender' => $teacher->gender,
                    'nationality' => $teacher->nationality,
                    'verified' => optional($teacher->profile)->verified,
                    'bio' => optional($teacher->profile)->bio,
                    'languages' => $languages,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Teachers with language study service retrieved successfully',
                'total' => count($data),
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching language study teachers', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve teachers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all languages by a specific teacher
     * GET /api/language-study/teacher/{teacherId}
     */
    public function getTeacherLanguages($teacherId): JsonResponse
    {
        try {
            $teacher = User::where('id', $teacherId)
                ->where('role_id', 3)
                ->where('is_active', 1)
                ->with('teacherLanguages.language')
                ->firstOrFail();

            $languages = $teacher->teacherLanguages()->with('language')->get()->map(function ($tl) {
                return [
                    'id' => $tl->language->id,
                    'name_en' => $tl->language->name_en,
                    'name_ar' => $tl->language->name_ar,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Teacher languages retrieved successfully',
                'data' => [
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->first_name . ' ' . $teacher->last_name,
                    'languages' => $languages,
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching teacher languages', ['teacher_id' => $teacherId, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve teacher languages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Teacher: Add languages they teach
     * POST /api/language-study/teacher/languages
     * Body: { "language_ids": [1, 2, 3] }
     */
    public function addTeacherLanguages(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        // Verify user is a teacher
        if ($user->role_id != 3) {
            return response()->json([
                'success' => false,
                'message' => 'Only teachers can add languages'
            ], 403);
        }

        $request->validate([
            'language_ids' => 'required|array|min:1',
            'language_ids.*' => 'required|exists:languages,id',
        ]);

        DB::beginTransaction();
        try {
            // Delete existing languages first
            TeacherLanguage::where('teacher_id', $user->id)->delete();

            // Add new languages
            foreach ($request->language_ids as $languageId) {
                TeacherLanguage::create([
                    'teacher_id' => $user->id,
                    'language_id' => $languageId,
                ]);
            }

            DB::commit();

            // Get the updated languages
            $languages = $user->teacherLanguages()->with('language')->get()->map(function ($tl) {
                return [
                    'id' => $tl->language->id,
                    'name_en' => $tl->language->name_en,
                    'name_ar' => $tl->language->name_ar,
                ];
            })->values();

            Log::info('Teacher languages updated', ['teacher_id' => $user->id, 'language_count' => count($languages)]);

            return response()->json([
                'success' => true,
                'message' => 'Languages updated successfully',
                'data' => [
                    'teacher_id' => $user->id,
                    'languages' => $languages,
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error adding teacher languages', ['teacher_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to add languages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Teacher: Update their languages
     * PUT /api/language-study/teacher/languages
     * Body: { "language_ids": [1, 2] }
     */
    public function updateTeacherLanguages(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role_id != 3) {
            return response()->json([
                'success' => false,
                'message' => 'Only teachers can update languages'
            ], 403);
        }

        $request->validate([
            'language_ids' => 'required|array|min:1',
            'language_ids.*' => 'required|exists:languages,id',
        ]);

        DB::beginTransaction();
        try {
            // Delete existing languages
            TeacherLanguage::where('teacher_id', $user->id)->delete();

            // Add new languages
            foreach ($request->language_ids as $languageId) {
                TeacherLanguage::create([
                    'teacher_id' => $user->id,
                    'language_id' => $languageId,
                ]);
            }

            DB::commit();

            $languages = $user->teacherLanguages()->with('language')->get()->map(function ($tl) {
                return [
                    'id' => $tl->language->id,
                    'name_en' => $tl->language->name_en,
                    'name_ar' => $tl->language->name_ar,
                ];
            })->values();

            Log::info('Teacher languages updated', ['teacher_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Languages updated successfully',
                'data' => [
                    'teacher_id' => $user->id,
                    'languages' => $languages,
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating teacher languages', ['teacher_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update languages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Teacher: Delete a language from their teaching languages
     * DELETE /api/language-study/teacher/languages/{languageId}
     */
    public function deleteTeacherLanguage($languageId): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role_id != 3) {
            return response()->json([
                'success' => false,
                'message' => 'Only teachers can delete languages'
            ], 403);
        }

        try {
            $deleted = TeacherLanguage::where('teacher_id', $user->id)
                ->where('language_id', $languageId)
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Language not found in your teaching languages'
                ], 404);
            }

            Log::info('Teacher language deleted', ['teacher_id' => $user->id, 'language_id' => $languageId]);

            return response()->json([
                'success' => true,
                'message' => 'Language removed successfully',
                'data' => [
                    'teacher_id' => $user->id,
                    'deleted_language_id' => $languageId,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting teacher language', ['teacher_id' => $user->id, 'language_id' => $languageId, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete language',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Student/Guest: Filter teachers by language
     * GET /api/language-study/teachers/filter?language_id=1
     */
    public function filterTeachersByLanguage(Request $request): JsonResponse
    {
        $request->validate([
            'language_id' => 'required|exists:languages,id',
        ]);

        try {
            $languageId = $request->language_id;

            // Get teachers teaching this language
            $teachers = User::where('role_id', 3)
                ->where('is_active', 1)
                ->join('teacher_languages', 'users.id', '=', 'teacher_languages.teacher_id')
                ->join('teacher_services', 'users.id', '=', 'teacher_services.teacher_id')
                ->where('teacher_languages.language_id', $languageId)
                ->where('teacher_services.service_id', 4) // Language study service
                ->with('profile:id,user_id,bio,verified')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.gender', 'users.nationality')
                ->distinct()
                ->get();

            // Get language info
            $language = Languages::find($languageId);

            $data = $teachers->map(function ($teacher) {
                return [
                    'id' => $teacher->id,
                    'first_name' => $teacher->first_name,
                    'last_name' => $teacher->last_name,
                    'email' => $teacher->email,
                    'gender' => $teacher->gender,
                    'nationality' => $teacher->nationality,
                    'verified' => optional($teacher->profile)->verified,
                    'bio' => optional($teacher->profile)->bio,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Teachers filtered by language',
                'language' => [
                    'id' => $language->id,
                    'name_en' => $language->name_en,
                    'name_ar' => $language->name_ar,
                ],
                'total' => count($data),
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Error filtering teachers by language', ['language_id' => $request->language_id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to filter teachers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Original endpoint - all languages with their teachers
     * GET /api/language-study
     */
    public function index(): JsonResponse
    {
        $languages = Languages::where('status', 1)->get(['id', 'name_en', 'name_ar']);

        $data = [];
        foreach ($languages as $language) {
            $teachers = TeacherLanguage::where('language_id', $language->id)
                ->with(['teacher' => function ($query) {
                    $query->where('is_active', 1)
                          ->select('id', 'first_name', 'last_name', 'email');
                }])
                ->get()
                ->pluck('teacher')
                ->filter();

            $data[] = [
                'language' => $language,
                'teachers' => $teachers->values(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}