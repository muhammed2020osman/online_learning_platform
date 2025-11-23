<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TeacherTeachClasses;
use App\Models\TeacherSubject;
use App\Models\Subject;
use Illuminate\Support\Facades\Log;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function indexSubjects(Request $request)
    {
        $teacherId = $request->user()->id;
        $subjects = TeacherSubject::where('teacher_id', $teacherId)
            ->with('subject','class') // You can define a relation in TeacherSubject for subject if needed
            ->get();
        $subjectsData = $subjects->map(function ($item) {
            return [
                'id' => $item->subject->id,
                'name_en' => $item->subject->name_en,
                'name_ar' => $item->subject->name_ar,
                'class_id' => $item->subject->class_id,
                'education_level_id' => $item->subject->education_level_id,
            ];
        });

        return response()->json([$subjectsData]);
    }

    // classes
    public function indexClasses(Request $request)
    {
        $teacherId = $request->user()->id;
        $classes = TeacherTeachClasses::where('teacher_id', $teacherId)
            ->with('class') // You can define a relation in TeacherTeachClasses for class if needed
            ->get();

        return response()->json(['data' => $classes]);
    }

    public function storeSubject(Request $request)
    {
        Log::info('Storing subjects for teacher', ['request' => $request->all()]);
        $request->validate([
            'subjects_id' => 'required|array',
            'subjects_id.*' => 'exists:subjects,id',
        ]);
        $teacherId = $request->user()->id;

        $created = [];
        foreach ($request->subjects_id as $subjectId) {
            $created[] = TeacherSubject::firstOrCreate([
                'teacher_id' => $teacherId,
                'subject_id' => $subjectId,
            ]);
        }

        return response()->json(['message' => 'success']);
    }

    // classes
    public function storeClass(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
        ]);
        $teacherId = $request->user()->id;

        $created = [];
        foreach ($request->class_id as $classId) {
            $created[] = TeacherTeachClasses::firstOrCreate([
                'teacher_id' => $teacherId,
                'class_id' => $classId,
            ]);
        }
        return response()->json(['message' => 'success']);
    }

    public function show($id)
    {
        //
    }

    public function updateSubject(Request $request, $id)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
        ]);
        $teacherId = $request->user()->id;

        $teacherSubject = TeacherSubject::where('id', $id)->where('teacher_id', $teacherId)->firstOrFail();
        $teacherSubject->update([
            'subject_id' => $request->subject_id,
        ]);

        return response()->json(['data' => $teacherSubject, 'message' => 'Subject updated']);
    }

    // classes
    public function updateClass(Request $request, $id)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
        ]);
        $teacherId = $request->user()->id;

        $teacherClass = TeacherTeachClasses::where('id', $id)->where('teacher_id', $teacherId)->firstOrFail();
        $teacherClass->update([
            'class_id' => $request->class_id,
        ]);

        return response()->json(['data' => $teacherClass, 'message' => 'Class updated']);
    }

    public function destroySubject($id)
    {
        $teacherSubject = TeacherSubject::findOrFail($id);
        $teacherSubject->delete();

        return response()->json(['message' => 'Subject deleted']);
    }
    public function destroyClass($id)
    {
        $teacherClass = TeacherTeachClasses::findOrFail($id);
        $teacherClass->delete();

        return response()->json(['message' => 'Class deleted']);
    }
}
