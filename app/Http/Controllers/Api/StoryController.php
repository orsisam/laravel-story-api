<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    public function index(Request $request)
    {
        $stories = Story::with('user')
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => true,
            'data' => $stories,
        ]);
    }

    public function myStories(Request $request)
    {
        $stories = $request->user()
            ->stories()
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => true,
            'data' => $stories,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $data = [
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'content' => $request->content,
        ];

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('stories', 'public');
        }

        $story = Story::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Story berhasil dibuat',
            'data' => $story->load('user'),
        ], 201);
    }

    public function show(Story $story)
    {
        return response()->json([
            'status' => true,
            'data' => $story->load('user'),
        ]);
    }

    public function update(Request $request, Story $story)
    {
        // Check ownership
        if ($story->user_id !== $request->user()->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $data = [
            'title' => $request->title,
            'content' => $request->content,
        ];

        if ($request->hasFile('image')) {
            // Delete old image
            if ($story->image) {
                Storage::disk('public')->delete($story->image);
            }
            $data['image'] = $request->file('image')->store('stories', 'public');
        }

        $story->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Story berhasil diupdate',
            'data' => $story->fresh()->load('user'),
        ]);
    }

    public function destroy(Request $request, Story $story)
    {
        // check ownership
        if ($story->user_id !== $request->user()->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Delete image
        if ($story->image) {
            Storage::disk('public')->delete($story->image);
        }

        $story->delete();

        return response()->json([
            'status' => true,
            'message' => 'Story berhasil dihapus',
        ]);
    }
}
