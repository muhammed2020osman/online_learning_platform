<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;

class ServicesController extends Controller
{

public function listServices()
    {
        $services = \App\Models\Services::where('status', 1)->get(['name_en','name_ar','id']);
        return response()->json($services);
    }

public function studentIndex()
    {
        //
        $services = \App\Models\Services::where('status', 1)
        ->where('role_id', 4)
        ->get();
        return response()->json($services);
    }

    /**
     * Get subjects by service ID
     */
    public function getSubjectsByService($serviceId): JsonResponse
    {
        $subjects = Subject::select('id', 'name_en', 'name_ar')
                          ->where('service_id', $serviceId)
                          ->where('status', true)
                          ->orderBy('id', 'asc')
                          ->get();
        
        return response()->json([
            'success' => true,
            'data' => $subjects
        ]);
    }

    public function teacherIndex()
    {
        //
        $services = \App\Models\Services::where('status', 1)
        ->where('role_id', 3)
        ->get();
        return response()->json($services);
    }

    public function listSubjects($serviceId)
    {
        $subjects = Subject::where('service_id', $serviceId)
            ->where('status', 1)
            ->get(['id', 'name_en', 'name_ar']);
        return response()->json($subjects);
    }

    public function subjectDetails($id)
    {
        $subject = Subject::with('service')->find($id);
        if (! $subject) {
            return response()->json(['message' => 'Subject not found'], 404);
        }
        return response()->json($subject);
    }
}
