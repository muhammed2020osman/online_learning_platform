<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    public function index(Request $request)
    {
        $items = Attachment::where('attached_to_type', 'gallery')->orderByDesc('id')->paginate(25);
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request)
    {
        $request->validate(['file' => 'required|file|image|max:5120','title' => 'nullable|string']);
        $file = $request->file('file');
        $path = $file->store('gallery', 'public');
        $url = asset('storage/' . $path);
        $attachment = Attachment::create([
            'user_id' => $request->user()->id,
            'file_path' => $url,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'attached_to_type' => 'gallery'
        ]);
        return response()->json(['success' => true, 'data' => $attachment], 201);
    }

    public function destroy(Request $request, $id)
    {
        $att = Attachment::findOrFail($id);
        // Optionally delete file from storage
        try { if ($att->file_path) { /* not removing physical file to be safe */ } } catch (\Throwable $e) {}
        $att->delete();
        return response()->json(['success' => true]);
    }
}
