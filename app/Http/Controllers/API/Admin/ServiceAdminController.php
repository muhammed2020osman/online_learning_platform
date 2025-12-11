<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Services;

class ServiceAdminController extends Controller
{
    public function index(Request $request)
    {
        $services = Services::orderBy('id','desc')->get();
        return response()->json(['success' => true, 'data' => $services]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'status' => 'nullable|string'
        ]);
        $service = Services::create($data);
        return response()->json(['success' => true, 'data' => $service], 201);
    }

    public function update(Request $request, $id)
    {
        $service = Services::findOrFail($id);
        $service->update($request->only(['name','description','status']));
        return response()->json(['success' => true, 'data' => $service]);
    }

    public function destroy(Request $request, $id)
    {
        $service = Services::findOrFail($id);
        $service->delete();
        return response()->json(['success' => true]);
    }
}
